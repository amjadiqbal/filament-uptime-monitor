<?php

declare(strict_types=1);

use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\EditMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\ListMonitors;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\CustomMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\FakeUptimeRequestAction;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitorPlugin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs($this->makeUser());

    $this->site = $this->makeMonitor('https://shop.example.com');
    $this->broken = $this->makeMonitor('http://api.example.com');
    $this->off = $this->makeMonitor('https://old.example.com', ['uptime_check_enabled' => false, 'certificate_check_enabled' => false]);

    FakeUptimeRequestAction::$failures = ['http://api.example.com' => 'cURL error 7: Failed to connect'];
    CustomMonitor::$certificates = ['shop.example.com' => 120];
});

function actionsPlugin(): UptimeMonitorPlugin
{
    /** @var UptimeMonitorPlugin */
    return Filament::getPanel('admin')->getPlugin(UptimeMonitorPlugin::ID);
}

it('checks uptime and the certificate now, with the package code', function (): void {
    livewire(ListMonitors::class)
        ->callTableAction('checkMonitor', $this->site)
        ->assertNotified('https://shop.example.com is up');

    $site = $this->site->fresh();

    expect($site->uptime_status)->toBe(UptimeStatus::UP)
        ->and($site->uptime_last_check_date)->not->toBeNull()
        ->and($site->certificate_status)->toBe(CertificateStatus::VALID)
        ->and($site->certificate_issuer)->toBe('Test CA')
        ->and(FakeUptimeRequestAction::$requested)->toBe(['https://shop.example.com']);
});

it('reports a failing check with its reason', function (): void {
    livewire(ListMonitors::class)
        ->callTableAction('checkMonitor', $this->broken)
        ->assertNotified(Notification::make()->danger()->title('http://api.example.com is down')->body('cURL error 7: Failed to connect'));

    expect($this->broken->fresh())
        ->uptime_status->toBe(UptimeStatus::DOWN)
        ->uptime_check_times_failed_in_a_row->toBe(1);
});

it('reports an invalid certificate', function (): void {
    CustomMonitor::$certificates = [];

    livewire(ListMonitors::class)->callTableAction('checkMonitor', $this->site);

    expect($this->site->fresh()->certificate_status)->toBe(CertificateStatus::INVALID);
});

it('hides check now for a monitor that is off', function (): void {
    livewire(ListMonitors::class)->assertTableActionHidden('checkMonitor', $this->off);
});

it('never requests a private host, even if one is already stored', function (): void {
    $internal = $this->makeMonitor('http://10.0.0.5');

    livewire(ListMonitors::class)
        ->callTableAction('checkMonitor', $internal)
        ->assertNotified();

    expect(FakeUptimeRequestAction::$requested)->toBe([])
        ->and($internal->fresh()->uptime_status)->toBe(UptimeStatus::NOT_YET_CHECKED);
});

it('checks the selected monitors in bulk and summarises', function (): void {
    livewire(ListMonitors::class)
        ->callTableBulkAction('checkMonitors', [$this->site, $this->broken, $this->off])
        ->assertNotified(Notification::make()->danger()->title('Checked 2 monitors')
            ->body('1 up, 1 down. 1 monitor is off and was not checked.'));

    expect(FakeUptimeRequestAction::$requested)->toEqualCanonicalizing(['https://shop.example.com', 'http://api.example.com']);
});

it('caps a bulk check at the plugin maximum', function (): void {
    actionsPlugin()->maxChecksPerRequest(1);

    livewire(ListMonitors::class)
        ->callTableBulkAction('checkMonitors', [$this->site, $this->broken])
        ->assertNotified('Select at most 1 monitors to check at once.');

    expect(FakeUptimeRequestAction::$requested)->toBe([]);
});

it('rate-limits checks per user', function (): void {
    actionsPlugin()->checksPerMinute(2);

    $page = livewire(ListMonitors::class);
    $page->callTableAction('checkMonitor', $this->site);
    $page->callTableAction('checkMonitor', $this->site);
    $page->callTableAction('checkMonitor', $this->site)->assertNotified('Too many checks. Try again in a minute.');

    expect(FakeUptimeRequestAction::$requested)->toHaveCount(2);
});

it('turns a monitor off and on with the package methods', function (): void {
    livewire(ListMonitors::class)->callTableAction('disableMonitor', $this->site);

    expect($this->site->fresh())
        ->uptime_check_enabled->toBeFalse()
        ->certificate_check_enabled->toBeFalse();

    livewire(ListMonitors::class)->callTableAction('enableMonitor', $this->site);

    // Monitor::enable() turns the certificate check on for https URLs.
    expect($this->site->fresh())
        ->uptime_check_enabled->toBeTrue()
        ->certificate_check_enabled->toBeTrue();
});

it('turns monitors off and on in bulk', function (): void {
    livewire(ListMonitors::class)
        ->callTableBulkAction('disableMonitors', [$this->site, $this->broken])
        ->assertNotified('Turned off 2 monitors');

    expect(UptimeMonitor::scopeEnabled(UptimeMonitor::query(), false)->count())->toBe(3);

    livewire(ListMonitors::class)
        ->callTableBulkAction('enableMonitors', [$this->site, $this->broken, $this->off])
        ->assertNotified('Turned on 3 monitors');

    expect(UptimeMonitor::scopeEnabled(UptimeMonitor::query())->count())->toBe(3)
        ->and($this->broken->fresh()->certificate_check_enabled)->toBeFalse();
});

it('checks from the edit page', function (): void {
    livewire(EditMonitor::class, ['record' => $this->site->getKey()])
        ->callAction('checkMonitor')
        ->assertNotified('https://shop.example.com is up');
});

it('respects a custom request action from the package config', function (): void {
    // FakeUptimeRequestAction is configured in TestCase; the plugin used it instead of its guarded default.
    livewire(ListMonitors::class)->callTableAction('checkMonitor', $this->site);

    expect(config('uptime-monitor.actions.send_uptime_check_request'))->toBe(FakeUptimeRequestAction::class)
        ->and(FakeUptimeRequestAction::$requested)->toHaveCount(1);
});
