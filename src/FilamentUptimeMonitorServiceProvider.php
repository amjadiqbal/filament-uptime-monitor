<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor;

use AmjadIqbal\FilamentUptimeMonitor\Widgets\MonitorStatusOverview;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentUptimeMonitorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-uptime-monitor';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        // Registered by name as well, so the widget's Livewire updates resolve wherever it is placed, including on
        // a custom dashboard that adds it by class.
        Livewire::component('amjadiqbal.filament-uptime-monitor.monitor-status-overview', MonitorStatusOverview::class);
    }
}
