<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Widgets\MonitorStatusOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMonitors extends ListRecords
{
    public static function getResource(): string
    {
        return UptimeMonitor::plugin()->getResource();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return UptimeMonitor::plugin()->hasListWidget() ? [MonitorStatusOverview::class] : [];
    }
}
