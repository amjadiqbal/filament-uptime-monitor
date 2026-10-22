<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Resources;

use AmjadIqbal\FilamentUptimeMonitor\Actions\CheckMonitorAction;
use AmjadIqbal\FilamentUptimeMonitor\Actions\CheckMonitorsBulkAction;
use AmjadIqbal\FilamentUptimeMonitor\Actions\DisableMonitorAction;
use AmjadIqbal\FilamentUptimeMonitor\Actions\EnableMonitorAction;
use AmjadIqbal\FilamentUptimeMonitor\Actions\ToggleMonitorsBulkAction;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\CreateMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\EditMonitor;
use AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages\ListMonitors;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\UptimeMonitor\Models\Monitor;
use UnitEnum;

class MonitorResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $slug = 'uptime-monitors';

    protected static bool $isScopedToTenant = false;

    public static function getModel(): string
    {
        return UptimeMonitor::monitorModel();
    }

    public static function getModelLabel(): string
    {
        return __('filament-uptime-monitor::uptime-monitor.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-uptime-monitor::uptime-monitor.resource.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return UptimeMonitor::plugin()->getNavigationLabel()
            ?? __('filament-uptime-monitor::uptime-monitor.resource.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return UptimeMonitor::plugin()->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return UptimeMonitor::plugin()->getNavigationSort() ?? parent::getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! UptimeMonitor::plugin()->hasNavigationBadge()) {
            return null;
        }

        $count = UptimeMonitor::scopeProblems(UptimeMonitor::query())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Monitor ? $record->raw_url : (string) static::getModelLabel();
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        return UptimeMonitor::can($ability, $record instanceof Monitor ? $record : null) ? Response::allow() : Response::deny();
    }

    public static function can(string|UnitEnum $action, ?Model $record = null): bool
    {
        return static::getAuthorizationResponse($action, $record)->allowed();
    }

    public static function form(Schema $schema): Schema
    {
        $t = fn (string $key): string => __("filament-uptime-monitor::uptime-monitor.{$key}");

        return $schema->components([
            Section::make($t('form.monitor'))
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('url')
                        ->label($t('form.url'))
                        ->helperText($t('form.url_helper'))
                        ->placeholder('https://example.com')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                            if ($operation === 'create') {
                                $set('certificate_check_enabled', str_starts_with(strtolower(trim((string) $state)), 'https://'));
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state): string => UptimeMonitor::normalizeUrl((string) $state))
                        ->rule(fn (?Monitor $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $url = UptimeMonitor::normalizeUrl((string) $value);

                            if ($problem = UptimeMonitor::urlProblem($url)) {
                                $fail($problem);

                                return;
                            }

                            $exists = UptimeMonitor::query()
                                ->where('url', $url)
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($exists) {
                                $fail(__('filament-uptime-monitor::uptime-monitor.validation.unique'));
                            }
                        })
                        ->columnSpanFull(),
                    Toggle::make('uptime_check_enabled')
                        ->label($t('form.uptime_check_enabled'))
                        ->default(true),
                    Toggle::make('certificate_check_enabled')
                        ->label($t('form.certificate_check_enabled'))
                        ->helperText($t('form.certificate_check_helper'))
                        ->default(false)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($value && ! str_starts_with(strtolower(trim((string) $get('url'))), 'https://')) {
                                $fail(__('filament-uptime-monitor::uptime-monitor.validation.certificate_https'));
                            }
                        }),
                ]),
            Section::make($t('form.uptime_check'))
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('uptime_check_interval_in_minutes')
                        ->label($t('form.interval'))
                        ->suffix($t('form.interval_suffix'))
                        ->integer()
                        ->required()
                        ->minValue(1)
                        ->maxValue(10080)
                        ->default(fn (): int => (int) config('uptime-monitor.uptime_check.run_interval_in_minutes', 5)),
                    Select::make('uptime_check_method')
                        ->label($t('form.method'))
                        ->options(array_combine(UptimeMonitor::METHODS, array_map('strtoupper', UptimeMonitor::METHODS)))
                        ->default('get')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live(),
                    TextInput::make('look_for_string')
                        ->label($t('form.look_for_string'))
                        ->helperText($t('form.look_for_string_helper'))
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state): string => (string) $state)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (filled($value) && $get('uptime_check_method') === 'head') {
                                $fail(__('filament-uptime-monitor::uptime-monitor.validation.head_with_text'));
                            }
                        })
                        ->columnSpanFull(),
                    Textarea::make('uptime_check_payload')
                        ->label($t('form.payload'))
                        ->rows(3)
                        ->maxLength(65535)
                        ->visible(fn (Get $get): bool => in_array($get('uptime_check_method'), ['post', 'put', 'patch', 'delete'], true))
                        ->columnSpanFull(),
                    KeyValue::make('uptime_check_additional_headers')
                        ->label($t('form.headers'))
                        ->keyLabel($t('form.header_name'))
                        ->valueLabel($t('form.header_value'))
                        ->dehydrateStateUsing(fn (?array $state): array => array_filter($state ?? [], fn ($value, $key): bool => filled($key), ARRAY_FILTER_USE_BOTH))
                        ->columnSpanFull(),
                    Select::make('uptime_check_response_checker')
                        ->label($t('form.response_checker'))
                        ->options(fn (): array => UptimeMonitor::plugin()->getResponseCheckers())
                        ->placeholder($t('form.response_checker_default'))
                        ->visible(fn (): bool => UptimeMonitor::plugin()->getResponseCheckers() !== [])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $t = fn (string $key, array $replace = []): string => __("filament-uptime-monitor::uptime-monitor.{$key}", $replace);

        return $table
            ->defaultSort('url')
            ->columns([
                TextColumn::make('url')
                    ->label($t('columns.url'))
                    ->state(fn (Monitor $record): string => $record->raw_url)
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uptime_status')
                    ->label($t('columns.uptime'))
                    ->state(fn (Monitor $record): string => UptimeMonitor::uptimeState($record))
                    ->formatStateUsing(fn (string $state): string => $t("uptime.{$state}"))
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        UptimeMonitor::UPTIME_UP => Heroicon::OutlinedCheckCircle,
                        UptimeMonitor::UPTIME_DOWN => Heroicon::OutlinedXCircle,
                        UptimeMonitor::UPTIME_DISABLED => Heroicon::OutlinedPauseCircle,
                        default => Heroicon::OutlinedClock,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        UptimeMonitor::UPTIME_UP => 'success',
                        UptimeMonitor::UPTIME_DOWN => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Monitor $record): ?string => in_array(UptimeMonitor::uptimeState($record), [UptimeMonitor::UPTIME_UP, UptimeMonitor::UPTIME_DOWN], true)
                        && $record->uptime_status_last_change_date
                            ? $t('columns.since', ['time' => $record->uptime_status_last_change_date->diffForHumans()])
                            : null)
                    ->tooltip(fn (Monitor $record): ?string => UptimeMonitor::uptimeState($record) === UptimeMonitor::UPTIME_DOWN
                        ? str((string) $record->uptime_check_failure_reason)->limit(300)->toString() ?: null
                        : null)
                    ->sortable(),
                TextColumn::make('certificate_status')
                    ->label($t('columns.certificate'))
                    ->state(fn (Monitor $record): string => UptimeMonitor::certificateState($record))
                    ->formatStateUsing(fn (string $state): string => $t("certificate.{$state}"))
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        UptimeMonitor::CERTIFICATE_VALID => Heroicon::OutlinedShieldCheck,
                        UptimeMonitor::CERTIFICATE_EXPIRING, UptimeMonitor::CERTIFICATE_INVALID => Heroicon::OutlinedShieldExclamation,
                        UptimeMonitor::CERTIFICATE_OFF => Heroicon::OutlinedMinusCircle,
                        default => Heroicon::OutlinedClock,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        UptimeMonitor::CERTIFICATE_VALID => 'success',
                        UptimeMonitor::CERTIFICATE_EXPIRING, UptimeMonitor::CERTIFICATE_INVALID => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Monitor $record): ?string => match (true) {
                        ! $record->certificate_check_enabled || ! $record->certificate_expiration_date => null,
                        $record->certificate_expiration_date->isPast() => $t('columns.expired', ['date' => $record->certificate_expiration_date->toFormattedDateString()]),
                        default => $t('columns.expires', ['date' => $record->certificate_expiration_date->toFormattedDateString()]),
                    })
                    ->tooltip(fn (Monitor $record): ?string => match (UptimeMonitor::certificateState($record)) {
                        UptimeMonitor::CERTIFICATE_INVALID => str((string) $record->certificate_check_failure_reason)->limit(300)->toString() ?: null,
                        UptimeMonitor::CERTIFICATE_VALID, UptimeMonitor::CERTIFICATE_EXPIRING => filled($record->certificate_issuer)
                            ? $t('columns.issuer', ['issuer' => (string) $record->certificate_issuer])
                            : null,
                        default => null,
                    })
                    ->sortable(['certificate_expiration_date']),
                TextColumn::make('uptime_last_check_date')
                    ->label($t('columns.last_checked'))
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder($t('columns.never'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('uptime_check_interval_in_minutes')
                    ->label($t('columns.interval'))
                    ->formatStateUsing(fn (mixed $state): string => $t('columns.interval_value', ['count' => (int) $state]))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('uptime')
                    ->label($t('filters.uptime'))
                    ->options(collect([UptimeMonitor::UPTIME_UP, UptimeMonitor::UPTIME_DOWN, UptimeMonitor::UPTIME_UNCHECKED, UptimeMonitor::UPTIME_DISABLED])
                        ->mapWithKeys(fn (string $state): array => [$state => $t("uptime.{$state}")])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? UptimeMonitor::scopeUptime($query, (string) $data['value'])
                        : $query),
                SelectFilter::make('certificate')
                    ->label($t('filters.certificate'))
                    ->options(collect([UptimeMonitor::CERTIFICATE_VALID, UptimeMonitor::CERTIFICATE_EXPIRING, UptimeMonitor::CERTIFICATE_INVALID, UptimeMonitor::CERTIFICATE_UNCHECKED, UptimeMonitor::CERTIFICATE_OFF])
                        ->mapWithKeys(fn (string $state): array => [$state => $t("certificate.{$state}")])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? UptimeMonitor::scopeCertificate($query, (string) $data['value'])
                        : $query),
                TernaryFilter::make('enabled')
                    ->label($t('filters.enabled'))
                    ->trueLabel($t('filters.enabled_true'))
                    ->falseLabel($t('filters.enabled_false'))
                    ->queries(
                        true: fn (Builder $query): Builder => UptimeMonitor::scopeEnabled($query),
                        false: fn (Builder $query): Builder => UptimeMonitor::scopeEnabled($query, false),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('problems')
                    ->label($t('filters.problems'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => UptimeMonitor::scopeProblems($query)),
            ])
            ->recordActions([
                CheckMonitorAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    EnableMonitorAction::make(),
                    DisableMonitorAction::make(),
                    DeleteAction::make()->modalIconColor('gray'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CheckMonitorsBulkAction::make(),
                    ToggleMonitorsBulkAction::enable(),
                    ToggleMonitorsBulkAction::disable(),
                    DeleteBulkAction::make()->modalIconColor('gray'),
                ]),
            ])
            ->emptyStateHeading($t('resource.empty_heading'))
            ->emptyStateDescription($t('resource.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedSignal);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitors::route('/'),
            'create' => CreateMonitor::route('/create'),
            'edit' => EditMonitor::route('/{record}/edit'),
        ];
    }
}
