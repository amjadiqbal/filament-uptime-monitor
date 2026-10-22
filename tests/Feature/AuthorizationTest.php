<?php

declare(strict_types=1);

use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\CreateMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\ListMonitors;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\CustomMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\FakeUptimeRequestAction;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\MonitorPolicy;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\MonitorPolicyWithCheckNow;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitorPlugin;
use AmjadIqbal\FilamentUptimeMonitor\Widgets\MonitorStatusOverview;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    MonitorPolicy::$canUpdate = true;
    MonitorPolicy::$canCheckNow = null;

    $this->actingAs($this->makeUser());
    $this->site = $this->makeMonitor('https://shop.example.com');
});

function authPlugin(): UptimeMonitorPlugin
{
    /** @var UptimeMonitorPlugin */
    return Filament::getPanel('admin')->getPlugin(UptimeMonitorPlugin::ID);
}

it('allows everything when there is no policy or closure', function (): void {
    expect(MonitorResource::canViewAny())->toBeTrue()
        ->and(MonitorResource::canCreate())->toBeTrue()
        ->and(MonitorResource::canEdit($this->site))->toBeTrue()
        ->and(MonitorResource::canDelete($this->site))->toBeTrue()
        ->and(UptimeMonitor::can('checkNow', $this->site))->toBeTrue();
});

it('follows a policy on the monitor model', function (): void {
    Gate::policy(CustomMonitor::class, MonitorPolicy::class);

    expect(MonitorResource::canViewAny())->toBeTrue()
        ->and(MonitorResource::canCreate())->toBeFalse()
        ->and(MonitorResource::canDelete($this->site))->toBeFalse()
        ->and(MonitorResource::canDeleteAny())->toBeFalse();

    livewire(CreateMonitor::class)->assertForbidden();
    livewire(ListMonitors::class)->assertTableActionHidden('delete', $this->site);
});

it('falls back to the policy update for check now', function (): void {
    Gate::policy(CustomMonitor::class, MonitorPolicy::class);
    MonitorPolicy::$canUpdate = false;

    livewire(ListMonitors::class)
        ->assertTableActionHidden('checkMonitor', $this->site)
        ->assertTableActionHidden('disableMonitor', $this->site);
});

it('uses a checkNow policy method when there is one', function (): void {
    Gate::policy(CustomMonitor::class, MonitorPolicyWithCheckNow::class);
    MonitorPolicy::$canUpdate = true;
    MonitorPolicy::$canCheckNow = false;

    livewire(ListMonitors::class)
        ->assertTableActionHidden('checkMonitor', $this->site)
        ->assertTableActionVisible('disableMonitor', $this->site);
});

it('lets authorizeUsing() replace the policy', function (): void {
    Gate::policy(CustomMonitor::class, MonitorPolicy::class);
    $seen = [];

    authPlugin()->authorizeUsing(function (string $ability, $record, $user) use (&$seen): bool {
        $seen[] = $ability;

        return $ability !== 'viewAny';
    });

    expect(MonitorResource::canViewAny())->toBeFalse()
        ->and(MonitorResource::canCreate())->toBeTrue()
        ->and(MonitorStatusOverview::canView())->toBeFalse()
        ->and($seen)->toContain('viewAny', 'create');

    livewire(ListMonitors::class)->assertForbidden();
});

it('skips records the user may not check in a bulk check', function (): void {
    $other = $this->makeMonitor('https://other.example.com');

    authPlugin()->authorizeUsing(fn (string $ability, $record): bool => ! ($ability === 'checkNow' && $record?->is($other)));

    livewire(ListMonitors::class)
        ->callTableBulkAction('checkMonitors', [$this->site, $other])
        ->assertNotified();

    expect(FakeUptimeRequestAction::$requested)->toBe(['https://shop.example.com']);
});
