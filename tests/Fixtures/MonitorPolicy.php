<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures;

use Spatie\UptimeMonitor\Models\Monitor;

class MonitorPolicy
{
    public static bool $canUpdate = true;

    public static ?bool $canCheckNow = null;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Monitor $monitor): bool
    {
        return static::$canUpdate;
    }

    public function delete(User $user, Monitor $monitor): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
