<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Tests;

use AmjadIqbal\FilamentUptimeMonitor\FilamentUptimeMonitorServiceProvider;
use AmjadIqbal\FilamentUptimeMonitor\Support\HostGuard;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\AdminPanelProvider;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\CustomMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\FakeUptimeRequestAction;
use AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures\User;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Spatie\UptimeMonitor\UptimeMonitorServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeUptimeRequestAction::$failures = [];
        FakeUptimeRequestAction::$requested = [];
        CustomMonitor::$certificates = [];
        RateLimiter::clear('filament-uptime-monitor:check:1');
        // Tests never touch real DNS: hosts resolve to a public address, intranet.* to a private one.
        HostGuard::resolveUsing(fn (string $host): array => match (true) {
            str_starts_with($host, 'intranet.') => ['10.1.2.3'],
            default => ['93.184.215.14'],
        });
    }

    protected function tearDown(): void
    {
        HostGuard::resolveUsing(null);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            PowerJoinsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            UptimeMonitorServiceProvider::class,
            FilamentUptimeMonitorServiceProvider::class,
            AdminPanelProvider::class,
        ];

        return array_values(array_filter($providers, 'class_exists'));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('uptime-monitor.monitor_model', CustomMonitor::class);
        $app['config']->set('uptime-monitor.actions.send_uptime_check_request', FakeUptimeRequestAction::class);
        $app['config']->set('uptime-monitor.notifications.notifications', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $stub = __DIR__.'/../vendor/spatie/laravel-uptime-monitor/database/migrations/create_monitors_table.php.stub';

        if (! class_exists('CreateMonitorsTable')) {
            require $stub;
        }

        (new \CreateMonitorsTable)->up();
    }

    protected function makeUser(string $name = 'Admin'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'password' => 'secret-password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeMonitor(string $url, array $attributes = []): CustomMonitor
    {
        return CustomMonitor::query()->create([
            'url' => $url,
            'uptime_check_enabled' => true,
            'certificate_check_enabled' => str_starts_with($url, 'https://'),
            'uptime_status' => UptimeStatus::NOT_YET_CHECKED,
            'certificate_status' => CertificateStatus::NOT_YET_CHECKED,
            'uptime_check_interval_in_minutes' => 5,
            ...$attributes,
        ]);
    }
}
