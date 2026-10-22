<?php

declare(strict_types=1);

use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\CreateMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\EditMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\ListMonitors;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\CustomMonitor;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitorPlugin;
use Filament\Facades\Filament;
use Spatie\UptimeMonitor\Helpers\UptimeResponseCheckers\LookForStringChecker;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs($this->makeUser());

    $this->up = $this->makeMonitor('https://shop.example.com', [
        'uptime_status' => UptimeStatus::UP,
        'uptime_last_check_date' => now()->subMinute(),
        'certificate_status' => CertificateStatus::VALID,
        'certificate_expiration_date' => now()->addDays(90),
        'certificate_issuer' => 'Test CA',
    ]);
    $this->down = $this->makeMonitor('https://api.example.com', [
        'uptime_status' => UptimeStatus::DOWN,
        'uptime_check_failure_reason' => 'cURL error 28: Connection timed out',
        'certificate_status' => CertificateStatus::VALID,
        'certificate_expiration_date' => now()->addDays(3),
    ]);
    $this->unchecked = $this->makeMonitor('http://blog.example.com');
    $this->off = $this->makeMonitor('https://old.example.com', [
        'uptime_check_enabled' => false,
        'certificate_check_enabled' => false,
    ]);
    $this->badCert = $this->makeMonitor('https://legacy.example.com', [
        'uptime_status' => UptimeStatus::UP,
        'certificate_status' => CertificateStatus::INVALID,
        'certificate_check_failure_reason' => 'The certificate has expired',
    ]);
});

function uptimePlugin(): UptimeMonitorPlugin
{
    /** @var UptimeMonitorPlugin */
    return Filament::getPanel('admin')->getPlugin(UptimeMonitorPlugin::ID);
}

it('uses the monitor model from the package config', function (): void {
    expect(MonitorResource::getModel())->toBe(CustomMonitor::class);
});

it('lists monitors with uptime and certificate states', function (): void {
    livewire(ListMonitors::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->up, $this->down, $this->unchecked, $this->off, $this->badCert])
        ->assertTableColumnStateSet('url', 'https://shop.example.com', $this->up)
        ->assertTableColumnStateSet('uptime_status', UptimeMonitor::UPTIME_UP, $this->up)
        ->assertTableColumnStateSet('uptime_status', UptimeMonitor::UPTIME_DOWN, $this->down)
        ->assertTableColumnStateSet('uptime_status', UptimeMonitor::UPTIME_UNCHECKED, $this->unchecked)
        ->assertTableColumnStateSet('uptime_status', UptimeMonitor::UPTIME_DISABLED, $this->off)
        ->assertTableColumnStateSet('certificate_status', UptimeMonitor::CERTIFICATE_VALID, $this->up)
        ->assertTableColumnStateSet('certificate_status', UptimeMonitor::CERTIFICATE_EXPIRING, $this->down)
        ->assertTableColumnStateSet('certificate_status', UptimeMonitor::CERTIFICATE_OFF, $this->unchecked)
        ->assertTableColumnStateSet('certificate_status', UptimeMonitor::CERTIFICATE_INVALID, $this->badCert)
        ->assertTableColumnFormattedStateSet('uptime_status', 'Down', $this->down)
        ->assertTableColumnFormattedStateSet('certificate_status', 'Expires soon', $this->down)
        ->assertSee('cURL error 28: Connection timed out');
});

it('uses the package threshold for "expires soon"', function (): void {
    config(['uptime-monitor.certificate_check.fire_expiring_soon_event_if_certificate_expires_within_days' => 2]);

    expect(UptimeMonitor::certificateState($this->down->fresh()))->toBe(UptimeMonitor::CERTIFICATE_VALID);
});

it('searches by URL', function (): void {
    livewire(ListMonitors::class)
        ->searchTable('legacy')
        ->assertCanSeeTableRecords([$this->badCert])
        ->assertCanNotSeeTableRecords([$this->up, $this->down]);
});

