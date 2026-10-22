<?php

declare(strict_types=1);

use AmjadIqbal\FilamentUptimeMonitor\Support\GuardedUptimeCheckRequestAction;
use AmjadIqbal\FilamentUptimeMonitor\Support\HostGuard;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\CustomMonitor;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;

it('treats loopback, private, link-local, CGNAT and reserved addresses as private', function (string $host): void {
    expect(HostGuard::isPrivate($host))->toBeTrue();
})->with([
    'localhost', 'app.localhost', '127.0.0.1', '127.1.2.3', '10.0.0.1', '172.16.5.4', '192.168.0.1', '169.254.169.254',
    '100.64.0.1', '0.0.0.0', '[::1]', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1', 'intranet.example.com',
]);

it('treats public addresses and hosts as public', function (string $host): void {
    expect(HostGuard::isPrivate($host))->toBeFalse();
})->with(['93.184.215.14', '1.1.1.1', '2606:4700:4700::1111', 'example.com', 'www.example.org']);

it('blocks a host when one of several resolved addresses is private', function (): void {
    HostGuard::resolveUsing(fn (): array => ['93.184.215.14', '127.0.0.1']);

    expect(HostGuard::isPrivate('mixed.example.com'))->toBeTrue();
});

it('refuses redirects to private hosts on checks started from the panel', function (): void {
    $action = new class extends GuardedUptimeCheckRequestAction
    {
        /** @return array<string, mixed> */
        public function options(CustomMonitor $monitor): array
        {
            return $this->requestOptions($monitor);
        }
    };

    $monitor = new CustomMonitor(['url' => 'https://example.com', 'uptime_check_method' => 'get']);
    $options = $action->options($monitor);
    $onRedirect = $options['allow_redirects']['on_redirect'];

    expect($options['allow_redirects']['protocols'])->toBe(['http', 'https']);

    $onRedirect(new Request('GET', 'https://example.com'), new Response(302), new Uri('https://www.example.com/'));

    expect(fn () => $onRedirect(new Request('GET', 'https://example.com'), new Response(302), new Uri('http://169.254.169.254/latest')))
        ->toThrow(RequestException::class, 'Redirect to a private address was refused.');
});

it('keeps redirects off when the package config turns them off', function (): void {
    config(['uptime-monitor.uptime_check.guzzle_options.allow_redirects' => false]);

    $action = new class extends GuardedUptimeCheckRequestAction
    {
        /** @return array<string, mixed> */
        public function options(CustomMonitor $monitor): array
        {
            return $this->requestOptions($monitor);
        }
    };

    expect($action->options(new CustomMonitor(['url' => 'https://example.com'])))->not->toHaveKey('allow_redirects');
});

function guardedAction(): GuardedUptimeCheckRequestAction
{
    return new class extends GuardedUptimeCheckRequestAction
    {
        /** @return array<string, mixed> */
        public function options(CustomMonitor $monitor): array
        {
            return $this->requestOptions($monitor);
        }

        /** @param array<int, string> $addresses */
        public function resolvedTo(array $addresses): static
        {
            $this->addresses = $addresses;

            return $this;
        }
    };
}

it('pins the request to the address it checked', function (string $url, string $resolve): void {
    config(['uptime-monitor.uptime_check.guzzle_options.curl' => [CURLOPT_TCP_KEEPALIVE => 1]]);

    $options = guardedAction()->resolvedTo(['2606:2800::1', '93.184.215.14'])->options(new CustomMonitor(['url' => $url]));

    expect($options['curl'][CURLOPT_RESOLVE])->toBe([$resolve])
        ->and($options['curl'][CURLOPT_TCP_KEEPALIVE])->toBe(1);
})->with([
    'https default port' => ['https://example.com/health', 'example.com:443:93.184.215.14'],
    'http default port' => ['http://example.com', 'example.com:80:93.184.215.14'],
    'explicit port' => ['https://example.com:8443', 'example.com:8443:93.184.215.14'],
]);

it('pins an IPv6-only host with brackets', function (): void {
    $options = guardedAction()->resolvedTo(['2606:2800::1'])->options(new CustomMonitor(['url' => 'https://example.com']));

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['example.com:443:[2606:2800::1]']);
});

it('refuses a host that does not resolve or resolves to a private address at check time', function (string $host, string $reason): void {
    HostGuard::resolveUsing(fn (string $h): array => match ($h) {
        'gone.example.com' => [],
        default => ['10.0.0.9'],
    });

    $promise = guardedAction()->execute(new CustomMonitor(['url' => "https://{$host}", 'uptime_check_method' => 'get']));

    expect(fn () => $promise->wait())->toThrow(ConnectException::class, $reason);
})->with([
    'unresolvable' => ['gone.example.com', 'Could not resolve host: gone.example.com'],
    'rebound to private' => ['rebind.example.com', 'resolves to a private address'],
]);
