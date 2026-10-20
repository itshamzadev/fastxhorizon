<?php

namespace App\Builder;

use App\Traits\MaintenanceModeTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Builder\Contracts\MaintenanceProvider as MaintenanceProviderContract;
use Modules\Builder\ValueObjects\MaintenanceState;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley maintenance adapter.
 *
 * Mirrors the host's admin maintenance feature (Business Settings → Maintenance
 * Mode). The storefront is the customer-facing website, so it is gated by the
 * `user_website` system checkbox. The master switch, selected systems, duration
 * window and message body are projected from the same `system_maintenance_mode`
 * cache the host {@see MaintenanceModeTrait} reads, so admin saves stay
 * consistent. The window check is delegated to that trait's
 * {@see MaintenanceModeTrait::checkForMaintenanceMode()} to avoid duplicating
 * the "until_change vs dated window" logic.
 */
class MaintenanceProvider implements MaintenanceProviderContract
{
    use MaintenanceModeTrait;

    /** The system key this storefront is gated by (admin checkbox). */
    private const SYSTEM_KEY = 'user_website';

    public function state(?StorefrontScope $scope = null): MaintenanceState
    {
        $maintenance = Cache::get('system_maintenance_mode');

        if (!$maintenance || !($maintenance['status'] ?? false)) {
            return MaintenanceState::inactive();
        }

        if (!($maintenance['selectedSystems'][self::SYSTEM_KEY] ?? 0)) {
            return MaintenanceState::inactive();
        }

        if (!$this->checkForMaintenanceMode($maintenance)) {
            return MaintenanceState::inactive();
        }

        $message = $maintenance['maintenance_message'] ?? [];

        return new MaintenanceState(
            active:  true,
            title:   $message['maintenance_message'] ?? null,
            body:    $message['message_body'] ?? null,
            endDate: $this->endDate($maintenance['maintenance_duration'] ?? []),
            phone:   !empty($message['business_number']) ? (getWebConfig(name: 'company_phone') ?: null) : null,
            email:   !empty($message['business_email']) ? (getWebConfig(name: 'company_email') ?: null) : null,
        );
    }

    /** ISO end timestamp for the countdown, or null for an open-ended window. */
    private function endDate(array $duration): ?string
    {
        if (($duration['maintenance_duration'] ?? null) === 'until_change') {
            return null;
        }

        $end = $duration['end_date'] ?? null;
        if (!$end) {
            return null;
        }

        try {
            return Carbon::parse($end)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
