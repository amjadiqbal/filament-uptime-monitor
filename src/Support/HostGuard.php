<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Support;

/**
 * Tells whether a host is, or resolves to, an address that is not on the public internet: loopback, private,
 * link-local, carrier-grade NAT, documentation or other reserved ranges.
 */
class HostGuard
{
    /** @var (callable(string): array<int, string>)|null */
    protected static $resolver = null;

    public static function isPrivate(string $host): bool
    {
        $host = strtolower(rtrim(trim($host, '[]'), '.'));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return static::isPrivateIp($host);
        }

        foreach (static::resolve($host) as $ip) {
            if (static::isPrivateIp($ip)) {
                return true;
            }
        }

        return false;
    }

    public static function isPrivateIp(string $ip): bool
    {
        // ::ffff:127.0.0.1 is 127.0.0.1.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $match)) {
            $ip = $match[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return true;
        }

        // 100.64.0.0/10 (carrier-grade NAT) is not covered by FILTER_FLAG_GLOBAL_RANGE on every PHP version.
        $long = ip2long($ip);

        return $long !== false && ($long & 0xFFC00000) === (100 << 24 | 64 << 16);
    }

    /**
     * @return array<int, string>
     */
    public static function resolve(string $host): array
    {
        if (static::$resolver) {
            return (static::$resolver)($host);
        }

        $ips = @gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Replace DNS lookups, for tests. Null restores real lookups.
     *
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public static function resolveUsing(?callable $resolver): void
    {
        static::$resolver = $resolver;
    }
}
