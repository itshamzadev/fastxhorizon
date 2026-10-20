<?php

namespace App\Builder;

use App\Models\User;
use Modules\Builder\Contracts\PushNotificationProvider as PushNotificationProviderContract;

/**
 * Host adapter — persists customer FCM tokens against `users.cm_firebase_token`.
 *
 * Reads/writes the same column the host's existing customer APIs use, so a
 * token registered from the storefront is picked up by the host's dispatch
 * pipeline. Single-column design is last-device-wins, matching the host model.
 */
class PushNotificationProvider implements PushNotificationProviderContract
{
    public function storeCustomerToken(int $customerId, string $token): void
    {
        User::query()
            ->where('id', $customerId)
            ->update(['cm_firebase_token' => $token]);
    }

    public function clearCustomerToken(int $customerId): void
    {
        User::query()
            ->where('id', $customerId)
            ->update(['cm_firebase_token' => null]);
    }
}
