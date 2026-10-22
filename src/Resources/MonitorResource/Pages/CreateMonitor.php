<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Resources\Pages\CreateRecord;

class CreateMonitor extends CreateRecord
{
    public static function getResource(): string
    {
        return UptimeMonitor::plugin()->getResource();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
