<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures;

use Spatie\UptimeMonitor\Models\Monitor;

class MonitorPolicyWithCheckNow extends MonitorPolicy
{
    public function checkNow(User $user, ?Monitor $monitor = null): bool
    {
        return (bool) static::$canCheckNow;
    }
}
