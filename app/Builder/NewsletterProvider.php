<?php

namespace App\Builder;

use App\Models\Subscription;
use Modules\Builder\Contracts\NewsletterProvider as NewsletterProviderContract;

/**
 * 6Valley host adapter for NewsletterProvider.
 *
 * Writes storefront footer newsletter subscriptions into the same
 * `subscriptions` table the host newsletter feature uses, with the same
 * duplicate-email guard, so admins see all subscribers in one place.
 */
class NewsletterProvider implements NewsletterProviderContract
{
    public function subscribe(string $email): array
    {
        $email = strtolower(trim($email));

        if (Subscription::where('email', $email)->exists()) {
            return ['success' => false, 'errors' => [[
                'code'    => 'exists',
                'message' => translate('You_are_already_subscribed') ?: 'You are already subscribed.',
            ]]];
        }

        try {
            Subscription::create(['email' => $email]);
        } catch (\Throwable) {
            return ['success' => false, 'errors' => [[
                'code'    => 'persist',
                'message' => translate('Could_not_subscribe_right_now') ?: 'Could not subscribe right now.',
            ]]];
        }

        return ['success' => true];
    }
}
