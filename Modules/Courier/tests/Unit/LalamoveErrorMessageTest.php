<?php

namespace Modules\Courier\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Courier\CourierProviders\LalamoveProvider;
use Modules\Courier\Tests\CourierTestCase;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\Exceptions\CourierException;

class LalamoveErrorMessageTest extends CourierTestCase
{
    private function quoteFailsWith(int $status, array $body): string
    {
        Http::fake(['*/v3/quotations' => Http::response($body, $status)]);

        $provider = new LalamoveProvider();
        $provider->setCredentials(['api_key' => 'key', 'api_secret' => 'secret']);

        try {
            $provider->getDeliveryCharges(new QuoteData(
                weight: 1.0,
                toLatitude: '23.8103',
                toLongitude: '90.4125',
                meta: ['address' => 'Dhaka'],
            ));
        } catch (CourierException $exception) {
            return $exception->getMessage();
        }

        $this->fail('Lalamove was expected to reject the quotation.');
    }

    public function test_a_documented_error_code_is_replaced_by_a_readable_message(): void
    {
        $message = $this->quoteFailsWith(422, ['errors' => [[
            'id'      => 'e4e4ea0e',
            'message' => 'ERR_OUT_OF_SERVICE_AREA',
            'detail'  => 'given lattitue/longtitude is out of service',
        ]]]);

        $this->assertStringNotContainsString('ERR_', $message);
        $this->assertStringContainsString('service area', $message);
    }

    public function test_every_reported_error_code_contributes_a_readable_message(): void
    {
        $message = $this->quoteFailsWith(422, ['errors' => [
            ['message' => 'ERR_INVALID_FIELD', 'detail' => 'data.recipients[0].phone'],
            ['message' => 'ERR_INSUFFICIENT_CREDIT'],
        ]]);

        $this->assertStringNotContainsString('ERR_', $message);
        $this->assertStringNotContainsString('data.recipients', $message);
        $this->assertStringContainsString('format Lalamove expects', $message);
        $this->assertStringContainsString('credit', $message);
    }

    public function test_an_undocumented_error_code_falls_back_to_the_status_message(): void
    {
        $message = $this->quoteFailsWith(422, ['errors' => [[
            'message' => 'ERR_A_CODE_LALAMOVE_HAS_NOT_DOCUMENTED',
            'detail'  => 'internal detail',
        ]]]);

        $this->assertStringNotContainsString('ERR_', $message);
        $this->assertStringNotContainsString('internal detail', $message);
        $this->assertStringContainsString('Lalamove could not complete this request', $message);
    }

    public function test_rejected_credentials_point_at_the_delivery_partner_settings(): void
    {
        $this->assertStringContainsString(
            'API key and secret',
            $this->quoteFailsWith(401, ['message' => 'Your authorization token is incorrect.']),
        );
    }

    public function test_a_server_side_outage_reads_as_a_temporary_failure(): void
    {
        $this->assertStringContainsString(
            'not responding right now',
            $this->quoteFailsWith(503, []),
        );
    }
}
