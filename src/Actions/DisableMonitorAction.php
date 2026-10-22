<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Actions;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * Turns uptime and certificate checks off: the package's Monitor::disable().
 */
class DisableMonitorAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'disableMonitor';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-uptime-monitor::uptime-monitor.actions.disable.label'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedPauseCircle)
            ->modalIconColor('gray')
            ->modalHeading(__('filament-uptime-monitor::uptime-monitor.actions.disable.modal_heading'))
            ->modalDescription(__('filament-uptime-monitor::uptime-monitor.actions.disable.modal_description'))
            ->modalSubmitActionLabel(__('filament-uptime-monitor::uptime-monitor.actions.disable.label'))
            ->visible(fn (Monitor $record): bool => UptimeMonitor::isEnabled($record))
            ->authorize(fn (Monitor $record): bool => UptimeMonitor::can('update', $record))
            ->action(function (Monitor $record): void {
                $record->disable();

                Notification::make()->success()
                    ->title(trans_choice('filament-uptime-monitor::uptime-monitor.actions.disable.success', 1, ['count' => 1]))
                    ->send();
            });
    }
}