it('filters by uptime, certificate, on/off and problems', function (): void {
    livewire(ListMonitors::class)
        ->filterTable('uptime', UptimeMonitor::UPTIME_DOWN)
        ->assertCanSeeTableRecords([$this->down])
        ->assertCanNotSeeTableRecords([$this->up, $this->unchecked, $this->off, $this->badCert]);

    livewire(ListMonitors::class)
        ->filterTable('uptime', UptimeMonitor::UPTIME_UNCHECKED)
        ->assertCanSeeTableRecords([$this->unchecked])
        ->assertCanNotSeeTableRecords([$this->up, $this->down, $this->off]);

    livewire(ListMonitors::class)
        ->filterTable('certificate', UptimeMonitor::CERTIFICATE_EXPIRING)
        ->assertCanSeeTableRecords([$this->down])
        ->assertCanNotSeeTableRecords([$this->up, $this->badCert]);

    livewire(ListMonitors::class)
        ->filterTable('certificate', UptimeMonitor::CERTIFICATE_VALID)
        ->assertCanSeeTableRecords([$this->up])
        ->assertCanNotSeeTableRecords([$this->down, $this->badCert]);

    livewire(ListMonitors::class)
        ->filterTable('enabled', false)
        ->assertCanSeeTableRecords([$this->off])
        ->assertCanNotSeeTableRecords([$this->up, $this->down]);

    livewire(ListMonitors::class)
        ->filterTable('problems')
        ->assertCanSeeTableRecords([$this->down, $this->badCert])
        ->assertCanNotSeeTableRecords([$this->up, $this->unchecked, $this->off]);
});

it('shows the number of monitors with a problem as a gray navigation badge', function (): void {
    expect(MonitorResource::getNavigationBadge())->toBe('2')
        ->and(MonitorResource::getNavigationBadgeColor())->toBe('gray');

    uptimePlugin()->navigationBadge(false);

    expect(MonitorResource::getNavigationBadge())->toBeNull();
});

