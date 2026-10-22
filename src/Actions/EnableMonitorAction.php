<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Actions;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * Turns uptime checks on, and certificate checks too for https URLs: the package's Monitor::enable().
 */
class EnableMonitorAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'enableMonitor';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-uptime-monitor::uptime-monitor.actions.enable.label'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('gray')
            ->visible(fn (Monitor $record): bool => ! UptimeMonitor::isEnabled($record))
            ->authorize(fn (Monitor $record): bool => UptimeMonitor::can('update', $record))
            ->action(function (Monitor $record): void {
                $record->enable();

                Notification::make()->success()
                    ->title(trans_choice('filament-uptime-monitor::uptime-monitor.actions.enable.success', 1, ['count' => 1]))
                    ->send();
            });
    }
}
