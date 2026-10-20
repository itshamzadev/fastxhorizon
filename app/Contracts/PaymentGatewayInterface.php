<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function initialize(array $payment): array;

    public function verifyCallback(string $rawBody, array $headers): bool;

    public function normalizeStatus(mixed $providerStatus): string;
}
