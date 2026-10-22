<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor;

use AmjadIqbal\FilamentUptimeMonitor\Support\GuardedUptimeCheckRequestAction;
use AmjadIqbal\FilamentUptimeMonitor\Support\HostGuard;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use ReflectionMethod;
use Spatie\UptimeMonitor\Actions\SendUptimeCheckRequestAction;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Spatie\UptimeMonitor\Models\Monitor;
use Spatie\UptimeMonitor\MonitorCollection;
use Throwable;

class UptimeMonitor
{
    public const UPTIME_UP = 'up';

    public const UPTIME_DOWN = 'down';

    public const UPTIME_UNCHECKED = 'unchecked';

    public const UPTIME_DISABLED = 'disabled';

    public const CERTIFICATE_VALID = 'valid';

    public const CERTIFICATE_EXPIRING = 'expiring';

    public const CERTIFICATE_INVALID = 'invalid';

    public const CERTIFICATE_UNCHECKED = 'unchecked';

    public const CERTIFICATE_OFF = 'off';

    public const METHODS = ['get', 'head', 'post', 'put', 'patch', 'delete', 'options'];

    public static function plugin(): UptimeMonitorPlugin
    {
        return UptimeMonitorPlugin::get();
    }

    /**
     * The monitor model from config('uptime-monitor.monitor_model'), the same one the package's commands use.
     *
     * @return class-string<Monitor>
     */
    public static function monitorModel(): string
    {
        $model = config('uptime-monitor.monitor_model') ?? Monitor::class;

        if (! is_string($model) || ! is_a($model, Monitor::class, true)) {
            throw new InvalidArgumentException('config uptime-monitor.monitor_model must extend '.Monitor::class.'.');
        }

        return $model;
    }

    /**
     * @return Builder<Monitor>
     */
    public static function query(): Builder
    {
        return static::monitorModel()::query();
    }

    public static function user(): ?Authenticatable
    {
        try {
            return Filament::auth()->user();
        } catch (Throwable) {
            return auth()->user();
        }
    }

    /**
     * Order: the plugin's authorizeUsing() closure, then a policy for the monitor model (a custom ability such as
     * checkNow falls back to the policy's update), then allow.
     */
    public static function can(string $ability, ?Monitor $record = null): bool
    {
        $user = static::user();

        if ($callback = static::plugin()->getAuthorizeUsing()) {
            return (bool) app()->call($callback, ['ability' => $ability, 'record' => $record, 'user' => $user]);
        }

        $policy = Gate::getPolicyFor(static::monitorModel());

        if (! $policy) {
            return true;
        }

        if (! method_exists($policy, $ability) && $ability === 'checkNow') {
            $ability = 'update';
        }

        if (! method_exists($policy, $ability)) {
            return true;
        }

        // A bulk action asks for a per-record ability without a record; it checks each record itself afterwards.
        if (! $record && (new ReflectionMethod($policy, $ability))->getNumberOfRequiredParameters() > 1) {
            return true;
        }

        $gate = $user ? Gate::forUser($user) : Gate::getFacadeRoot();

        return $gate->allows($ability, $record ?? static::monitorModel());
    }

    /**
     * The URL as the package stores it: trimmed, without a trailing slash (as `monitor:create` does).
     */
    public static function normalizeUrl(string $url): string
    {
        return rtrim(trim($url), '/');
    }

    /**
     * Why this URL may not be monitored from the panel, or null when it may.
     */
    public static function urlProblem(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || blank($parts['host'] ?? null)) {
            return __('filament-uptime-monitor::uptime-monitor.validation.scheme');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return __('filament-uptime-monitor::uptime-monitor.validation.credentials');
        }

        if (! static::plugin()->allowsPrivateHosts() && HostGuard::isPrivate((string) $parts['host'])) {
            return __('filament-uptime-monitor::uptime-monitor.validation.private_host');
        }

