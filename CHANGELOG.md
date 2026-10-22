# Changelog

All notable changes to `filament-uptime-monitor` are documented in this file, in [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

## [Unreleased]

## [1.0.1] - 2026-10-23

### Security

- "Check now" resolves the monitor's host once, refuses it when it resolves to a private address, and pins the request
  to the checked address (curl `CURLOPT_RESOLVE`), so DNS can't switch to a private address between the check and the
  connection. A host that no longer resolves is marked down instead of being requested.

### Fixed

- Requires spatie/laravel-uptime-monitor ^4.7: the redirect guard uses its request action, which first shipped in 4.7.0.
- The "Is the scheduler running?" hint uses the shortest interval of the monitors that are on, so monitors with long
  intervals no longer trigger it falsely.
- README: exact bulk-check and rate-limit behaviour, what the address guard covers, the Guzzle 7 requirement.
- Homepage and docs now point to https://amjad.com.pk/filament/plugins/filament-uptime-monitor.

## [1.0.0] - 2026-10-23

### Added

- Monitors resource for spatie/laravel-uptime-monitor: list, add, edit and delete monitors, with uptime and certificate
  status columns, filters (uptime, certificate, on/off, needs attention) and URL search.
- "Check now" for one monitor or a selection, using the package's own checks and config, with a per-user rate limit and
  a cap per bulk action.
- Turn monitors on and off, one at a time or in bulk.
- Dashboard status widget (up, down, certificate problems, last check and a scheduler hint).
- Navigation badge with the number of monitors that need attention.
- Authorization through a policy on the monitor model or an `authorizeUsing()` closure, with a `checkNow` ability.
- Private and local addresses are refused from the panel unless `allowPrivateHosts()` is set, including redirects.
- "Check now" saves the result even when a notification channel fails, and shows the error.
- Filament 4 and 5, Laravel 12 and 13, PHP 8.2 to 8.5 (Filament 5 and Laravel 13 from PHP 8.3).

[Unreleased]: https://github.com/amjadiqbal/filament-uptime-monitor/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/amjadiqbal/filament-uptime-monitor/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/amjadiqbal/filament-uptime-monitor/releases/tag/v1.0.0
