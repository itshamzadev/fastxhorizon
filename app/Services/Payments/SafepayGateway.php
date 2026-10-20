<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use RuntimeException;

/**
 * Safepay boundary. The installed project contains no Safepay contract or SDK,
 * so this class intentionally refuses to guess provider fields or sign/verify
 * payloads. It becomes active only after the official sandbox contract is
 * confirmed and credentials are supplied.
 */
class SafepayGateway implements PaymentGatewayInterface
{
    public function initialize(array $payment): array
    {
        $this->assertConfigured();
        throw new RuntimeException('Safepay sandbox adapter requires the verified provider API contract before it can initialize payments.');
    }

    public function verifyCallback(string $rawBody, array $headers): bool
    {
        $this->assertConfigured();
        throw new RuntimeException('Safepay callback verification is not enabled until the provider signature contract is verified.');
    }

    public function normalizeStatus(mixed $providerStatus): string
    {
        return match (strtolower((string) $providerStatus)) {
            'paid', 'success', 'succeeded', 'completed' => 'paid',
            'failed', 'failure', 'declined', 'rejected' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    private function assertConfigured(): void
    {
        if (!config('safepay.enabled') || config('safepay.environment') !== 'sandbox') {
            throw new RuntimeException('Safepay is disabled. Enable only with verified sandbox credentials.');
        }

        foreach (['api_key', 'secret_key', 'base_url'] as $key) {
            if (!filled(config('safepay.' . $key))) {
                throw new RuntimeException('Safepay sandbox is not fully configured.');
            }
        }
    }
}
