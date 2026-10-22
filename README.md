<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/amjadiqbal/filament-uptime-monitor/main/art/banner-dark.png">
    <img alt="Uptime Monitor: watch your sites and certificates from Filament" src="https://raw.githubusercontent.com/amjadiqbal/filament-uptime-monitor/main/art/banner-light.png" width="100%">
  </picture>
</p>

# Uptime Monitor for Filament

An admin UI for [spatie/laravel-uptime-monitor](https://github.com/spatie/laravel-uptime-monitor) in your Filament
panel. Add, edit, turn on and off and delete monitors, see uptime and SSL certificate status at a glance, and check a
site right now instead of waiting for the scheduler. It works with Filament 4 and 5.

[![Latest Version](https://img.shields.io/packagist/v/amjadiqbal/filament-uptime-monitor.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/filament-uptime-monitor)
[![PHP Version](https://img.shields.io/packagist/php-v/amjadiqbal/filament-uptime-monitor?style=flat-square)](https://packagist.org/packages/amjadiqbal/filament-uptime-monitor)
[![License](https://img.shields.io/packagist/l/amjadiqbal/filament-uptime-monitor.svg?style=flat-square)](LICENSE.md)

![The uptime monitors page in a Filament panel](https://raw.githubusercontent.com/amjadiqbal/filament-uptime-monitor/main/art/screenshots/list-light.png)

<p align="center">
  <img alt="The monitors page in dark mode" src="https://raw.githubusercontent.com/amjadiqbal/filament-uptime-monitor/main/art/screenshots/list-dark.png" width="80%">
</p>

## What it does

`spatie/laravel-uptime-monitor` checks your sites on a schedule and notifies you when one goes down, comes back, or
has a certificate problem. You manage it from the command line (`monitor:create`, `monitor:list`, ...). This plugin
adds the screen for it:

- **Monitors page**: every monitor with its uptime status (up, down, not checked yet, off) and how long it has been
  that way, the certificate status with its expiry date, and when it was last checked. Hover a down site or an invalid
  certificate to read the reason.
- **Filters**: uptime, certificate (valid, expires soon, invalid, not checked, off), on or off, and "needs attention".
- **Add and edit**: URL, uptime and certificate checks, interval, HTTP method, text to look for, request body and
  extra headers. Changing the URL resets the old status.
- **Check now**: run the uptime and certificate checks for one monitor, or for a selection, with the package's own code
  and config. You see the result straight away.
- **Turn on and off**, one at a time or in bulk, using the package's `enable()` and `disable()`.
- **Dashboard widget**: up, down, certificate problems, and the time of the last check, with a hint when the scheduler
  seems to have stopped.
- **Navigation badge** with the number of monitors that need attention.

It uses the package's model, table and config as they are. It adds no tables or migrations of its own and does not
change how the scheduled checks or notifications work.

## Requirements

- PHP 8.2+ (with Filament 5, tested on PHP 8.3+)
- Laravel 12 or 13
- Filament 4 or 5
- spatie/laravel-uptime-monitor 4.6+

## Installation

```bash
composer require amjadiqbal/filament-uptime-monitor
```

On Laravel 13, add `-W`. A new Laravel 13 app ships Guzzle 8, while spatie/laravel-uptime-monitor 4.7 needs Guzzle 7
(which Laravel 13 also supports), so Composer has to move Guzzle back:

```bash
composer require amjadiqbal/filament-uptime-monitor -W
```

If you haven't set up the uptime monitor package yet, publish its config and migration, then migrate:

```bash
php artisan vendor:publish --provider="Spatie\UptimeMonitor\UptimeMonitorServiceProvider"
php artisan migrate
```

Schedule its checks (in `routes/console.php`). The plugin does not schedule anything itself:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('monitor:check-uptime')->everyMinute();
Schedule::command('monitor:check-certificate')->daily();
```

Register the plugin in your panel provider:

```php
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(UptimeMonitorPlugin::make());
}
```

The plugin uses only Filament's own components, so there are no assets to build or publish.

## Configuration

Everything is optional:

```php
UptimeMonitorPlugin::make()
    ->navigationGroup('Monitoring')
    ->navigationSort(10)
    ->navigationLabel('Uptime')
    ->navigationBadge(false)                // hide the "needs attention" count
    ->dashboardWidget(false)                // leave the widget off the dashboard
    ->listWidget(false)                     // and off the monitors page
    ->maxChecksPerRequest(20)               // most monitors one bulk "Check now" may check (default 20)
    ->checksPerMinute(30)                   // hand-run checks per user per minute (default 30)
    ->responseCheckers([                    // let users pick a response checker per monitor
        \App\Uptime\JsonHealthChecker::class => 'JSON health endpoint',
    ])
    ->allowPrivateHosts()                   // see "Private addresses" below
    ->resource(\App\Filament\Resources\MyMonitorResource::class); // your own subclass
```

What comes from the package's `config/uptime-monitor.php`:

| Setting | Used for |
|---|---|
| `monitor_model` | The model the page lists and edits (your subclass works) |
| `uptime_check.run_interval_in_minutes` | The default interval for a new monitor, and the "is the scheduler running?" hint |
| `certificate_check.fire_expiring_soon_event_if_certificate_expires_within_days` | When a certificate shows "Expires soon" |
| `uptime_check.*`, `actions.send_uptime_check_request` | How "Check now" sends the request: timeout, headers, Guzzle options, user agent, your own request action |
| `notifications.*` | Unchanged: "Check now" fires the package's events, so its notifications go out as usual |

## Permissions

By default every user who can open the panel can see and change monitors. Two ways to restrict that:

**A policy** for the monitor model (`Spatie\UptimeMonitor\Models\Monitor`, or your `monitor_model`). The plugin uses
`viewAny`, `create`, `update`, `delete` and `deleteAny`. "Check now" uses a `checkNow` method when the policy has one,
and `update` when it doesn't. A missing method allows the action.

```php
Gate::policy(\Spatie\UptimeMonitor\Models\Monitor::class, \App\Policies\MonitorPolicy::class);
```

**A closure**, which replaces the policy:

```php
UptimeMonitorPlugin::make()
    ->authorizeUsing(fn (string $ability, ?Monitor $record, $user): bool => $user->is_admin);
```

`$ability` is one of `viewAny`, `view`, `create`, `update`, `delete`, `deleteAny` and `checkNow`. Bulk actions check
every selected record and report the ones they skipped.

Monitors are global: the package's table has no tenant column, so in a multi-tenant panel every tenant sees the same
monitors. Restrict the page with a policy or `authorizeUsing()` there.

## Private addresses

"Check now" makes your server send a request to the monitor's URL. So that a panel user can't use it to reach internal
services, the plugin refuses URLs on `localhost` or on private, loopback, link-local and other reserved addresses, both
when a monitor is saved and right before it is checked by hand. Checks started from the panel also refuse redirects to
such addresses.

If you do monitor internal services, allow them:

```php
UptimeMonitorPlugin::make()->allowPrivateHosts();
```

This guard covers the panel only. The package's scheduled `monitor:check-uptime` checks every stored monitor as it is.

## How it maps to the package

| Shown | Package state |
|---|---|
| Uptime: Up / Down / Not checked yet | `uptime_status` (`up`, `down`, `not yet checked`) |
| Uptime: Off | `uptime_check_enabled` is false |
| Certificate: Valid / Invalid / Not checked yet | `certificate_status` |
| Certificate: Expires soon | Valid, and `certificate_expiration_date` within the package's threshold |
| Certificate: Off | `certificate_check_enabled` is false |
| Needs attention | Uptime down, or certificate invalid or expiring soon |

## Translations

English is included. To translate or change the wording:

```bash
php artisan vendor:publish --tag=filament-uptime-monitor-translations
```

## Testing

```bash
composer test
```

The full matrix (PHP 8.2-8.5, Laravel 12 and 13, Filament 4 and 5; Filament 5 and Laravel 13 from PHP 8.3) runs locally with `scripts/ci-local.sh`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Support

- 🐛 **Bug or feature request**: [open an issue](https://github.com/amjadiqbal/filament-uptime-monitor/issues)
- 🔒 **Security issue**: please report it privately through GitHub's security tab

## Need this customised, or something built?

I'm available for custom development, package integration, and technical consulting.

[![Hire me on Upwork](https://img.shields.io/badge/Hire%20me-Upwork-6FDA44?style=flat-square&logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/amjadkhatri)

## More packages

This is part of a family of open-source packages. See [all of them](https://github.com/amjadiqbal?tab=repositories).

| | |
|---|---|
| [filament-deleted-records](https://github.com/amjadiqbal/filament-deleted-records) | A recycle bin for Filament, for spatie/laravel-deleted-models |
| [filament-change-requests](https://github.com/amjadiqbal/filament-change-requests) | Hold record edits in Filament for approval, with a field-by-field diff |
| [laravel-logpulse](https://github.com/amjadiqbal/laravel-logpulse) | Log health monitoring and alerting for Laravel |

## Credits

- [Amjad Iqbal](https://github.com/amjadiqbal)
- Monitoring, checks and notifications: [spatie/laravel-uptime-monitor](https://github.com/spatie/laravel-uptime-monitor)
  by Spatie (MIT). This plugin is not affiliated with or endorsed by Spatie.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
