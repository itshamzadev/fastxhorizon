<?php

namespace Tests\Unit;

use App\Services\Payments\SafepayGateway;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SafepayGatewayTest extends TestCase
{
    public function test_safepay_fails_closed_when_disabled(): void
    {
        Config::set('safepay.enabled', false);

        $this->expectException(\RuntimeException::class);
        (new SafepayGateway())->initialize([]);
    }

    public function test_provider_statuses_are_normalized_without_network_calls(): void
    {
        $gateway = new SafepayGateway();

        self::assertSame('paid', $gateway->normalizeStatus('success'));
        self::assertSame('failed', $gateway->normalizeStatus('declined'));
        self::assertSame('cancelled', $gateway->normalizeStatus('cancelled'));
        self::assertSame('pending', $gateway->normalizeStatus('processing'));
    }
}