        return null;
    }

    public static function uptimeState(Monitor $monitor): string
    {
        if (! $monitor->uptime_check_enabled) {
            return static::UPTIME_DISABLED;
        }

        return match ($monitor->uptime_status) {
            UptimeStatus::UP => static::UPTIME_UP,
            UptimeStatus::DOWN => static::UPTIME_DOWN,
            default => static::UPTIME_UNCHECKED,
        };
    }

    public static function certificateState(Monitor $monitor): string
    {
        if (! $monitor->certificate_check_enabled) {
            return static::CERTIFICATE_OFF;
        }

        return match ($monitor->certificate_status) {
            CertificateStatus::VALID => static::isExpiringSoon($monitor) ? static::CERTIFICATE_EXPIRING : static::CERTIFICATE_VALID,
            CertificateStatus::INVALID => static::CERTIFICATE_INVALID,
            default => static::CERTIFICATE_UNCHECKED,
        };
    }

    /**
     * The package's own threshold for its "expires soon" event.
     */
    public static function expiringWithinDays(): int
    {
        return (int) config('uptime-monitor.certificate_check.fire_expiring_soon_event_if_certificate_expires_within_days', 10);
    }

    public static function isExpiringSoon(Monitor $monitor): bool
    {
        $date = $monitor->certificate_expiration_date;

        return $date instanceof Carbon && $date->lte(now()->addDays(static::expiringWithinDays()));
    }

    /**
     * @param  Builder<Monitor>  $query
     * @return Builder<Monitor>
     */
    public static function scopeUptime(Builder $query, string $state): Builder
    {
        return match ($state) {
            static::UPTIME_DISABLED => $query->where('uptime_check_enabled', false),
            static::UPTIME_UP => $query->where('uptime_check_enabled', true)->where('uptime_status', UptimeStatus::UP),
            static::UPTIME_DOWN => $query->where('uptime_check_enabled', true)->where('uptime_status', UptimeStatus::DOWN),
            static::UPTIME_UNCHECKED => $query->where('uptime_check_enabled', true)
                ->whereNotIn('uptime_status', [UptimeStatus::UP, UptimeStatus::DOWN]),
            default => $query,
        };
    }

    /**
     * @param  Builder<Monitor>  $query
     * @return Builder<Monitor>
     */
    public static function scopeCertificate(Builder $query, string $state): Builder
    {
        $soon = now()->addDays(static::expiringWithinDays());

        return match ($state) {
            static::CERTIFICATE_OFF => $query->where('certificate_check_enabled', false),
            static::CERTIFICATE_VALID => $query->where('certificate_check_enabled', true)
                ->where('certificate_status', CertificateStatus::VALID)
                ->where(fn (Builder $q) => $q->whereNull('certificate_expiration_date')->orWhere('certificate_expiration_date', '>', $soon)),
            static::CERTIFICATE_EXPIRING => $query->where('certificate_check_enabled', true)
                ->where('certificate_status', CertificateStatus::VALID)
                ->where('certificate_expiration_date', '<=', $soon),
            static::CERTIFICATE_INVALID => $query->where('certificate_check_enabled', true)
                ->where('certificate_status', CertificateStatus::INVALID),
            static::CERTIFICATE_UNCHECKED => $query->where('certificate_check_enabled', true)
                ->whereNotIn('certificate_status', [CertificateStatus::VALID, CertificateStatus::INVALID]),
            default => $query,
        };
    }

    /**
     * Monitors that need attention: uptime down, or certificate invalid or expiring soon.
     *
     * @param  Builder<Monitor>  $query
     * @return Builder<Monitor>
     */
    public static function scopeProblems(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->orWhere(fn (Builder $q) => static::scopeUptime($q, static::UPTIME_DOWN))
            ->orWhere(fn (Builder $q) => static::scopeCertificate($q, static::CERTIFICATE_INVALID))
            ->orWhere(fn (Builder $q) => static::scopeCertificate($q, static::CERTIFICATE_EXPIRING)));
    }

    /**
     * @param  Builder<Monitor>  $query
     * @return Builder<Monitor>
     */
    public static function scopeEnabled(Builder $query, bool $enabled = true): Builder
    {
        return $enabled
            ? $query->where(fn (Builder $q) => $q->where('uptime_check_enabled', true)->orWhere('certificate_check_enabled', true))
            : $query->where('uptime_check_enabled', false)->where('certificate_check_enabled', false);
    }

    public static function isEnabled(Monitor $monitor): bool
    {
        return $monitor->uptime_check_enabled || $monitor->certificate_check_enabled;
    }

    /**
     * Count a hand-run check against the user's per-minute limit. False when the limit is reached.
     */
    public static function consumeCheckAllowance(int $monitors): bool
    {
        $key = 'filament-uptime-monitor:check:'.(static::user()?->getAuthIdentifier() ?? request()->ip());
        $limit = static::plugin()->getChecksPerMinute();

        if (RateLimiter::attempts($key) + $monitors > $limit) {
            return false;
        }

        RateLimiter::increment($key, 60, $monitors);

        return true;
    }

    /**
     * Run the uptime and certificate checks for these monitors now, with the package's own code and config. Monitors
     * whose host is not allowed (see allowPrivateHosts) are skipped and returned as such.
     *
     * @param  iterable<Monitor>  $monitors
     * @return array{checked: Collection<int, Monitor>, blocked: Collection<int, Monitor>}
     */
    public static function check(iterable $monitors): array
    {
        $monitors = collect($monitors)->filter(fn (Monitor $monitor): bool => static::isEnabled($monitor))->values();

        [$allowed, $blocked] = $monitors->partition(fn (Monitor $monitor): bool => static::urlProblem($monitor->raw_url) === null);

        $uptime = $allowed->filter(fn (Monitor $monitor): bool => (bool) $monitor->uptime_check_enabled)->values();

        if ($uptime->isNotEmpty()) {
            static::withGuardedRequests(fn () => MonitorCollection::make($uptime->all())->checkUptime());
        }

        $allowed
            ->filter(fn (Monitor $monitor): bool => (bool) $monitor->certificate_check_enabled)
            ->each(fn (Monitor $monitor) => $monitor->checkCertificate());

        return [
            'checked' => $allowed->map(fn (Monitor $monitor): Monitor => $monitor->refresh())->values(),
            'blocked' => $blocked->values(),
        ];
    }

    /**
     * While private hosts are blocked, swap the package's default request action for one that also refuses redirects
     * to private hosts. A custom action set in config is left alone.
     */
    protected static function withGuardedRequests(callable $callback): void
    {
        $key = 'uptime-monitor.actions.send_uptime_check_request';
        $configured = config($key, SendUptimeCheckRequestAction::class);

        if (static::plugin()->allowsPrivateHosts() || $configured !== SendUptimeCheckRequestAction::class) {
            $callback();

            return;
        }

        config([$key => GuardedUptimeCheckRequestAction::class]);

        try {
            $callback();
        } finally {
            config([$key => $configured]);
        }
    }

    /**
     * @return array{total: int, enabled: int, up: int, down: int, unchecked: int, disabled: int, certificate_problems: int, last_check: ?Carbon}
     */
    public static function summary(): array
    {
        $count = fn (callable $scope): int => $scope(static::query())->count();
        $last = static::query()->max('uptime_last_check_date');

        return [
            'total' => static::query()->count(),
            'enabled' => $count(fn (Builder $q) => static::scopeEnabled($q)),
            'up' => $count(fn (Builder $q) => static::scopeUptime($q, static::UPTIME_UP)),
            'down' => $count(fn (Builder $q) => static::scopeUptime($q, static::UPTIME_DOWN)),
            'unchecked' => $count(fn (Builder $q) => static::scopeUptime($q, static::UPTIME_UNCHECKED)),
            'disabled' => $count(fn (Builder $q) => static::scopeEnabled($q, false)),
            'certificate_problems' => $count(fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->orWhere(fn (Builder $q) => static::scopeCertificate($q, static::CERTIFICATE_INVALID))
                ->orWhere(fn (Builder $q) => static::scopeCertificate($q, static::CERTIFICATE_EXPIRING)))),
            'last_check' => $last ? Carbon::parse($last) : null,
        ];
    }
}
