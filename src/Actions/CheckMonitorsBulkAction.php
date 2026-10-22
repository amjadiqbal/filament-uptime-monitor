<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Actions;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\UptimeMonitor\Models\Monitor;

class CheckMonitorsBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'checkMonitors';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-uptime-monitor::uptime-monitor.actions.check.bulk_label'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => UptimeMonitor::can('checkNow'))
            ->action(function (Collection $records): void {
                $max = UptimeMonitor::plugin()->getMaxChecksPerRequest();

                if ($records->count() > $max) {
                    Notification::make()->danger()->title(__('filament-uptime-monitor::uptime-monitor.actions.check.too_many', ['max' => $max]))->send();

                    return;
                }

                [$allowed, $denied] = $records->partition(fn (Monitor $record): bool => UptimeMonitor::can('checkNow', $record));
                [$enabled, $off] = $allowed->partition(fn (Monitor $record): bool => UptimeMonitor::isEnabled($record));

                if (! UptimeMonitor::consumeCheckAllowance($enabled->count())) {
                    Notification::make()->danger()->title(__('filament-uptime-monitor::uptime-monitor.actions.check.rate_limited'))->send();

                    return;
                }

                ['checked' => $checked, 'blocked' => $blocked, 'errors' => $errors] = UptimeMonitor::check($enabled);

                $down = $checked->filter(fn (Monitor $monitor): bool => UptimeMonitor::uptimeState($monitor) === UptimeMonitor::UPTIME_DOWN
                    || UptimeMonitor::certificateState($monitor) === UptimeMonitor::CERTIFICATE_INVALID)->count();
                $up = $checked->count() - $down;

                $body = collect([
                    __('filament-uptime-monitor::uptime-monitor.actions.check.summary', ['up' => $up, 'down' => $down]),
                    $blocked->count() ? trans_choice('filament-uptime-monitor::uptime-monitor.actions.check.blocked', $blocked->count(), ['count' => $blocked->count()]) : null,
                    $off->count() ? trans_choice('filament-uptime-monitor::uptime-monitor.actions.check.disabled', $off->count(), ['count' => $off->count()]) : null,
                    $denied->count() ? trans_choice('filament-uptime-monitor::uptime-monitor.actions.check.skipped', $denied->count(), ['count' => $denied->count()]) : null,
                    ...array_map(fn (string $error): string => __('filament-uptime-monitor::uptime-monitor.actions.check.error', ['message' => str($error)->limit(200)->toString()]), $errors),
                ])->filter()->implode(' ');

                Notification::make()
                    ->status($down || $blocked->count() || $denied->count() || $errors ? 'danger' : 'success')
                    ->title(trans_choice('filament-uptime-monitor::uptime-monitor.actions.check.checked', $checked->count(), ['count' => $checked->count()]))
                    ->body($body)
                    ->send();
            });
    }
}
