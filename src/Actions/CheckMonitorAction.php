<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Actions;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Spatie\UptimeMonitor\Models\Monitor;

class CheckMonitorAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'checkMonitor';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-uptime-monitor::uptime-monitor.actions.check.label'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Monitor $record): bool => UptimeMonitor::isEnabled($record))
            ->authorize(fn (Monitor $record): bool => UptimeMonitor::can('checkNow', $record))
            ->action(function (Monitor $record): void {
                if (! UptimeMonitor::consumeCheckAllowance(1)) {
                    Notification::make()->danger()->title(__('filament-uptime-monitor::uptime-monitor.actions.check.rate_limited'))->send();

                    return;
                }

                ['checked' => $checked, 'errors' => $errors] = UptimeMonitor::check([$record]);

                static::notify($record, $checked->first(), $errors);
            });
    }

    /**
     * @param  array<int, string>  $errors
     */
    public static function notify(Monitor $record, ?Monitor $checked, array $errors = []): void
    {
        $url = $record->raw_url;

        if (! $checked) {
            Notification::make()->danger()->title(__('filament-uptime-monitor::uptime-monitor.validation.private_host'))->send();

            return;
        }

        $lines = [];
        $failing = false;

        if ($checked->uptime_check_enabled) {
            $up = UptimeMonitor::uptimeState($checked) === UptimeMonitor::UPTIME_UP;
            $failing = ! $up;
            $title = __('filament-uptime-monitor::uptime-monitor.actions.check.'.($up ? 'up' : 'down'), ['url' => $url]);

            if (! $up && filled($checked->uptime_check_failure_reason)) {
                $lines[] = str((string) $checked->uptime_check_failure_reason)->limit(300)->toString();
            }
        } else {
            $title = $url;
        }

        if ($checked->certificate_check_enabled) {
            $state = UptimeMonitor::certificateState($checked);

            if ($state === UptimeMonitor::CERTIFICATE_INVALID) {
                $failing = true;
                $lines[] = __('filament-uptime-monitor::uptime-monitor.actions.check.certificate_invalid', [
                    'reason' => str((string) $checked->certificate_check_failure_reason)->limit(200)->toString(),
                ]);
            } elseif ($checked->certificate_expiration_date) {
                $lines[] = __('filament-uptime-monitor::uptime-monitor.actions.check.certificate_valid', [
                    'date' => $checked->certificate_expiration_date->toFormattedDateString(),
                ]);
            }
        }

        foreach ($errors as $error) {
            $failing = true;
            $lines[] = __('filament-uptime-monitor::uptime-monitor.actions.check.error', ['message' => str($error)->limit(200)->toString()]);
        }

        Notification::make()
            ->status($failing ? 'danger' : 'success')
            ->title($title)
            ->body($lines ? implode("\n", $lines) : null)
            ->send();
    }
}
