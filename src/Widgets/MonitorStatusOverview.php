<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Widgets;

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\UptimeMonitor\Models\Monitor;

class MonitorStatusOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return UptimeMonitor::can('viewAny');
    }

    protected function getHeading(): ?string
    {
        return __('filament-uptime-monitor::uptime-monitor.widget.heading');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $s = UptimeMonitor::summary();
        $t = fn (string $key, array $replace = []): string => __("filament-uptime-monitor::uptime-monitor.widget.{$key}", $replace);

        $lastCheck = $s['last_check'];
        // The monitor with the shortest interval is checked at least that often while the scheduler runs; no check in
        // three of those intervals means the scheduler is probably not running.
        $staleAfter = UptimeMonitor::shortestInterval() * 3;
        $stale = $lastCheck && $s['enabled'] > 0 && $lastCheck->lt(now()->subMinutes($staleAfter));

        return [
            Stat::make($t('up'), (string) $s['up'])
                ->description($t('up_description', ['count' => $s['enabled']]))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($s['up'] > 0 ? 'success' : 'gray'),
            Stat::make($t('down'), (string) $s['down'])
                ->description($s['down'] > 0 ? UptimeMonitor::scopeUptime(UptimeMonitor::query(), UptimeMonitor::UPTIME_DOWN)
                    ->orderBy('url')->limit(3)->get()->map(fn (Monitor $monitor): string => $monitor->raw_url)->implode(', ') : $t('down_none'))
                ->descriptionIcon($s['down'] > 0 ? Heroicon::OutlinedXCircle : Heroicon::OutlinedCheckCircle)
                ->color($s['down'] > 0 ? 'danger' : 'success'),
            Stat::make($t('certificates'), (string) $s['certificate_problems'])
                ->description($s['certificate_problems'] > 0
                    ? $t('certificates_description', ['days' => UptimeMonitor::expiringWithinDays()])
                    : $t('certificates_none'))
                ->descriptionIcon($s['certificate_problems'] > 0 ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedShieldCheck)
                ->color($s['certificate_problems'] > 0 ? 'danger' : 'success'),
            Stat::make($t('last_check'), $lastCheck ? $lastCheck->diffForHumans() : $t('last_check_never'))
                ->description(match (true) {
                    ! $lastCheck => $t('last_check_none'),
                    $stale => $t('last_check_stale'),
                    default => $t('last_check_ok', ['count' => $s['enabled']]),
                })
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($stale || ! $lastCheck ? 'gray' : 'success'),
        ];
    }
}
