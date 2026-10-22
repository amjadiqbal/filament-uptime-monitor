<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Support;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\UptimeMonitor\Actions\SendUptimeCheckRequestAction;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * The package's request action, used for checks started from the panel: it also refuses to follow a redirect to a
 * private host, so a public URL can't bounce the request into the internal network.
 */
class GuardedUptimeCheckRequestAction extends SendUptimeCheckRequestAction
{
    /**
     * @return array<string, mixed>
     */
    protected function requestOptions(Monitor $monitor): array
    {
        $options = parent::requestOptions($monitor);
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
}
