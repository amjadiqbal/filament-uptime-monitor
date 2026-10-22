<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentUptimeMonitor\Tests\Fixtures;

use Illuminate\Support\Carbon;
use Spatie\UptimeMonitor\Models\Enums\CertificateStatus;
use Spatie\UptimeMonitor\Models\Monitor;

/**
 * A monitor model set in config('uptime-monitor.monitor_model'), whose certificate check reads from a fixture
 * instead of opening a TLS connection.
 */
class CustomMonitor extends Monitor
{
    protected $table = 'monitors';

    /** @var array<string, string|int> host => days until expiry, or a failure reason */
    public static array $certificates = [];

    public function checkCertificate(): void
    {
        $result = static::$certificates[$this->url->getHost()] ?? 'Could not connect';

        if (is_int($result)) {
            $this->certificate_status = CertificateStatus::VALID;
            $this->certificate_expiration_date = Carbon::now()->addDays($result);
            $this->certificate_issuer = 'Test CA';
            $this->certificate_check_failure_reason = '';
        } else {
            $this->certificate_status = CertificateStatus::INVALID;
            $this->certificate_expiration_date = null;
            $this->certificate_issuer = '';
            $this->certificate_check_failure_reason = $result;
        }

        $this->save();
    }
}
