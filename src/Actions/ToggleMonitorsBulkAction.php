<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Actions;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * Turn the selected monitors on (ToggleMonitorsBulkAction::enable()) or off (ToggleMonitorsBulkAction::disable()).
 */
class ToggleMonitorsBulkAction extends BulkAction
{
    protected bool $enables = true;

    public static function enable(): static
    {
        return static::make('enableMonitors')->enables(true);
    }

    public static function disable(): static
    {
        return static::make('disableMonitors')->enables(false);
    }

    public function enables(bool $condition): static
    {
        $this->enables = $condition;

        $key = $condition ? 'enable' : 'disable';

        $this
            ->label(__("filament-uptime-monitor::uptime-monitor.actions.{$key}.bulk_label"))
            ->icon($condition ? Heroicon::OutlinedPlayCircle : Heroicon::OutlinedPauseCircle);

        if (! $condition) {
            $this
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedPauseCircle)
                ->modalIconColor('gray')
                ->modalHeading(__('filament-uptime-monitor::uptime-monitor.actions.disable.bulk_modal_heading'))
                ->modalDescription(__('filament-uptime-monitor::uptime-monitor.actions.disable.modal_description'))
                ->modalSubmitActionLabel(__('filament-uptime-monitor::uptime-monitor.actions.disable.label'));
        }

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->enables($this->enables)
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => UptimeMonitor::can('update'))
            ->action(function (Collection $records): void {
                $changed = 0;
                $skipped = 0;
                $key = $this->enables ? 'enable' : 'disable';

                /** @var Monitor $record */
                foreach ($records as $record) {
                    if (! UptimeMonitor::can('update', $record)) {
                        $skipped++;

                        continue;
                    }

                    $this->enables ? $record->enable() : $record->disable();
                    $changed++;
                }

                Notification::make()
                    ->status($skipped ? 'danger' : 'success')
                    ->title(trans_choice("filament-uptime-monitor::uptime-monitor.actions.{$key}.success", $changed, ['count' => $changed]))
                    ->body($skipped ? trans_choice('filament-uptime-monitor::uptime-monitor.actions.skipped', $skipped, ['count' => $skipped]) : null)
                    ->send();
            });
    }
}