it('creates a monitor with the package defaults', function (): void {
    livewire(CreateMonitor::class)
        ->fillForm(['url' => ' https://new.example.com/ '])
        ->assertSchemaStateSet(['uptime_check_interval_in_minutes' => 5, 'uptime_check_method' => 'get', 'uptime_check_enabled' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = CustomMonitor::query()->where('url', 'https://new.example.com')->sole();

    expect($monitor->uptime_check_enabled)->toBeTrue()
        ->and($monitor->certificate_check_enabled)->toBeTrue()
        ->and($monitor->uptime_status)->toBe(UptimeStatus::NOT_YET_CHECKED)
        ->and($monitor->look_for_string)->toBe('')
        ->and($monitor->uptime_check_additional_headers)->toBe([]);
});

it('takes the default interval from the package config', function (): void {
    config(['uptime-monitor.uptime_check.run_interval_in_minutes' => 15]);

    livewire(CreateMonitor::class)->assertSchemaStateSet(['uptime_check_interval_in_minutes' => 15]);
});

it('stores the method, text to look for, body and headers', function (): void {
    livewire(CreateMonitor::class)
        ->fillForm([
            'url' => 'https://hooks.example.com/health',
            'uptime_check_method' => 'post',
            'look_for_string' => 'ok',
            'uptime_check_payload' => '{"ping":true}',
            'uptime_check_additional_headers' => ['X-Token' => 'abc'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $monitor = CustomMonitor::query()->where('url', 'https://hooks.example.com/health')->sole();

    expect($monitor->uptime_check_method)->toBe('post')
        ->and($monitor->look_for_string)->toBe('ok')
        ->and($monitor->uptime_check_payload)->toBe('{"ping":true}')
        ->and($monitor->uptime_check_additional_headers)->toBe(['X-Token' => 'abc']);
});

it('rejects invalid, duplicate, private and credential URLs', function (string $url, string $message): void {
    livewire(CreateMonitor::class)
        ->fillForm(['url' => $url, 'certificate_check_enabled' => false])
        ->call('create')
        ->assertHasFormErrors(['url'])
        ->assertSee($message);
})->with([
    'no scheme' => ['example.org', 'Use a full http:// or https:// URL.'],
    'ftp' => ['ftp://example.org', 'Use a full http:// or https:// URL.'],
    'duplicate' => ['https://shop.example.com/', 'This URL is already monitored.'],
    'localhost' => ['http://localhost:8000', 'private or local address'],
    'loopback ip' => ['http://127.0.0.1', 'private or local address'],
    'private ip' => ['http://192.168.1.10', 'private or local address'],
    'metadata ip' => ['http://169.254.169.254/latest', 'private or local address'],
    'ipv6 loopback' => ['http://[::1]/', 'private or local address'],
    'resolves private' => ['https://intranet.example.com', 'private or local address'],
    'credentials' => ['https://user:pass@example.org', 'Leave the user name and password out of the URL.'],
]);

it('allows private hosts when the plugin says so', function (): void {
    uptimePlugin()->allowPrivateHosts();

    livewire(CreateMonitor::class)
        ->fillForm(['url' => 'http://127.0.0.1:8000', 'certificate_check_enabled' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CustomMonitor::query()->where('url', 'http://127.0.0.1:8000')->exists())->toBeTrue();
});

it('only checks certificates of https URLs', function (): void {
    livewire(CreateMonitor::class)
        ->fillForm(['url' => 'http://plain.example.com', 'certificate_check_enabled' => true])
        ->call('create')
        ->assertHasFormErrors(['certificate_check_enabled']);
});

it('refuses to look for text in a HEAD response', function (): void {
    livewire(CreateMonitor::class)
        ->fillForm(['url' => 'https://head.example.com', 'uptime_check_method' => 'head', 'look_for_string' => 'ok'])
        ->call('create')
        ->assertHasFormErrors(['look_for_string']);
});

it('offers only the response checkers the plugin lists', function (): void {
    livewire(CreateMonitor::class)->assertFormFieldHidden('uptime_check_response_checker');

    uptimePlugin()->responseCheckers([LookForStringChecker::class => 'Look for text']);

    livewire(CreateMonitor::class)
        ->assertFormFieldVisible('uptime_check_response_checker')
        ->fillForm(['url' => 'https://checker.example.com', 'uptime_check_response_checker' => 'App\\Evil'])
        ->call('create')
        ->assertHasFormErrors(['uptime_check_response_checker']);
});

it('edits a monitor and resets its status when the URL changes', function (): void {
    livewire(EditMonitor::class, ['record' => $this->down->getKey()])
        ->assertSchemaStateSet(['url' => 'https://api.example.com'])
        ->fillForm(['url' => 'https://api2.example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    $monitor = $this->down->fresh();

    expect($monitor->raw_url)->toBe('https://api2.example.com')
        ->and($monitor->uptime_status)->toBe(UptimeStatus::NOT_YET_CHECKED)
        ->and($monitor->uptime_check_failure_reason)->toBeNull()
        ->and($monitor->certificate_status)->toBe(CertificateStatus::NOT_YET_CHECKED)
        ->and($monitor->certificate_expiration_date)->toBeNull();
});

it('keeps the status when only settings change', function (): void {
    livewire(EditMonitor::class, ['record' => $this->up->getKey()])
        ->fillForm(['uptime_check_interval_in_minutes' => 10])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->up->fresh())
        ->uptime_status->toBe(UptimeStatus::UP)
        ->uptime_check_interval_in_minutes->toBe('10');
});

it('deletes a monitor', function (): void {
    livewire(ListMonitors::class)
        ->callTableAction('delete', $this->off);

    expect(CustomMonitor::query()->whereKey($this->off->getKey())->exists())->toBeFalse();
});
