<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Resources\MonitorResource\Pages;

use AmjadIqbal\FilamentUptimeMonitor\Actions\CheckMonitorAction;
use AmjadIqbal\FilamentUptimeMonitor\UptimeMonitor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Spatie\UptimeMonitor\Models\Monitor;

class EditMonitor extends EditRecord
{
    public static function getResource(): string
    {
        return UptimeMonitor::plugin()->getResource();
    }

    protected function getHeaderActions(): array
    {
        return [
            CheckMonitorAction::make(),
            DeleteAction::make()->modalIconColor('gray'),
        ];
    }

    /**
     * The model's url accessor returns a Spatie\Url\Url object; the form works with the stored string.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Monitor $record */
        $record = $this->getRecord();

        return [...$data, 'url' => $record->raw_url];
    }

    /**
     * A new URL is a new site: its old status, failure reason and certificate details no longer apply.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Monitor $record */
        $record = $this->getRecord();

        if (isset($data['url']) && $data['url'] !== $record->raw_url) {
            $data = [
                ...$data,
                'uptime_status' => UptimeStatus::NOT_YET_CHECKED,
                'uptime_check_failure_reason' => null,
                'uptime_check_times_failed_in_a_row' => 0,
                'uptime_last_check_date' => null,
                'uptime_check_failed_event_fired_on_date' => null,
                'certificate_status' => CertificateStatus::NOT_YET_CHECKED,
                'certificate_expiration_date' => null,
                'certificate_issuer' => null,
                'certificate_check_failure_reason' => '',
            ];
        }

        return $data;
    }
}
