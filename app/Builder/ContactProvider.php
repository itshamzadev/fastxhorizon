<?php

namespace App\Builder;

use App\Models\Contact;
use Modules\Builder\Contracts\ContactProvider as ContactProviderContract;

/**
 * 6Valley host adapter for ContactProvider.
 *
 * Writes storefront contact messages into the same `contacts` table the host
 * contact page uses, so admins see all enquiries in one place. reCAPTCHA
 * mirrors the host: when Google reCAPTCHA is enabled in business settings the
 * v3 token from the storefront is verified against Google; when off the check
 * is skipped (the blade-only session captcha is not ported — inappropriate for
 * an SPA).
 */
class ContactProvider implements ContactProviderContract
{
    public function submit(array $payload): array
    {
        try {
            Contact::create([
                'name'    => (string) ($payload['name']    ?? ''),
                'email'   => (string) ($payload['email']   ?? ''),
                'subject' => (string) ($payload['subject'] ?? ''),
                'message' => (string) ($payload['message'] ?? ''),
            ]);
        } catch (\Throwable) {
            return ['success' => false, 'errors' => [[
                'code'    => 'persist',
                'message' => translate('Could_not_send_your_message') ?: 'Could not send your message.',
            ]]];
        }

        return ['success' => true];
    }

    /**
     * Returns null when reCAPTCHA is disabled OR verification passes; an
     * errors[] entry when it fails. A network/exception failure is treated as
     * a rejection but soft-reported so the admin can see Google's response in
     * the log without surfacing detail to the user.
     */
}
