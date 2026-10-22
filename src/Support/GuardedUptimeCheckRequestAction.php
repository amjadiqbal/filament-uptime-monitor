<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\UptimeMonitor\Actions\SendUptimeCheckRequestAction;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * The package's request action, used for checks started from the panel. On top of the package's request it:
 * - resolves the host once, refuses it when it does not resolve or resolves to a private address, and pins curl to the
 *   address it checked (CURLOPT_RESOLVE), so DNS can't change between the check and the connection;
 * - refuses to follow a redirect to a private host, so a public URL can't bounce the request into the internal network.
 */
class GuardedUptimeCheckRequestAction extends SendUptimeCheckRequestAction
{
    /** @var array<int, string> The addresses the host resolved to, set by execute() for requestOptions(). */
    protected array $addresses = [];

    public function execute(Monitor $monitor): PromiseInterface
    {
        $host = trim((string) parse_url($monitor->raw_url, PHP_URL_HOST), '[]');
        $this->addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : HostGuard::resolve($host);

        $refusal = match (true) {
            $this->addresses === [] => "Could not resolve host: {$host}",
            collect($this->addresses)->contains(fn (string $ip): bool => HostGuard::isPrivateIp($ip)) => 'The host resolves to a private address; the request was refused.',
            default => null,
        };

        if ($refusal) {
            return Create::rejectionFor(new ConnectException($refusal, new Request($monitor->uptime_check_method, $monitor->raw_url)));
        }

        return parent::execute($monitor);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestOptions(Monitor $monitor): array
    {
        $options = parent::requestOptions($monitor);
        $options['curl'] = $this->pinnedAddress($monitor);
        $configured = config('uptime-monitor.uptime_check.guzzle_options.allow_redirects', true);

        if ($configured === false) {
            return $options;
        }

        $options['allow_redirects'] = [
            ...(is_array($configured) ? $configured : []),
            'protocols' => ['http', 'https'],
            'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                if (HostGuard::isPrivate($uri->getHost())) {
                    throw new RequestException('Redirect to a private address was refused.', $request, $response);
                }
            },
        ];

        return $options;
    }

    /**
     * The configured curl options plus CURLOPT_RESOLVE for the monitor's host. Request options replace the client's
     * 'curl' array as a whole, so the configured ones are carried over.
     *
     * @return array<int, mixed>
     */
    protected function pinnedAddress(Monitor $monitor): array
    {
        $curl = (array) config('uptime-monitor.uptime_check.guzzle_options.curl', []);
        $parts = parse_url($monitor->raw_url);
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (! defined('CURLOPT_RESOLVE') || $this->addresses === [] || filter_var($host, FILTER_VALIDATE_IP)) {
            return $curl;
        }

        $port = $parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80);
        $ip = collect($this->addresses)->first(fn (string $ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            ?? $this->addresses[0];
        $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        $curl[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$address}"];

        return $curl;
    }
}
