<?php

declare(strict_types=1);

use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitorPlugin;
use AmjadIqbal\FilamentUptimeMonitor\Widgets\MonitorStatusOverview;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs($this->makeUser());
});

it('counts up, down and certificate problems', function (): void {
    $this->makeMonitor('https://a.example.com', ['uptime_status' => UptimeStatus::UP, 'uptime_last_check_date' => now()->subMinute(), 'certificate_status' => CertificateStatus::VALID, 'certificate_expiration_date' => now()->addDays(2)]);
    $this->makeMonitor('https://b.example.com', ['uptime_status' => UptimeStatus::DOWN, 'uptime_last_check_date' => now()->subMinute()]);
    $this->makeMonitor('https://c.example.com', ['uptime_status' => UptimeStatus::UP, 'certificate_status' => CertificateStatus::INVALID]);
    $this->makeMonitor('https://d.example.com', ['uptime_check_enabled' => false, 'certificate_check_enabled' => false]);

    livewire(MonitorStatusOverview::class)
        ->assertOk()
        ->assertSeeInOrder(['Up', '2', 'of 3 monitored'])
        ->assertSeeInOrder(['Down', '1', 'https://b.example.com'])
        ->assertSeeInOrder(['Certificate problems', '2'])
        ->assertSee('3 monitors on');
});

it('says when no check has run yet, and when the scheduler looks stopped', function (): void {
    $monitor = $this->makeMonitor('https://a.example.com');

    livewire(MonitorStatusOverview::class)->assertSee('Run the package\'s scheduler to start checks');

    $monitor->update(['uptime_status' => UptimeStatus::UP, 'uptime_last_check_date' => now()->subHours(2)]);

    livewire(MonitorStatusOverview::class)->assertSee('Is the scheduler running?');
});

it('is on the dashboard and registered with Livewire by name', function (): void {
    expect(Filament::getPanel('admin')->getWidgets())->toContain(MonitorStatusOverview::class)
        ->and(Livewire::new('amjadiqbal.filament-uptime-monitor.monitor-status-overview'))->toBeInstanceOf(MonitorStatusOverview::class);
});

it('can be left off the dashboard', function (): void {
    $plugin = UptimeMonitorPlugin::make()->dashboardWidget(false);

    expect($plugin->hasDashboardWidget())->toBeFalse();
});
