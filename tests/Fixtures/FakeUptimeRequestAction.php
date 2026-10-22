<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Spatie\UptimeMonitor\Actions\SendUptimeCheckRequestAction;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * Answers uptime requests without the network: a URL mapped to a string fails to connect with that message, anything
 * else gets a 200 with the body "Hello world".
 */
class FakeUptimeRequestAction extends SendUptimeCheckRequestAction
{
    /** @var array<string, string> */
    public static array $failures = [];

    /** @var array<int, string> */
    public static array $requested = [];

    public function execute(Monitor $monitor): PromiseInterface
    {
        $url = $monitor->raw_url;
        static::$requested[] = $url;

        if (isset(static::$failures[$url])) {
            return Create::rejectionFor(new ConnectException(static::$failures[$url], new Request('GET', $url)));
        }

        return Create::promiseFor(new Response(200, [], 'Hello world'));
    }
}
