<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor;

use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource;
use AmjadIqbal\FilamentUptimeMonitor\Widgets\MonitorStatusOverview;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Throwable;

class UptimeMonitorPlugin implements Plugin
{
    public const ID = 'uptime-monitor';

    protected bool $hasResource = true;

    protected ?string $resource = null;

    protected bool $hasDashboardWidget = true;

    protected bool $hasListWidget = true;

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?string $navigationLabel = null;

    protected bool $hasNavigationBadge = true;

    protected ?Closure $authorizeUsing = null;

    protected bool $allowsPrivateHosts = false;

    /** @var array<class-string, string> */
    protected array $responseCheckers = [];

    protected int $maxChecksPerRequest = 20;

    protected int $checksPerMinute = 30;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin registered on the current panel, or a default instance outside a panel.
     */
    public static function get(): static
    {
        try {
            $panel = Filament::getCurrentOrDefaultPanel();

            if ($panel?->hasPlugin(static::ID)) {
                /** @var static */
                return $panel->getPlugin(static::ID);
            }
        } catch (Throwable) {
            // No panels registered.
        }

        return app(static::class);
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        if ($this->hasResource) {
            $panel->resources([$this->getResource()]);
        }

        if ($this->hasDashboardWidget) {
            $panel->widgets([MonitorStatusOverview::class]);
        }
    }

    public function boot(Panel $panel): void {}

    public function resource(bool|string $condition = true): static
    {
        if (is_string($condition)) {
            $this->resource = $condition;
            $condition = true;
        }

        $this->hasResource = $condition;

        return $this;
    }

    /**
     * @return class-string<MonitorResource>
     */
    public function getResource(): string
    {
        /** @var class-string<MonitorResource> */
        return $this->resource ?? MonitorResource::class;
    }

    /**
     * Show the status widget on the panel dashboard.
     */
    public function dashboardWidget(bool $condition = true): static
    {
        $this->hasDashboardWidget = $condition;

        return $this;
    }

    public function hasDashboardWidget(): bool
    {
        return $this->hasDashboardWidget;
    }

    /**
     * Show the status widget above the monitors table.
     */
    public function listWidget(bool $condition = true): static
    {
        $this->hasListWidget = $condition;

        return $this;
    }

    public function hasListWidget(): bool
    {
        return $this->hasListWidget;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    /**
     * Show the number of monitors that need attention (down, or certificate invalid or expiring soon) next to the navigation item.
     */
    public function navigationBadge(bool $condition = true): static
    {
        $this->hasNavigationBadge = $condition;

        return $this;
    }

    public function hasNavigationBadge(): bool
    {
        return $this->hasNavigationBadge;
    }

    /**
     * Decide who may see, add, change, check or delete monitors.
     * The closure receives `$ability` (viewAny, view, create, update, delete, deleteAny, checkNow), `$record` (the
     * monitor, or null for viewAny, create, deleteAny) and `$user`, and returns a bool. It replaces the policy checks.
     */
    public function authorizeUsing(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function getAuthorizeUsing(): ?Closure
    {
        return $this->authorizeUsing;
    }

    /**
     * Allow monitors on localhost and private, loopback, link-local or reserved addresses. Off by default, because a
     * panel user could otherwise make the server request internal services.
     */
    public function allowPrivateHosts(bool $condition = true): static
    {
        $this->allowsPrivateHosts = $condition;

        return $this;
    }

    public function allowsPrivateHosts(): bool
    {
        return $this->allowsPrivateHosts;
    }

    /**
     * Response checker classes a user may pick per monitor, e.g. [JsonHealthChecker::class => 'JSON health']. Empty
     * hides the field, so every monitor uses the checker from config/uptime-monitor.php.
     *
     * @param  array<class-string, string>  $checkers
     */
    public function responseCheckers(array $checkers): static
    {
        $this->responseCheckers = $checkers;

        return $this;
    }

    /**
     * @return array<class-string, string>
     */
    public function getResponseCheckers(): array
    {
        return $this->responseCheckers;
    }

    /**
     * The most monitors one "Check now" bulk action checks in one request.
     */
    public function maxChecksPerRequest(int $max): static
    {
        $this->maxChecksPerRequest = max(1, $max);

        return $this;
    }

    public function getMaxChecksPerRequest(): int
    {
        return $this->maxChecksPerRequest;
    }

    /**
     * How many monitors one user may check by hand per minute.
     */
    public function checksPerMinute(int $limit): static
    {
        $this->checksPerMinute = max(1, $limit);

        return $this;
    }

    public function getChecksPerMinute(): int
    {
        return $this->checksPerMinute;
    }
}
