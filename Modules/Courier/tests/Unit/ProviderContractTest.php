<?php

namespace Modules\Courier\Tests\Unit;

use Modules\Courier\CourierProviders\Contracts\CalculatesPrice;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CollectsCod;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesBatchWebhooks;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ProvidesStores;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\CourierProviders\Contracts\UpdatesOrders;
use Modules\Courier\CourierProviders\AramexProvider;
use Modules\Courier\CourierProviders\CourierProvider;
use Modules\Courier\CourierProviders\DelhiveryProvider;
use Modules\Courier\CourierProviders\DhlProvider;
use Modules\Courier\CourierProviders\DomexProvider;
use Modules\Courier\CourierProviders\GarudaExpressProvider;
use Modules\Courier\CourierProviders\LalamoveProvider;
use Modules\Courier\CourierProviders\LeopardsProvider;
use Modules\Courier\CourierProviders\NepalCanMoveProvider;
use Modules\Courier\CourierProviders\PathaoNepalProvider;
use Modules\Courier\CourierProviders\PathaoProvider;
use Modules\Courier\CourierProviders\RedxProvider;
use Modules\Courier\CourierProviders\ShiprocketProvider;
use Modules\Courier\CourierProviders\TcsProvider;
use Modules\Courier\app\DataTransferObjects\Requests\ItemData;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Http\Requests\Admin\SendToCourierRequest;
use Modules\Courier\app\Http\Requests\SaveCourierProviderRequest;
use Modules\Courier\app\Models\CourierProviderAddress;
use Modules\Courier\app\Models\CourierProviderSetting;
use Modules\Courier\app\Services\CourierConfigService;
use Modules\Courier\app\Services\ProviderRegistry;
use Modules\Courier\app\Support\CountryList;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Modules\Courier\Tests\CourierTestCase;

class ProviderContractTest extends CourierTestCase
{
    public function test_every_registered_provider_is_a_courier_provider_matching_its_id(): void
    {
        $registry = (array) config('courier.providers', []);
        $this->assertNotEmpty($registry, 'No courier providers registered.');

        foreach ($registry as $id => $class) {
            $driver = $this->app->make($class);

            $this->assertInstanceOf(CourierProvider::class, $driver, "[{$id}] must extend CourierProvider.");
            $this->assertSame($id, $driver->getName(), "[{$id}] getName() must match its registry id.");
        }
    }

    public function test_pathao_advertises_the_roles_it_implements(): void
    {
        $pathao = $this->app->make(PathaoProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $pathao);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $pathao);
        $this->assertInstanceOf(ResolvesLocations::class, $pathao);
        $this->assertInstanceOf(CalculatesPrice::class, $pathao);
        $this->assertInstanceOf(HandlesWebhooks::class, $pathao);
    }

    public function test_redx_advertises_the_roles_it_implements(): void
    {
        $redx = $this->app->make(RedxProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $redx);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $redx);
        $this->assertInstanceOf(TracksOrders::class, $redx);
        $this->assertInstanceOf(CancelsOrders::class, $redx);
        $this->assertInstanceOf(UpdatesOrders::class, $redx);
        $this->assertInstanceOf(EstimatesDeliveryCharge::class, $redx);
        $this->assertInstanceOf(ResolvesLocations::class, $redx);
        $this->assertInstanceOf(HandlesWebhooks::class, $redx);
    }

    public function test_dhl_advertises_the_roles_it_implements(): void
    {
        $dhl = $this->app->make(DhlProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $dhl);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $dhl);
        $this->assertInstanceOf(TracksOrders::class, $dhl);
        $this->assertInstanceOf(EstimatesDeliveryCharge::class, $dhl);

        $this->assertNotInstanceOf(ResolvesLocations::class, $dhl);
        $this->assertNotInstanceOf(ProvidesStores::class, $dhl);
        $this->assertNotInstanceOf(CollectsCod::class, $dhl);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $dhl);
        $this->assertNotInstanceOf(CancelsOrders::class, $dhl);
    }

    public function test_dhl_declares_the_international_address_mode(): void
    {
        $dhl = $this->app->make(DhlProvider::class);

        $this->assertNotEmpty($dhl->addressFields());
        $this->assertSame([], $dhl->locationLevels());
        $this->assertSame(['country_code', 'postal_code', 'city_name', 'state_province'], array_column($dhl->addressFields(), 'key'));
    }

    public function test_catalog_providers_do_not_use_the_international_address_mode(): void
    {
        $this->assertSame([], $this->app->make(PathaoProvider::class)->addressFields());
        $this->assertSame([], $this->app->make(RedxProvider::class)->addressFields());
    }

    public function test_delhivery_advertises_the_roles_it_implements(): void
    {
        $delhivery = $this->app->make(DelhiveryProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $delhivery);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $delhivery);
        $this->assertInstanceOf(TracksOrders::class, $delhivery);
        $this->assertInstanceOf(CancelsOrders::class, $delhivery);
        $this->assertInstanceOf(UpdatesOrders::class, $delhivery);
        $this->assertInstanceOf(EstimatesDeliveryCharge::class, $delhivery);
        $this->assertInstanceOf(HandlesWebhooks::class, $delhivery);

        $this->assertNotInstanceOf(ResolvesLocations::class, $delhivery);
        $this->assertNotInstanceOf(ProvidesStores::class, $delhivery);
        $this->assertNotInstanceOf(CollectsCod::class, $delhivery);
        $this->assertNotInstanceOf(CalculatesPrice::class, $delhivery);
    }

    public function test_delhivery_declares_the_postal_address_mode(): void
    {
        $delhivery = $this->app->make(DelhiveryProvider::class);

        $this->assertSame('postal', $delhivery->addressMode());
        $this->assertSame([], $delhivery->locationLevels());
        $this->assertSame(['country_code', 'postal_code', 'city_name', 'state_province'], array_column($delhivery->addressFields(), 'key'));
    }

    public function test_lalamove_advertises_the_roles_it_implements(): void
    {
        $lalamove = $this->app->make(LalamoveProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $lalamove);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $lalamove);
        $this->assertInstanceOf(TracksOrders::class, $lalamove);
        $this->assertInstanceOf(EstimatesDeliveryCharge::class, $lalamove);
        $this->assertInstanceOf(CancelsOrders::class, $lalamove);
        $this->assertInstanceOf(HandlesWebhooks::class, $lalamove);

        $this->assertNotInstanceOf(ResolvesLocations::class, $lalamove);
        $this->assertNotInstanceOf(ProvidesStores::class, $lalamove);
        $this->assertNotInstanceOf(CollectsCod::class, $lalamove);
        $this->assertNotInstanceOf(UpdatesOrders::class, $lalamove);
    }

    public function test_lalamove_declares_the_geo_address_mode(): void
    {
        $lalamove = $this->app->make(LalamoveProvider::class);

        $this->assertSame('geo', $lalamove->addressMode());
        $this->assertSame([], $lalamove->locationLevels());
        $this->assertSame(['latitude', 'longitude'], array_column($lalamove->addressFields(), 'key'));
    }

    public function test_providers_declare_their_address_mode(): void
    {
        $this->assertSame('catalog', $this->app->make(PathaoProvider::class)->addressMode());
        $this->assertSame('catalog', $this->app->make(RedxProvider::class)->addressMode());
        $this->assertSame('postal', $this->app->make(DhlProvider::class)->addressMode());
        $this->assertSame('geo', $this->app->make(LalamoveProvider::class)->addressMode());
        $this->assertSame('postal', $this->app->make(AramexProvider::class)->addressMode());
        $this->assertSame('postal', $this->app->make(ShiprocketProvider::class)->addressMode());
        $this->assertSame('postal', $this->app->make(DomexProvider::class)->addressMode());
        $this->assertSame('catalog', $this->app->make(TcsProvider::class)->addressMode());
    }

    public function test_optional_fields_complete_the_dispatch_form_per_address_mode(): void
    {
        $always = ['quantity', 'item_description', 'note', 'order_value'];

        $this->assertSame(
            array_merge(['store_id', 'delivery_type'], $always),
            $this->app->make(PathaoProvider::class)->optionalFields(),
        );

        $this->assertSame(
            array_merge(['store_id'], $always),
            $this->app->make(RedxProvider::class)->optionalFields(),
        );

        $this->assertSame(
            array_merge(['state_province'], $always),
            $this->app->make(DhlProvider::class)->optionalFields(),
        );

        $this->assertSame(
            array_merge(['delivery_type'], $always),
            $this->app->make(LalamoveProvider::class)->optionalFields(),
        );

        $this->assertSame(
            array_merge(['source_branch_id', 'destination_branch_id', 'delivery_type'], $always),
            $this->app->make(NepalCanMoveProvider::class)->optionalFields(),
        );
    }

    public function test_the_api_field_contract_and_the_web_dispatch_form_render_the_same_inputs(): void
    {
        preg_match_all(
            '/name="([a-z_]+)"/',
            (string) file_get_contents(module_path('Courier', 'resources/views/partials/_delivery-partner-fields.blade.php')),
            $matches,
        );

        $formFields = array_values(array_unique($matches[1]));
        $accepted = array_keys((new SendToCourierRequest())->rules());

        $this->assertSame(
            [],
            array_values(array_diff($formFields, $accepted)),
            'The dispatch form renders an input SendToCourierRequest does not accept.',
        );

        $advertised = ['provider', 'host_order_reference'];

        foreach ($this->everyProviderClass() as $class) {
            $driver = $this->app->make($class);
            $advertised = array_merge($advertised, $driver->requiredFields(), $driver->optionalFields());
        }

        $advertised = array_unique($advertised);

        $this->assertSame(
            [],
            array_values(array_diff($formFields, $advertised)),
            'The web dispatch form renders a field no provider advertises to the API.',
        );

        $this->assertSame(
            [],
            array_values(array_diff($advertised, $formFields)),
            'A provider advertises a field the web dispatch form never renders.',
        );
    }

    private function everyProviderClass(): array
    {
        $classes = [];

        foreach (glob(module_path('Courier', 'CourierProviders/*Provider.php')) as $file) {
            $class = 'Modules\\Courier\\CourierProviders\\'.basename($file, '.php');

            if ($class !== CourierProvider::class) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    public function test_no_provider_declares_a_field_as_both_required_and_optional(): void
    {
        foreach ((array) config('courier.providers', []) as $id => $class) {
            $driver = $this->app->make($class);

            $this->assertSame(
                [],
                array_intersect($driver->requiredFields(), $driver->optionalFields()),
                "[{$id}] declares the same field as required and optional.",
            );
        }
    }

    public function test_aramex_advertises_the_roles_it_implements(): void
    {
        $aramex = $this->app->make(AramexProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $aramex);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $aramex);
        $this->assertInstanceOf(TracksOrders::class, $aramex);

        $this->assertNotInstanceOf(EstimatesDeliveryCharge::class, $aramex);
        $this->assertNotInstanceOf(ResolvesLocations::class, $aramex);
        $this->assertNotInstanceOf(ProvidesStores::class, $aramex);
        $this->assertNotInstanceOf(CollectsCod::class, $aramex);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $aramex);
        $this->assertNotInstanceOf(CancelsOrders::class, $aramex);
        $this->assertNotInstanceOf(UpdatesOrders::class, $aramex);
    }

    public function test_aramex_declares_the_postal_address_mode(): void
    {
        $aramex = $this->app->make(AramexProvider::class);

        $this->assertSame('postal', $aramex->addressMode());
        $this->assertSame([], $aramex->locationLevels());
        $this->assertSame(['country_code', 'postal_code', 'city_name', 'state_province'], array_column($aramex->addressFields(), 'key'));
    }

    public function test_aramex_embeds_client_info_and_returns_the_awb(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors'  => false,
                'Shipments'  => [['ID' => '1234567890', 'HasErrors' => false, 'Notifications' => []]],
            ], 200),
        ]);

        $result = $this->makeConfiguredAramex()->createOrder($this->sampleOrder());

        $this->assertSame('1234567890', $result->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $result->status);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['ClientInfo']['AccountNumber'] ?? null) === '102331'
                && ($body['ClientInfo']['Source'] ?? null) === 24
                && str_starts_with((string) ($body['Shipments'][0]['ForeignHAWB'] ?? ''), '102331-')
                && str_starts_with((string) ($body['Shipments'][0]['ShippingDateTime'] ?? ''), '/Date(');
        });
    }

    public function test_aramex_treats_has_errors_on_http_200_as_a_failure(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors'     => true,
                'Notifications' => [['Code' => 'ERR30', 'Message' => 'Foreign AWB already exists']],
            ], 200),
        ]);

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('Foreign AWB already exists');

        $this->makeConfiguredAramex()->createOrder($this->sampleOrder());
    }

    public function test_aramex_surfaces_per_shipment_errors_on_a_partially_failed_batch(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors' => false,
                'Shipments' => [[
                    'ID'            => '',
                    'HasErrors'     => true,
                    'Notifications' => [['Code' => 'ERR34', 'Message' => 'Failed to save the shipment']],
                ]],
            ], 200),
        ]);

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('Failed to save the shipment');

        $this->makeConfiguredAramex()->createOrder($this->sampleOrder());
    }

    public function test_aramex_parses_the_tracking_results_dictionary_and_omits_source(): void
    {
        Http::fake([
            '*/TrackShipments' => Http::response([
                'HasErrors'       => false,
                'TrackingResults' => [[
                    'Key'   => '1234567890',
                    'Value' => [[
                        'WaybillNumber'     => '1234567890',
                        'UpdateCode'        => 'SH014',
                        'UpdateDescription' => 'Shipment out for delivery',
                        'UpdateDateTime'    => '/Date(1740090000000)/',
                        'UpdateLocation'    => 'Dubai',
                    ]],
                ]],
            ], 200),
        ]);

        $events = $this->makeConfiguredAramex()->trackOrder('1234567890');

        $this->assertCount(1, $events);
        $this->assertSame(ShipmentStatus::OutForDelivery, $events[0]->status);
        $this->assertSame('Dubai', $events[0]->location);
        $this->assertNotSame('', $events[0]->occurredAt);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return str_contains((string) $request->url(), '/tracking/')
                && !array_key_exists('Source', (array) ($body['ClientInfo'] ?? []))
                && ($body['Shipments'][0] ?? null) === '1234567890';
        });
    }

    private function makeConfiguredAramex(string $country = 'GB'): AramexProvider
    {
        $aramex = $this->app->make(AramexProvider::class);
        $aramex->setCountry($country);
        $aramex->setCredentials([
            'username'             => 'testingapi@aramex.com',
            'password'             => 'R123456789$r',
            'version'              => 'v1',
            'account_number'       => '102331',
            'account_pin'          => '321321',
            'account_entity'       => 'LON',
            'origin_company_name'  => 'Shop',
            'origin_full_name'     => 'Owner',
            'origin_phone'         => '5555555',
            'origin_city'          => 'London',
            'origin_line1'         => '15 ABC Street',
        ]);

        return $aramex;
    }

    private function sampleOrder(): OrderData
    {
        return new OrderData(
            hostOrderReference: 'ORDER-10024',
            recipient: new RecipientData(
                name: 'Mazen',
                phone: '0500000000',
                address: '15 ABC St',
                countryCode: 'AE',
                cityName: 'Dubai',
            ),
            codAmount: 0.0,
            weight: 0.5,
        );
    }

    public function test_lalamove_signs_each_request_over_the_exact_body_it_sends(): void
    {
        Http::fake([
            '*/v3/quotations' => Http::response(['data' => ['priceBreakdown' => ['total' => '124', 'currency' => 'HKD']]], 201),
        ]);

        $lalamove = $this->app->make(LalamoveProvider::class);
        $lalamove->setCountry('HK');
        $lalamove->setCredentials([
            'api_key'              => 'pk_test_key',
            'api_secret'           => 'sk_test_secret',
            'language'             => 'en_HK',
            'default_service_type' => 'MOTORCYCLE',
            'origin_latitude'      => '22.33',
            'origin_longitude'     => '114.17',
            'origin_address'       => 'Origin',
            'sender_name'          => 'Sender',
            'sender_phone'         => '+85256847123',
        ]);

        $lalamove->getDeliveryCharges(new QuoteData(weight: 1.0, toLatitude: '22.28', toLongitude: '114.15'));

        Http::assertSent(function ($request): bool {
            $auth = $request->header('Authorization')[0] ?? '';
            if (!str_starts_with($auth, 'hmac pk_test_key:')) {
                return false;
            }

            [$key, $timestamp, $signature] = explode(':', substr($auth, strlen('hmac ')));
            $expected = hash_hmac('sha256', $timestamp."\r\n".'POST'."\r\n".'/v3/quotations'."\r\n\r\n".$request->body(), 'sk_test_secret');

            return $key === 'pk_test_key'
                && $signature === $expected
                && ($request->header('Market')[0] ?? '') === 'HK'
                && !empty($request->header('Request-ID')[0] ?? '');
        });
    }

    public function test_shiprocket_advertises_the_roles_it_implements(): void
    {
        $shiprocket = $this->app->make(ShiprocketProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $shiprocket);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $shiprocket);
        $this->assertInstanceOf(TracksOrders::class, $shiprocket);
        $this->assertInstanceOf(CancelsOrders::class, $shiprocket);
        $this->assertInstanceOf(EstimatesDeliveryCharge::class, $shiprocket);
        $this->assertInstanceOf(HandlesWebhooks::class, $shiprocket);

        $this->assertNotInstanceOf(ResolvesLocations::class, $shiprocket);
        $this->assertNotInstanceOf(ProvidesStores::class, $shiprocket);
        $this->assertNotInstanceOf(CollectsCod::class, $shiprocket);
        $this->assertNotInstanceOf(UpdatesOrders::class, $shiprocket);
        $this->assertNotInstanceOf(CalculatesPrice::class, $shiprocket);
    }

    public function test_shiprocket_declares_the_postal_address_mode(): void
    {
        $shiprocket = $this->app->make(ShiprocketProvider::class);

        $this->assertSame('postal', $shiprocket->addressMode());
        $this->assertSame([], $shiprocket->locationLevels());
        $this->assertSame(['country_code', 'postal_code', 'city_name', 'state_province'], array_column($shiprocket->addressFields(), 'key'));
    }

    public function test_shiprocket_chains_create_serviceability_and_awb_assignment(): void
    {
        Http::fake([
            '*/auth/login'              => Http::response(['token' => 'jwt-abc'], 200),
            '*/orders/create/adhoc'     => Http::response(['order_id' => 705944082, 'shipment_id' => 702734113, 'status' => 'NEW', 'status_code' => 1], 200),
            '*serviceability*'          => Http::response(['status' => 200, 'data' => ['recommended_courier_company_id' => 24, 'available_courier_companies' => [['courier_company_id' => 24, 'courier_name' => 'Delhivery Surface', 'rate' => 85]]]], 200),
            '*/courier/assign/awb'      => Http::response(['awb_assign_status' => 1, 'response' => ['data' => ['courier_company_id' => 24, 'awb_code' => '19041424751540', 'courier_name' => 'Delhivery Surface', 'freight_charges' => 85]]], 200),
        ]);

        $result = $this->makeConfiguredShiprocket()->createOrder($this->sampleIndiaOrder());

        $this->assertSame('19041424751540', $result->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $result->status);
        $this->assertSame(705944082, $result->raw['sr_order_id']);
        $this->assertSame(702734113, $result->raw['sr_shipment_id']);

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/orders/create/adhoc')) {
                return false;
            }

            $body = json_decode($request->body(), true);

            return ($request->header('Authorization')[0] ?? '') === 'Bearer jwt-abc'
                && (float) ($body['sub_total'] ?? 0) === 9000.0
                && ($body['pickup_location'] ?? null) === 'Primary';
        });
    }

    public function test_shiprocket_treats_an_error_message_on_http_200_as_a_failure(): void
    {
        Http::fake([
            '*/auth/login'          => Http::response(['token' => 'jwt-abc'], 200),
            '*/orders/create/adhoc' => Http::response(['message' => 'The pickup location is invalid.'], 200),
        ]);

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('The pickup location is invalid.');

        $this->makeConfiguredShiprocket()->createOrder($this->sampleIndiaOrder());
    }

    public function test_shiprocket_parses_the_webhook_on_sr_status_and_skips_na_scans(): void
    {
        $event = $this->makeConfiguredShiprocket()->parseWebhook($this->webhookRequest([
            'awb'               => '19041424751540',
            'current_status'    => 'IN TRANSIT',
            'order_id'          => '1373900_224-477',
            'sr_order_id'       => 705944082,
            'current_timestamp' => '23 05 2023 11:43:52',
            'scans'             => [
                ['sr-status' => 'NA', 'sr-status-label' => '', 'status' => 'X-UCI'],
                ['sr-status' => '42', 'sr-status-label' => 'PICKED UP', 'status' => 'X-PPOM'],
            ],
        ]));

        $this->assertSame('19041424751540', $event->consignmentId);
        $this->assertSame(ShipmentStatus::PickedUp, $event->status);
        $this->assertSame('224-477', $event->hostOrderReference);
    }

    public function test_shiprocket_webhook_verification_uses_the_x_api_key_header(): void
    {
        $shiprocket = $this->app->make(ShiprocketProvider::class);
        $shiprocket->setCredentials(['webhook_token' => 'sekret']);

        $this->assertFalse($shiprocket->verifyWebhook($this->webhookRequest(['awb' => 'x'])));

        $withKey = $this->webhookRequest(['awb' => 'x']);
        $withKey->headers->set('x-api-key', 'sekret');

        $this->assertTrue($shiprocket->verifyWebhook($withKey));
    }

    public function test_webhook_routing_resolves_shiprocket_by_neutral_slug_or_id(): void
    {
        $this->assertSame('logistics-in', $this->app->make(ShiprocketProvider::class)->webhookSlug());
        $this->assertSame('pathao', $this->app->make(PathaoProvider::class)->webhookSlug());

        $registry = $this->app->make(ProviderRegistry::class);

        $this->assertSame('shiprocket', $registry->resolveByRouteKey('logistics-in'));
        $this->assertSame('shiprocket', $registry->resolveByRouteKey('shiprocket'));
        $this->assertSame('pathao', $registry->resolveByRouteKey('pathao'));
        $this->assertNull($registry->resolveByRouteKey('nope'));
    }

    public function test_leopards_advertises_the_roles_it_implements(): void
    {
        $leopards = $this->app->make(LeopardsProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $leopards);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $leopards);
        $this->assertInstanceOf(TracksOrders::class, $leopards);
        $this->assertInstanceOf(CancelsOrders::class, $leopards);
        $this->assertInstanceOf(ResolvesLocations::class, $leopards);

        $this->assertNotInstanceOf(CalculatesPrice::class, $leopards);
        $this->assertNotInstanceOf(EstimatesDeliveryCharge::class, $leopards);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $leopards);
        $this->assertNotInstanceOf(ProvidesStores::class, $leopards);
        $this->assertNotInstanceOf(CollectsCod::class, $leopards);
        $this->assertNotInstanceOf(UpdatesOrders::class, $leopards);
    }

    public function test_leopards_declares_the_catalog_city_address_mode(): void
    {
        $leopards = $this->app->make(LeopardsProvider::class);

        $this->assertSame('catalog', $leopards->addressMode());
        $this->assertSame(['city'], $leopards->locationLevels());
        $this->assertSame([], $leopards->addressFields());
        $this->assertSame([], $leopards->getZones('789'));
        $this->assertSame([], $leopards->getAreas('789'));
    }

    public function test_leopards_books_in_grams_with_credentials_and_self_shipper(): void
    {
        Http::fake([
            '*/bookPacket/*' => Http::response(['status' => 1, 'track_number' => 'LE1234567'], 200),
        ]);

        $result = $this->makeConfiguredLeopards()->createOrder($this->sampleLeopardsOrder());

        $this->assertSame('LE1234567', $result->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $result->status);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['api_key'] ?? null) === 'key-abc'
                && ($body['api_password'] ?? null) === 'pass-xyz'
                && ($body['enable_test_mode'] ?? null) === true
                && (int) ($body['booked_packet_weight'] ?? 0) === 2000
                && ($body['origin_city'] ?? null) === 'self'
                && ($body['shipment_name_eng'] ?? null) === 'self'
                && ($body['destination_city'] ?? null) === '789'
                && (int) ($body['booked_packet_collect_amount'] ?? -1) === 0;
        });
    }

    public function test_leopards_honors_explicit_shipper_overrides(): void
    {
        Http::fake(['*/bookPacket/*' => Http::response(['status' => 1, 'track_number' => 'LE9'], 200)]);

        $leopards = $this->app->make(LeopardsProvider::class);
        $leopards->setCredentials([
            'api_key'          => 'k',
            'api_password'     => 'p',
            'shipment_name'    => 'My Shop',
            'shipment_address' => '1 Mall Road, Lahore',
        ]);

        $leopards->createOrder($this->sampleLeopardsOrder());

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['shipment_name_eng'] ?? null) === 'My Shop'
                && ($body['shipment_address'] ?? null) === '1 Mall Road, Lahore'
                && ($body['shipment_email'] ?? null) === 'self';
        });
    }

    public function test_leopards_maps_cities_through_fallback_keys(): void
    {
        Http::fake([
            '*/getAllCities/*' => Http::response([
                'status'    => 1,
                'city_list' => [
                    ['id' => 789, 'name' => 'Lahore', 'is_origin' => 1, 'is_destination' => 1],
                    ['city_id' => 202, 'city_name' => 'Karachi'],
                ],
            ], 200),
        ]);

        $cities = $this->makeConfiguredLeopards()->getCities();

        $this->assertCount(2, $cities);
        $this->assertSame('789', $cities[0]->id);
        $this->assertSame('Lahore', $cities[0]->name);
        $this->assertSame('202', $cities[1]->id);
        $this->assertSame('Karachi', $cities[1]->name);
        $this->assertSame(1, $cities[0]->raw['is_destination']);
    }

    public function test_leopards_does_not_strict_validate_cn_and_treats_body_error_as_failure(): void
    {
        Http::fake(['*/cancelBookedPackets/*' => Http::response(['status' => 1], 200)]);

        $cancelled = $this->makeConfiguredLeopards()->cancelOrder('AB7');
        $this->assertSame(ShipmentStatus::Cancelled, $cancelled->status);

        Http::fake(['*/bookPacket/*' => Http::response(['status' => 0, 'error_msg' => 'Invalid destination city'], 200)]);

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('Invalid destination city');
        $this->makeConfiguredLeopards()->createOrder($this->sampleLeopardsOrder());
    }

    public function test_leopards_redacts_credentials_for_logging(): void
    {
        $redacted = LeopardsProvider::redactCredentials([
            'api_key'      => 'key-abc',
            'api_password' => 'pass-xyz',
            'origin_city'  => 'self',
        ]);

        $this->assertSame('***', $redacted['api_key']);
        $this->assertSame('***', $redacted['api_password']);
        $this->assertSame('self', $redacted['origin_city']);
    }

    public function test_leopards_simulation_returns_synthetic_data_without_http(): void
    {
        Http::fake();

        $leopards = $this->app->make(LeopardsProvider::class);
        $leopards->setCredentials(['api_key' => 'k', 'api_password' => 'p', 'enable_simulation' => '1']);
        $leopards->setEnvironment('sandbox');

        $cities = $leopards->getCities();
        $this->assertNotEmpty($cities);
        $this->assertSame('789', $cities[0]->id);

        $shipment = $leopards->createOrder($this->sampleLeopardsOrder());
        $this->assertStringStartsWith('SIM', $shipment->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);

        $this->assertNotEmpty($leopards->trackOrder($shipment->consignmentId));

        Http::assertNothingSent();
    }

    private function makeConfiguredLeopards(): LeopardsProvider
    {
        $leopards = $this->app->make(LeopardsProvider::class);
        $leopards->setCredentials(['api_key' => 'key-abc', 'api_password' => 'pass-xyz']);
        $leopards->setEnvironment('sandbox');

        return $leopards;
    }

    private function sampleLeopardsOrder(): OrderData
    {
        return new OrderData(
            hostOrderReference: 'ORD-12345',
            recipient: new RecipientData(
                name: 'John Doe',
                phone: '03001234567',
                address: '123 Main St, Lahore',
                cityId: '789',
                meta: ['email' => 'john@example.com'],
            ),
            codAmount: 0.0,
            weight: 2.0,
        );
    }

    private function makeConfiguredShiprocket(): ShiprocketProvider
    {
        $shiprocket = $this->app->make(ShiprocketProvider::class);
        $shiprocket->setCredentials([
            'email'           => 'apiuser@example.com',
            'password'        => 'secret',
            'pickup_location' => 'Primary',
            'origin_pincode'  => '110002',
        ]);

        return $shiprocket;
    }

    private function sampleIndiaOrder(): OrderData
    {
        return new OrderData(
            hostOrderReference: '224-477',
            recipient: new RecipientData(
                name: 'Naruto Uzumaki',
                phone: '9876543210',
                address: 'House 221B, Leaf Village',
                countryCode: 'IN',
                postalCode: '110002',
                cityName: 'New Delhi',
                stateProvince: 'Delhi',
                meta: ['email' => 'naruto@example.com'],
            ),
            codAmount: 0.0,
            weight: 2.5,
            items: [new ItemData(name: 'Kunai', quantity: 10, price: 900.0, sku: 'chakra123')],
        );
    }

    private function webhookRequest(array $payload): Request
    {
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_nepal_can_move_advertises_the_roles_it_implements(): void
    {
        $ncm = $this->app->make(NepalCanMoveProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $ncm);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $ncm);
        $this->assertInstanceOf(TracksOrders::class, $ncm);
        $this->assertInstanceOf(EstimatesDeliveryCharge::class, $ncm);
        $this->assertInstanceOf(CancelsOrders::class, $ncm);
        $this->assertInstanceOf(ResolvesLocations::class, $ncm);
        $this->assertInstanceOf(HandlesWebhooks::class, $ncm);
        $this->assertInstanceOf(HandlesBatchWebhooks::class, $ncm);

        $this->assertNotInstanceOf(ProvidesStores::class, $ncm);
        $this->assertNotInstanceOf(CollectsCod::class, $ncm);
        $this->assertNotInstanceOf(UpdatesOrders::class, $ncm);
        $this->assertNotInstanceOf(CalculatesPrice::class, $ncm);
    }

    public function test_nepal_can_move_declares_the_branch_address_mode(): void
    {
        $ncm = $this->app->make(NepalCanMoveProvider::class);

        $this->assertSame('branch', $ncm->addressMode());
        $this->assertSame([], $ncm->locationLevels());
        $this->assertSame([1, 2, 3, 4], array_keys($ncm->deliveryTypes()));
    }

    public function test_nepal_can_move_creates_a_flat_order_by_branch_names(): void
    {
        Http::fake([
            '*/order/create' => Http::response(['orderid' => 1001001, 'status' => 'Pending'], 200),
        ]);

        $result = $this->makeConfiguredNcm()->createOrder(new OrderData(
            hostOrderReference: 'ORDER-55',
            recipient: new RecipientData(
                name: 'Achyut Neupane',
                phone: '9800000000',
                address: 'Lakeside, Pokhara',
                sourceBranchName: 'KATHMANDU',
                sourceBranchId: '1',
                destinationBranchName: 'POKHARA',
                destinationBranchId: '5',
            ),
            codAmount: 1500.0,
            weight: 0.0,
            itemDescription: 'Books',
            meta: ['delivery_type' => 1],
        ));

        $this->assertSame('1001001', $result->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $result->status);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($request->header('Authorization')[0] ?? '') === 'Token ncm-token'
                && ($body['sourceBranch'] ?? null) === 'KATHMANDU'
                && ($body['destinationBranch'] ?? null) === 'POKHARA'
                && ($body['codCharge'] ?? null) === '1500'
                && ($body['deliveryType'] ?? null) === 'DoorToDoor'
                && ($body['package'] ?? null) === 'Books';
        });
    }

    public function test_nepal_can_move_verifies_the_webhook_secret_and_fans_out_over_order_ids(): void
    {
        $ncm = $this->makeConfiguredNcm();
        $body = json_encode(['orderIds' => [123, 124], 'event' => 'Delivered']);

        $unsigned = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $unsigned->headers->set('Content-Type', 'application/json');
        $this->assertFalse($ncm->verifyWebhook($unsigned));

        $signed = Request::create('/webhook?secret=ncm-secret', 'POST', [], [], [], [], $body);
        $signed->headers->set('Content-Type', 'application/json');
        $this->assertTrue($ncm->verifyWebhook($signed));

        $events = $ncm->parseWebhookEvents($signed);

        $this->assertCount(2, $events);
        $this->assertSame('123', $events[0]->consignmentId);
        $this->assertSame('124', $events[1]->consignmentId);
        $this->assertSame(ShipmentStatus::Delivered, $events[0]->status);
    }

    private function makeConfiguredNcm(): NepalCanMoveProvider
    {
        $ncm = $this->app->make(NepalCanMoveProvider::class);
        $ncm->setCredentials([
            'api_token'      => 'ncm-token',
            'source_branch'  => 'KATHMANDU',
            'package'        => 'Parcel',
            'webhook_secret' => 'ncm-secret',
        ]);

        return $ncm;
    }

    public function test_domex_advertises_the_roles_it_implements(): void
    {
        $domex = $this->app->make(DomexProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $domex);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $domex);
        $this->assertInstanceOf(TracksOrders::class, $domex);

        $this->assertNotInstanceOf(EstimatesDeliveryCharge::class, $domex);
        $this->assertNotInstanceOf(CalculatesPrice::class, $domex);
        $this->assertNotInstanceOf(ResolvesLocations::class, $domex);
        $this->assertNotInstanceOf(ProvidesStores::class, $domex);
        $this->assertNotInstanceOf(CollectsCod::class, $domex);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $domex);
        $this->assertNotInstanceOf(CancelsOrders::class, $domex);
        $this->assertNotInstanceOf(UpdatesOrders::class, $domex);
    }

    public function test_domex_declares_the_postal_address_mode(): void
    {
        $domex = $this->app->make(DomexProvider::class);

        $this->assertSame('postal', $domex->addressMode());
        $this->assertSame([], $domex->locationLevels());
        $this->assertSame(['city_name', 'postal_code'], array_column($domex->addressFields(), 'key'));
    }

    public function test_domex_sends_the_published_payload_preserving_the_typo_and_token_header(): void
    {
        Http::fake([
            '*/api/pickupData' => Http::response(['waybill' => 'DX10001'], 200),
        ]);

        $result = $this->makeConfiguredDomex()->createOrder($this->sampleDomexOrder());

        $this->assertSame('DX10001', $result->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $result->status);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($request->header('apiToken')[0] ?? null) === 'token-abc'
                && empty($request->header('Authorization'))
                && array_key_exists('senderAdddress', $body)
                && !array_key_exists('senderAddress', $body)
                && ($body['senderAdddress'] ?? null) === '10 Galle Road'
                && ($body['receiverCity'] ?? null) === 'Kandy'
                && ($body['paymentMode'] ?? null) === 'COD'
                && ($body['pickupBranch'] ?? null) === 'Colombo';
        });
    }

    public function test_domex_throws_when_the_response_carries_no_waybill(): void
    {
        Http::fake([
            '*/api/pickupData' => Http::response(['message' => 'ok'], 200),
        ]);

        $this->expectException(CourierException::class);

        $this->makeConfiguredDomex()->createOrder($this->sampleDomexOrder());
    }

    public function test_domex_extracts_the_waybill_from_a_nested_data_node(): void
    {
        Http::fake([
            '*/api/pickupData' => Http::response(['data' => ['way_bill_number' => 'DX20002']], 200),
        ]);

        $result = $this->makeConfiguredDomex()->createOrder($this->sampleDomexOrder());

        $this->assertSame('DX20002', $result->consignmentId);
    }

    public function test_domex_tracking_sends_the_snake_case_waybill_field(): void
    {
        Http::fake([
            '*/api/orderTracking' => Http::response(['tracking' => [
                ['status' => 'In Transit', 'date' => '2026-07-20 10:00:00', 'location' => 'Colombo Hub'],
                ['status' => 'Delivered', 'date' => '2026-07-21 14:00:00', 'location' => 'Kandy'],
            ]], 200),
        ]);

        $events = $this->makeConfiguredDomex()->trackOrder('DX10001');

        $this->assertCount(2, $events);
        $this->assertSame(ShipmentStatus::InTransit, $events[0]->status);
        $this->assertSame(ShipmentStatus::Delivered, $events[1]->status);
        $this->assertSame('Kandy', $events[1]->location);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return str_contains((string) $request->url(), '/api/orderTracking')
                && ($body['way_bill_numbers'] ?? null) === 'DX10001'
                && !array_key_exists('wayBillNumbers', $body);
        });
    }

    public function test_domex_sandbox_simulates_create_and_track_without_http(): void
    {
        Http::fake();

        $domex = $this->app->make(DomexProvider::class);
        $domex->setCredentials(['api_token' => 'token-abc', 'sender_name' => 'Shop']);
        $domex->setEnvironment('sandbox');

        $shipment = $domex->createOrder($this->sampleDomexOrder());

        $this->assertStringStartsWith('DX-SBX-', $shipment->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertTrue($shipment->raw['simulated']);

        $events = $domex->trackOrder($shipment->consignmentId);

        $this->assertNotEmpty($events);
        $this->assertTrue($events[0]->raw['simulated']);

        Http::assertNothingSent();
    }

    private function makeConfiguredDomex(): DomexProvider
    {
        $domex = $this->app->make(DomexProvider::class);
        $domex->setCredentials([
            'api_token'     => 'token-abc',
            'sender_name'   => 'Shop',
            'sender_address' => '10 Galle Road',
            'sender_city'   => 'Colombo',
            'sender_phone'  => '0117759759',
            'sender_email'  => 'shop@example.com',
            'pickup_branch' => 'Colombo',
        ]);
        $domex->setEnvironment('live');

        return $domex;
    }

    private function sampleDomexOrder(): OrderData
    {
        return new OrderData(
            hostOrderReference: 'ORDER-55001',
            recipient: new RecipientData(
                name: 'Nimal Perera',
                phone: '0771234567',
                address: '25 Peradeniya Road',
                cityName: 'Kandy',
            ),
            codAmount: 1500.0,
            weight: 1.0,
        );
    }

    public function test_tcs_advertises_the_roles_it_implements(): void
    {
        $tcs = $this->app->make(TcsProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $tcs);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $tcs);
        $this->assertInstanceOf(TracksOrders::class, $tcs);
        $this->assertInstanceOf(CancelsOrders::class, $tcs);
        $this->assertInstanceOf(ResolvesLocations::class, $tcs);
        $this->assertInstanceOf(CollectsCod::class, $tcs);

        $this->assertNotInstanceOf(CalculatesPrice::class, $tcs);
        $this->assertNotInstanceOf(EstimatesDeliveryCharge::class, $tcs);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $tcs);
        $this->assertNotInstanceOf(HandlesBatchWebhooks::class, $tcs);
        $this->assertNotInstanceOf(UpdatesOrders::class, $tcs);
        $this->assertNotInstanceOf(ProvidesStores::class, $tcs);
    }

    public function test_tcs_declares_the_three_level_catalog_address_mode(): void
    {
        $tcs = $this->app->make(TcsProvider::class);

        $this->assertSame('catalog', $tcs->addressMode());
        $this->assertSame(['city', 'zone', 'area'], $tcs->locationLevels());
        $this->assertSame([], $tcs->addressFields());
        $this->assertSame(['O' => 'Overnight', 'C' => 'Second Day', 'V' => 'Overland Economy'], $tcs->deliveryTypes());
        $this->assertSame([
            'sandbox' => 'https://devconnect.tcscourier.com',
            'live'    => 'https://ociconnect.tcscourier.com',
        ], $tcs->baseUrls());
    }

    public function test_tcs_books_with_the_bearer_token_cost_center_and_split_consignee_name(): void
    {
        $this->fakeTcsAuth([
            '*/booking/create' => Http::response([
                'response'     => 'SUCCESS',
                'consignmentNo' => '99210301520',
                'code'         => '200',
                'status'       => true,
                'message'      => 'success',
            ], 200),
        ]);

        $result = $this->makeConfiguredTcs()->createOrder($this->sampleTcsOrder());

        $this->assertSame('99210301520', $result->consignmentId);
        $this->assertSame('99210301520', $result->trackingCode);
        $this->assertSame(ShipmentStatus::Pending, $result->status);
        $this->assertSame('ORDER-9001', $result->hostOrderReference);

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/booking/create')) {
                return false;
            }

            $body = json_decode($request->body(), true);

            return ($request->header('Authorization')[0] ?? '') === 'Bearer tcs-access-token'
                && ($body['accesstoken'] ?? null) === 'tcs-access-token'
                && ($body['shipperinfo']['tcsaccount'] ?? null) === '04011K1'
                && ($body['shipperinfo']['cityname'] ?? null) === 'Karachi'
                && ($body['consigneeinfo']['firstname'] ?? null) === 'Muhammad'
                && ($body['consigneeinfo']['middlename'] ?? null) === 'Ali'
                && ($body['consigneeinfo']['lastname'] ?? null) === 'Khan'
                && ($body['consigneeinfo']['citycode'] ?? null) === 'KHI'
                && ($body['consigneeinfo']['cityname'] ?? null) === 'Karachi'
                && !array_key_exists('areacode', $body['consigneeinfo'])
                && !array_key_exists('blockcode', $body['consigneeinfo'])
                && ($body['shipmentinfo']['costcentercode'] ?? null) === 'Test-01'
                && ($body['shipmentinfo']['referenceno'] ?? null) === 'ORDER-9001'
                && ($body['shipmentinfo']['servicecode'] ?? null) === 'O'
                && ($body['shipmentinfo']['currency'] ?? null) === 'PKR'
                && ($body['shipmentinfo']['fragile'] ?? null) === false;
        });
    }

    public function test_tcs_repeats_a_single_word_name_into_the_required_middle_name(): void
    {
        $this->fakeTcsAuth(['*/booking/create' => Http::response(['consignmentNo' => '99210301521', 'status' => true], 200)]);

        $this->makeConfiguredTcs()->createOrder($this->sampleTcsOrder(name: 'Imrooz'));

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/booking/create')) {
                return false;
            }

            $consignee = json_decode($request->body(), true)['consigneeinfo'];

            return ($consignee['firstname'] ?? null) === 'Imrooz'
                && ($consignee['middlename'] ?? null) === 'Imrooz'
                && !array_key_exists('lastname', $consignee);
        });
    }

    public function test_tcs_floors_weight_at_half_a_kilogram_without_converting_units(): void
    {
        $this->fakeTcsAuth(['*/booking/create' => Http::response(['consignmentNo' => '99210301522', 'status' => true], 200)]);

        $tcs = $this->makeConfiguredTcs();
        $tcs->createOrder($this->sampleTcsOrder(reference: 'ORDER-LIGHT', weight: 0.2));
        $tcs->createOrder($this->sampleTcsOrder(reference: 'ORDER-HEAVY', weight: 2.5));

        $weights = [];

        Http::assertSent(function ($request) use (&$weights): bool {
            if (str_contains($request->url(), '/booking/create')) {
                $body = json_decode($request->body(), true);
                $weights[$body['shipmentinfo']['referenceno']] = $body['shipmentinfo']['weightinkg'];
            }

            return true;
        });

        $this->assertSame(0.5, $weights['ORDER-LIGHT']);
        $this->assertSame(2.5, $weights['ORDER-HEAVY']);
    }

    public function test_tcs_sends_zero_cod_for_prepaid_and_refuses_amounts_over_the_ceiling(): void
    {
        $this->fakeTcsAuth(['*/booking/create' => Http::response(['consignmentNo' => '99210301523', 'status' => true], 200)]);

        $tcs = $this->makeConfiguredTcs();

        $this->assertThrowsCourierException(
            fn () => $tcs->createOrder($this->sampleTcsOrder(codAmount: 250001.0)),
            '250,000',
        );

        Http::assertNothingSent();

        $tcs->createOrder($this->sampleTcsOrder(codAmount: 0.0));

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/booking/create')) {
                return false;
            }

            $shipment = json_decode($request->body(), true)['shipmentinfo'];

            return (float) $shipment['codamount'] === 0.0 && (float) $shipment['weightinkg'] === 1.0;
        });
    }

    public function test_tcs_maps_order_items_onto_skus(): void
    {
        $this->fakeTcsAuth(['*/booking/create' => Http::response(['consignmentNo' => '99210301524', 'status' => true], 200)]);

        $this->makeConfiguredTcs()->createOrder($this->sampleTcsOrder(items: [
            new ItemData(name: 'Kurta', quantity: 2, price: 1800.0, weight: 0.3, sku: 'KRT-1'),
        ]));

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/booking/create')) {
                return false;
            }

            $skus = json_decode($request->body(), true)['shipmentinfo']['skus'];

            return count($skus) === 1
                && $skus[0]['description'] === 'Kurta'
                && $skus[0]['quantity'] === 2
                && $skus[0]['weight'] === 0.5
                && $skus[0]['uom'] === 'KG'
                && $skus[0]['unitprice'] === 1800;
        });
    }

    public function test_tcs_treats_an_error_array_on_http_200_as_a_failure(): void
    {
        $this->fakeTcsAuth([
            '*/booking/create' => Http::response([
                'error'   => [['errorname' => 'Cost center does not exist']],
                'message' => 'Summary of error',
            ], 200),
        ]);

        $this->assertThrowsCourierException(
            fn () => $this->makeConfiguredTcs()->createOrder($this->sampleTcsOrder()),
            'Cost center does not exist',
        );
    }

    public function test_tcs_resolves_the_city_area_block_hierarchy_and_reuses_it_when_booking(): void
    {
        $this->fakeTcsAuth([
            '*setup/citylistbycountry*' => Http::response(['message' => 'SUCCESS', 'data' => [
                ['citycode' => 'KHI', 'cityname' => 'Karachi'],
                ['citycode' => 'LHE', 'cityname' => 'Lahore'],
            ]], 200),
            '*setup/areacode*' => Http::response(['message' => 'SUCCESS', 'data' => [
                ['areaid' => 'R80306631', 'citycode' => 'KHI', 'areacode' => 'KHI00001', 'areaname' => 'Gulshan'],
            ]], 200),
            '*setup/blockcode*' => Http::response(['message' => 'SUCCESS', 'data' => [
                ['blockid' => 'B1', 'areacode' => 'KHI00001', 'blockcode' => 'KHIB0006', 'blockname' => 'Gulshan Block 6'],
            ]], 200),
            '*/booking/create' => Http::response(['consignmentNo' => '99210301525', 'status' => true], 200),
        ]);

        $tcs = $this->makeConfiguredTcs();

        $cities = $tcs->getCities();
        $this->assertCount(2, $cities);
        $this->assertSame('KHI', $cities[0]->id);
        $this->assertSame('Karachi', $cities[0]->name);
        $this->assertSame('city', $cities[0]->level);

        $zones = $tcs->getZones('KHI');
        $this->assertSame('KHI00001', $zones[0]->id);
        $this->assertSame('Gulshan', $zones[0]->name);
        $this->assertSame('zone', $zones[0]->level);
        $this->assertSame('KHI', $zones[0]->parentId);

        $areas = $tcs->getAreas('KHI00001');
        $this->assertSame('KHIB0006', $areas[0]->id);
        $this->assertSame('Gulshan Block 6', $areas[0]->name);
        $this->assertSame('area', $areas[0]->level);

        $tcs->createOrder(new OrderData(
            hostOrderReference: 'ORDER-9002',
            recipient: new RecipientData(
                name: 'Imrooz Haider',
                phone: '03451234567',
                address: '25 Main Street',
                cityId: 'KHI',
                zoneId: 'KHI00001',
                areaId: 'KHIB0006',
            ),
            codAmount: 2500.0,
            weight: 1.0,
        ));

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/booking/create')) {
                return false;
            }

            $consignee = json_decode($request->body(), true)['consigneeinfo'];

            return $consignee['cityname'] === 'Karachi'
                && $consignee['areacode'] === 'KHI00001'
                && $consignee['areaname'] === 'Gulshan'
                && $consignee['blockcode'] === 'KHIB0006'
                && $consignee['blockname'] === 'Gulshan Block 6';
        });

        $this->assertSame(1, $this->sentCount('setup/citylistbycountry'));
        $this->assertSame(1, $this->sentCount('setup/areacode'));
    }

    public function test_tcs_tracking_maps_checkpoints_and_reads_status_from_the_delivery_code(): void
    {
        $this->fakeTcsAuth(['*GetDynamicTrackDetail*' => Http::response([
            'message'      => 'SUCCESS',
            'shipmentinfo' => [['consignmentno' => '779412326902', 'origin' => 'LAHORE', 'destination' => 'BAGH']],
            'deliveryinfo' => [['consignmentno' => '779412326902', 'station' => 'BAGH', 'status' => 'Delivered', 'code' => 'OK']],
            'checkpoints'  => [
                ['datetime' => 'Thursday Oct 17, 2024 12:58', 'recievedby' => 'IMROOZ', 'status' => 'Shipment Delivered'],
                ['datetime' => 'Thursday Oct 17, 2024 09:35', 'recievedby' => null, 'status' => 'Out For Delivery'],
                ['datetime' => 'Wednesday Oct 16, 2024 10:52', 'recievedby' => 'BAGH', 'status' => 'Arrived at TCS Facility'],
            ],
        ], 200)]);

        $tcs = $this->makeConfiguredTcs();
        $events = $tcs->trackOrder('779412326902');

        $this->assertCount(3, $events);
        $this->assertSame(ShipmentStatus::Delivered, $events[0]->status);
        $this->assertSame('IMROOZ', $events[0]->location);
        $this->assertSame('Thursday Oct 17, 2024 12:58', $events[0]->occurredAt);
        $this->assertSame(ShipmentStatus::OutForDelivery, $events[1]->status);
        $this->assertSame(ShipmentStatus::InTransit, $events[2]->status);

        $this->assertSame(ShipmentStatus::Delivered, $tcs->getOrderStatus('779412326902')->status);

        Http::assertSent(fn ($request): bool => !str_contains($request->url(), 'GetDynamicTrackDetail')
            || (json_decode($request->body(), true)['consignee'] ?? null) === ['779412326902']);
    }

    public function test_tcs_tracking_failure_surfaces_the_shipment_summary(): void
    {
        $this->fakeTcsAuth(['*GetDynamicTrackDetail*' => Http::response([
            'shipmentinfo'    => null,
            'deliveryinfo'    => null,
            'checkpoints'     => null,
            'shipmentsummary' => 'No Data Found/Invalid CN',
            'message'         => 'FAIL',
        ], 200)]);

        $this->assertThrowsCourierException(
            fn () => $this->makeConfiguredTcs()->trackOrder('000000000000'),
            'No Data Found/Invalid CN',
        );
    }

    public function test_tcs_cancels_on_a_success_message_and_throws_otherwise(): void
    {
        $this->fakeTcsAuth(['*/booking/cancel' => Http::sequence()
            ->push(['message' => 'SUCCESS'], 200)
            ->push(['message' => 'Failure: No Record Founds'], 200)]);

        $tcs = $this->makeConfiguredTcs();
        $cancelled = $tcs->cancelOrder('173000008151');

        $this->assertSame(ShipmentStatus::Cancelled, $cancelled->status);
        $this->assertSame('173000008151', $cancelled->consignmentId);

        $this->assertThrowsCourierException(
            fn () => $tcs->cancelOrder('173000008151'),
            'Failure: No Record Founds',
        );
    }

    public function test_tcs_reads_cod_settlement_and_degrades_when_no_record_exists(): void
    {
        $this->fakeTcsAuth(['*Payment/status*' => Http::sequence()
            ->push(['data' => [[
                'cn status'      => 'Delivered',
                'payment status' => 'Paid',
                'amount paid'    => 2057,
                'payment date'   => '2024-08-14',
            ]]], 200)
            ->push(['data' => []], 200)]);

        $tcs = $this->makeConfiguredTcs();
        $cod = $tcs->getCodCollection('779412314605');

        $this->assertSame(2057.0, $cod->collectedAmount);
        $this->assertSame('Paid', $cod->status);
        $this->assertSame('2024-08-14', $cod->settledAt);
        $this->assertSame('779412314605', $cod->consignmentId);

        $pending = $tcs->getCodCollection('779412314606');

        $this->assertSame(0.0, $pending->collectedAmount);
        $this->assertSame('unsettled', $pending->status);
    }

    public function test_tcs_prefers_client_credentials_and_falls_back_to_the_portal_login(): void
    {
        $this->fakeTcsAuth(['*/booking/cancel' => Http::response(['message' => 'SUCCESS'], 200)]);

        $this->makeConfiguredTcs()->cancelOrder('173000008151');

        $this->assertSame(1, $this->sentCount('/auth/api/auth'));
        $this->assertSame(0, $this->sentCount('authentication/token'));

        Http::assertSent(fn ($request): bool => !str_contains($request->url(), '/auth/api/auth')
            || (json_decode($request->body(), true)['clientid'] ?? null) === '205659575');

        $portalOnly = $this->app->make(TcsProvider::class);
        $portalOnly->setCredentials($this->tcsCredentials(['client_id' => '', 'client_secret' => '', 'username' => 'abc', 'password' => '123']));
        $portalOnly->setEnvironment('sandbox');
        $portalOnly->cancelOrder('173000008151');

        $this->assertSame(1, $this->sentCount('authentication/token'));
    }

    public function test_tcs_caches_the_token_and_re_authenticates_once_on_a_rejected_call(): void
    {
        $this->fakeTcsAuth(['*/booking/cancel' => Http::sequence()
            ->push(['message' => 'SUCCESS'], 200)
            ->push(['message' => 'SUCCESS'], 200)
            ->push(['message' => 'Unauthorized'], 401)
            ->push(['message' => 'SUCCESS'], 200)]);

        $tcs = $this->makeConfiguredTcs();
        $tcs->cancelOrder('173000008151');
        $tcs->cancelOrder('173000008152');

        $this->assertSame(1, $this->sentCount('/auth/api/auth'));
        $this->assertSame(2, $this->sentCount('/booking/cancel'));

        $tcs->cancelOrder('173000008153');

        $this->assertSame(2, $this->sentCount('/auth/api/auth'));
        $this->assertSame(4, $this->sentCount('/booking/cancel'));
    }

    public function test_tcs_without_any_credential_pair_never_reaches_the_network(): void
    {
        Http::fake();

        $tcs = $this->app->make(TcsProvider::class);
        $tcs->setCredentials(['tcs_account' => '04011K1', 'cost_center_code' => 'Test-01']);

        $this->assertThrowsCourierException(
            fn () => $tcs->cancelOrder('173000008151'),
            'Client ID and Client Secret',
        );

        Http::assertNothingSent();
    }

    public function test_tcs_sandbox_simulation_returns_synthetic_data_without_http(): void
    {
        Http::fake();

        $tcs = $this->app->make(TcsProvider::class);
        $tcs->setCredentials($this->tcsCredentials(['enable_simulation' => '1']));
        $tcs->setEnvironment('sandbox');

        $cities = $tcs->getCities();
        $this->assertSame('KHI', $cities[0]->id);
        $this->assertNotEmpty($tcs->getZones('KHI'));
        $this->assertNotEmpty($tcs->getAreas('KHI00001'));

        $shipment = $tcs->createOrder($this->sampleTcsOrder());
        $this->assertStringStartsWith('TCS-SBX-', $shipment->consignmentId);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertTrue($shipment->raw['simulated']);

        $this->assertNotEmpty($tcs->trackOrder($shipment->consignmentId));
        $this->assertSame(ShipmentStatus::Cancelled, $tcs->cancelOrder($shipment->consignmentId)->status);
        $this->assertSame('unsettled', $tcs->getCodCollection($shipment->consignmentId)->status);

        Http::assertNothingSent();
    }

    public function test_tcs_simulation_is_ignored_on_the_live_environment(): void
    {
        $this->fakeTcsAuth(['*/booking/create' => Http::response(['consignmentNo' => '99210301526', 'status' => true], 200)]);

        $tcs = $this->app->make(TcsProvider::class);
        $tcs->setCredentials($this->tcsCredentials(['enable_simulation' => '1']));
        $tcs->setEnvironment('live');

        $result = $tcs->createOrder($this->sampleTcsOrder());

        $this->assertSame('99210301526', $result->consignmentId);

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://ociconnect.tcscourier.com'));
    }

    public function test_tcs_redacts_credentials_for_logging(): void
    {
        $redacted = TcsProvider::redactCredentials([
            'client_id'     => '205659575',
            'client_secret' => 'YWxuYXNlZWJkYWlyeWZvb2RAdGNzYm9vaw==',
            'password'      => '123',
            'tcs_account'   => '04011K1',
        ]);

        $this->assertSame('***', $redacted['client_secret']);
        $this->assertSame('***', $redacted['password']);
        $this->assertSame('205659575', $redacted['client_id']);
        $this->assertSame('04011K1', $redacted['tcs_account']);
    }

    private function fakeTcsAuth(array $stubs = []): void
    {
        Http::fake([
            '*/auth/api/auth' => Http::response([
                'result' => ['accessToken' => 'tcs-access-token', 'expiry' => '2099-01-04T05:08:47Z', 'userInformation' => null],
                'status' => true,
                'code'   => '0200',
            ], 200),
            '*authentication/token' => Http::response([
                'accesstoken' => 'tcs-portal-token',
                'expiry'      => '2099-06-08T09:01:25.07Z',
                'message'     => 'success',
            ], 200),
            ...$stubs,
        ]);
    }

    private function sentCount(string $needle): int
    {
        return Http::recorded(fn ($request): bool => str_contains($request->url(), $needle))->count();
    }

    private function assertThrowsCourierException(callable $call, string $expectedMessage): void
    {
        try {
            $call();
        } catch (CourierException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());

            return;
        }

        $this->fail("Expected a CourierException containing [{$expectedMessage}].");
    }

    private function makeConfiguredTcs(): TcsProvider
    {
        $tcs = $this->app->make(TcsProvider::class);
        $tcs->setCredentials($this->tcsCredentials());
        $tcs->setEnvironment('sandbox');

        return $tcs;
    }

    private function tcsCredentials(array $overrides = []): array
    {
        return array_merge([
            'client_id'         => '205659575',
            'client_secret'     => 'YWxuYXNlZWJkYWlyeWZvb2RAdGNzYm9vaw==',
            'tcs_account'       => '04011K1',
            'cost_center_code'  => 'Test-01',
            'shipper_name'      => 'Test Shop',
            'shipper_address'   => 'Test address 1',
            'shipper_city_code' => 'KHI',
            'shipper_city_name' => 'Karachi',
            'shipper_mobile'    => '03451234567',
        ], $overrides);
    }

    private function sampleTcsOrder(
        string $reference = 'ORDER-9001',
        string $name = 'Muhammad Ali Khan',
        float $codAmount = 2500.0,
        float $weight = 1.0,
        array $items = [],
    ): OrderData {
        return new OrderData(
            hostOrderReference: $reference,
            recipient: new RecipientData(
                name: $name,
                phone: '03451234567',
                address: '25 Peradeniya Road',
                cityId: 'KHI',
                cityName: 'Karachi',
                meta: ['email' => 'buyer@example.com'],
            ),
            codAmount: $codAmount,
            weight: $weight,
            items: $items,
        );
    }

    public function test_every_provider_labels_itself_with_its_service_area(): void
    {
        $registry = (array) config('courier.providers', []);

        foreach ($registry as $id => $class) {
            $driver = $this->app->make($class);

            $this->assertNotSame('', $driver->coverage(), "[{$id}] must declare the country it serves, or 'Multinational'.");
            $this->assertStringEndsWith(' ('.$driver->coverage().')', $driver->getLabel(), "[{$id}] label must end with its service area in brackets.");
            $this->assertStringNotContainsString('()', $driver->getLabel(), "[{$id}] must not render an empty bracket.");
        }
    }

    public function test_same_brand_markets_are_distinguishable_by_label(): void
    {
        $this->assertSame('Pathao (Bangladesh)', $this->app->make(PathaoProvider::class)->getLabel());
        $this->assertSame('Pathao (Nepal)', $this->app->make(PathaoNepalProvider::class)->getLabel());
        $this->assertSame('The Garuda Express (Nepal)', $this->app->make(GarudaExpressProvider::class)->getLabel());
        $this->assertSame('Lalamove (Multinational)', $this->app->make(LalamoveProvider::class)->getLabel());
    }

    public function test_every_provider_declares_the_countries_it_serves(): void
    {
        $registry = (array) config('courier.providers', []);
        $countries = CountryList::all();

        foreach ($registry as $id => $class) {
            $supported = $this->app->make($class)->supportedCountries();

            $this->assertNotEmpty($supported, "[{$id}] must declare at least one country it serves.");

            foreach ($supported as $code) {
                $this->assertArrayHasKey($code, $countries, "[{$id}] declares [{$code}], which is not an ISO-3166 alpha-2 code.");
            }
        }
    }

    public function test_a_driver_without_a_selection_falls_back_to_its_first_supported_country(): void
    {
        $lalamove = $this->app->make(LalamoveProvider::class);

        $this->assertSame('BD', $this->app->make(PathaoProvider::class)->country());
        $this->assertSame('NP', $this->app->make(PathaoNepalProvider::class)->country());
        $this->assertSame($lalamove->supportedCountries()[0], $lalamove->country());
    }

    public function test_selecting_an_unsupported_country_leaves_the_previous_selection_standing(): void
    {
        $pathao = $this->app->make(PathaoProvider::class);
        $pathao->setCountry('HK');

        $this->assertSame('BD', $pathao->country());

        $lalamove = $this->app->make(LalamoveProvider::class);
        $lalamove->setCountry('HK');
        $lalamove->setCountry('ZZ');
        $this->assertSame('HK', $lalamove->country());

        $lalamove->setCountry(null);
        $this->assertSame('HK', $lalamove->country());
    }

    public function test_a_multinational_label_names_its_market_once_a_country_is_selected(): void
    {
        $lalamove = $this->app->make(LalamoveProvider::class);
        $this->assertSame('Lalamove (Multinational)', $lalamove->getLabel());
        $lalamove->setCountry('BD');
        $this->assertSame('Lalamove (Bangladesh)', $lalamove->getLabel());

        $dhl = $this->app->make(DhlProvider::class);
        $this->assertSame('DHL Express (Multinational)', $dhl->getLabel());
        $dhl->setCountry('AE');
        $this->assertSame('DHL Express (United Arab Emirates)', $dhl->getLabel());

        $aramex = $this->app->make(AramexProvider::class);
        $this->assertSame('Aramex (Multinational)', $aramex->getLabel());
        $aramex->setCountry('BD');
        $this->assertSame('Aramex (Bangladesh)', $aramex->getLabel());
    }

    public function test_every_multinational_driver_profiles_every_country_it_advertises(): void
    {
        foreach ([LalamoveProvider::class, AramexProvider::class] as $class) {
            $driver = $this->app->make($class);
            $profiles = $driver->countryProfiles();

            foreach ($driver->supportedCountries() as $code) {
                $this->assertArrayHasKey($code, $profiles, "[{$driver->getName()}] advertises [{$code}] with no country profile.");
                $this->assertArrayHasKey('currency', $profiles[$code], "[{$driver->getName()}] profile for [{$code}] declares no currency.");
            }
        }
    }

    public function test_lalamove_quotes_against_the_selected_market(): void
    {
        Http::fake(['*/v3/quotations' => Http::response(['data' => ['priceBreakdown' => ['total' => '124']]], 201)]);

        $quote = new QuoteData(weight: 1.0, toLatitude: '23.80', toLongitude: '90.41');

        $bangladesh = $this->makeConfiguredLalamove('BD')->getDeliveryCharges($quote);
        $hongKong = $this->makeConfiguredLalamove('HK')->getDeliveryCharges($quote);

        $this->assertSame('BDT', $bangladesh->currency);
        $this->assertSame('HKD', $hongKong->currency);

        $sent = Http::recorded()->map(static fn (array $pair) => $pair[0]);

        $this->assertSame('BD', $sent[0]->header('Market')[0]);
        $this->assertSame('HK', $sent[1]->header('Market')[0]);
        $this->assertSame('en_BD', json_decode($sent[0]->body(), true)['data']['language']);
        $this->assertSame('en_HK', json_decode($sent[1]->body(), true)['data']['language']);
    }

    public function test_dhl_rates_originate_from_the_selected_country(): void
    {
        Http::fake(['*/rates*' => Http::response(['products' => [['totalPrice' => [['price' => 12.5, 'priceCurrency' => 'AED']]]]], 200)]);

        $this->makeConfiguredDhl('AE')->getDeliveryCharges(
            new QuoteData(weight: 1.0, toCountryCode: 'BD', toPostalCode: '1207', toCityName: 'Dhaka'),
        );

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);

            return ($query['originCountryCode'] ?? null) === 'AE'
                && ($query['destinationCountryCode'] ?? null) === 'BD'
                && ($query['isCustomsDeclarable'] ?? null) === 'true';
        });
    }

    public function test_dhl_ships_domestically_when_origin_and_destination_share_a_country(): void
    {
        Http::fake(['*/shipments' => Http::response(['shipmentTrackingNumber' => 'DHL-1'], 201)]);

        $this->makeConfiguredDhl('BD')->createOrder($this->sampleDhlOrder('BD'));

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['productCode'] ?? null) === 'N'
                && ($body['content']['isCustomsDeclarable'] ?? null) === false
                && ($body['content']['declaredValueCurrency'] ?? null) === 'BDT'
                && ($body['customerDetails']['shipperDetails']['postalAddress']['countryCode'] ?? null) === 'BD';
        });
    }

    public function test_dhl_falls_back_to_the_default_profile_for_an_untabulated_country(): void
    {
        Http::fake(['*/shipments' => Http::response(['shipmentTrackingNumber' => 'DHL-2'], 201)]);

        $this->makeConfiguredDhl('IS')->createOrder($this->sampleDhlOrder('BD'));

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['productCode'] ?? null) === 'P'
                && ($body['content']['isCustomsDeclarable'] ?? null) === true
                && ($body['content']['declaredValueCurrency'] ?? null) === 'USD';
        });
    }

    public function test_aramex_bills_cod_in_the_selected_country_currency_from_its_own_station(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors' => false,
                'Shipments' => [['ID' => '556677', 'HasErrors' => false, 'Notifications' => []]],
            ], 200),
        ]);

        $this->makeConfiguredAramex('BD')->createOrder($this->sampleAramexCodOrder());

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);
            $details = $body['Shipments'][0]['Details'] ?? [];

            return ($body['ClientInfo']['AccountCountryCode'] ?? null) === 'BD'
                && ($body['ClientInfo']['AccountEntity'] ?? null) === 'DAC'
                && ($details['CashOnDeliveryAmount']['CurrencyCode'] ?? null) === 'BDT'
                && ($details['CustomsValueAmount']['CurrencyCode'] ?? null) === 'BDT'
                && ($details['ProductGroup'] ?? null) === 'DOM'
                && ($body['Shipments'][0]['Shipper']['PartyAddress']['CountryCode'] ?? null) === 'BD';
        });
    }

    public function test_lalamove_offers_the_selected_markets_vehicles_as_per_order_services(): void
    {
        $lalamove = $this->app->make(LalamoveProvider::class);

        $lalamove->setCountry('BD');
        $this->assertSame(['MOTORCYCLE', 'CAR', 'VAN', 'TRUCK'], array_keys($lalamove->deliveryTypes()));

        $lalamove->setCountry('HK');
        $this->assertContains('WALKER', array_keys($lalamove->deliveryTypes()));
    }

    public function test_lalamove_books_the_vehicle_chosen_on_the_order(): void
    {
        Http::fake([
            '*/v3/quotations' => Http::response(['data' => ['quotationId' => 'q1', 'stops' => [['stopId' => 's1'], ['stopId' => 's2']]]], 201),
            '*/v3/orders'     => Http::response(['data' => ['orderId' => 'LM-1', 'status' => 'ASSIGNING_DRIVER']], 201),
        ]);

        $order = $this->sampleDhlOrder('HK');
        $chosen = new OrderData(
            hostOrderReference: $order->hostOrderReference,
            recipient: $order->recipient,
            codAmount: $order->codAmount,
            weight: $order->weight,
            meta: ['delivery_type' => 'WALKER'],
        );

        $this->makeConfiguredLalamove('HK')->createOrder($chosen);

        $quotation = Http::recorded()->map(static fn (array $pair) => $pair[0])
            ->first(static fn ($request): bool => str_ends_with($request->url(), '/v3/quotations'));

        $this->assertSame('WALKER', json_decode($quotation->body(), true)['data']['serviceType']);
    }

    public function test_aramex_and_tcs_prefer_the_per_order_service_over_the_configured_default(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors' => false,
                'Shipments' => [['ID' => '778899', 'HasErrors' => false, 'Notifications' => []]],
            ], 200),
        ]);

        $order = $this->sampleAramexCodOrder();
        $economy = new OrderData(
            hostOrderReference: $order->hostOrderReference,
            recipient: $order->recipient,
            codAmount: $order->codAmount,
            weight: $order->weight,
            meta: ['delivery_type' => 'EPX'],
        );

        $this->makeConfiguredAramex('BD')->createOrder($economy);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['Shipments'][0]['Details']['ProductType'] ?? null) === 'EPX';
        });

        $tcs = $this->app->make(TcsProvider::class);
        $this->assertSame(['O', 'C', 'V'], array_keys($tcs->deliveryTypes()));
    }

    public function test_an_unknown_per_order_service_falls_back_instead_of_reaching_the_carrier(): void
    {
        $lalamove = $this->makeConfiguredLalamove('BD');
        $reflected = new ReflectionMethod($lalamove, 'serviceType');

        $this->assertSame('MOTORCYCLE', $reflected->invoke($lalamove, 'WALKER'));
        $this->assertSame('MOTORCYCLE', $reflected->invoke($lalamove, null));
    }

    public function test_the_registry_seeds_the_country_from_the_legacy_market_credential(): void
    {
        $this->createProviderSettingsTable();

        CourierProviderSetting::create([
            'provider'    => 'lalamove',
            'is_active'   => true,
            'credentials' => ['market' => 'HK'],
        ]);

        $this->assertSame('HK', $this->app->make(ProviderRegistry::class)->driver('lalamove')->country());
    }

    public function test_a_stored_country_wins_over_the_legacy_credential(): void
    {
        $this->createProviderSettingsTable();

        CourierProviderSetting::create([
            'provider'    => 'lalamove',
            'is_active'   => true,
            'credentials' => ['market' => 'HK'],
            'settings'    => ['country' => 'BD'],
        ]);

        $this->assertSame('BD', $this->app->make(ProviderRegistry::class)->driver('lalamove')->country());
    }

    public function test_saving_one_setting_never_discards_the_other(): void
    {
        $this->createProviderSettingsTable();

        $this->saveCourierProvider(['provider' => 'dhl', 'is_enabled' => '1', 'environment' => 'live']);
        $this->saveCourierProvider(['provider' => 'dhl', 'is_enabled' => '1', 'country' => 'AE']);

        $this->assertSame(
            ['environment' => 'live', 'country' => 'AE'],
            CourierProviderSetting::query()->where('provider', 'dhl')->value('settings'),
        );

        $this->saveCourierProvider(['provider' => 'dhl', 'is_enabled' => '1', 'environment' => 'sandbox']);

        $this->assertSame(
            ['environment' => 'sandbox', 'country' => 'AE'],
            CourierProviderSetting::query()->where('provider', 'dhl')->value('settings'),
        );
    }

    private function makeConfiguredLalamove(string $country): LalamoveProvider
    {
        $lalamove = $this->app->make(LalamoveProvider::class);
        $lalamove->setCountry($country);
        $lalamove->setCredentials([
            'api_key'         => 'pk_test_key',
            'api_secret'      => 'sk_test_secret',
            'origin_latitude' => '23.78',
            'origin_longitude'=> '90.40',
            'origin_address'  => 'Origin',
            'sender_name'     => 'Sender',
            'sender_phone'    => '+8801700000000',
        ]);

        return $lalamove;
    }

    private function makeConfiguredDhl(string $country): DhlProvider
    {
        $dhl = $this->app->make(DhlProvider::class);
        $dhl->setCountry($country);
        $dhl->setCredentials([
            'api_username'         => 'apiuser',
            'api_password'         => 'apipass',
            'account_number'       => '123456789',
            'origin_postal_code'   => '1207',
            'origin_city_name'     => 'Dhaka',
            'origin_address_line1' => '15 ABC Street',
            'origin_company_name'  => 'Shop',
            'origin_full_name'     => 'Owner',
            'origin_phone'         => '5555555',
        ]);

        return $dhl;
    }

    private function sampleDhlOrder(string $destinationCountryCode): OrderData
    {
        return new OrderData(
            hostOrderReference: 'ORDER-20030',
            recipient: new RecipientData(
                name: 'Rahim',
                phone: '01700000000',
                address: '12 Gulshan Avenue',
                countryCode: $destinationCountryCode,
                postalCode: '1212',
                cityName: 'Dhaka',
            ),
            codAmount: 2500.0,
            weight: 1.2,
        );
    }

    private function sampleAramexCodOrder(): OrderData
    {
        return new OrderData(
            hostOrderReference: 'ORDER-20031',
            recipient: new RecipientData(
                name: 'Rahim',
                phone: '01700000000',
                address: '12 Gulshan Avenue',
                countryCode: 'BD',
                postalCode: '1212',
                cityName: 'Dhaka',
            ),
            codAmount: 2500.0,
            weight: 1.2,
        );
    }

    private function saveCourierProvider(array $payload): void
    {
        $request = SaveCourierProviderRequest::create('/admin/courier/config', 'PUT', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));
        $request->validateResolved();

        $this->app->make(CourierConfigService::class)->save($request);
    }

    private function createProviderSettingsTable(): void
    {
        Schema::dropIfExists('courier_provider_settings');

        (require base_path('Modules/Courier/database/migrations/2026_07_19_100001_create_courier_provider_settings_table.php'))->up();
        (require base_path('Modules/Courier/database/migrations/2026_07_29_100006_add_owner_to_courier_provider_settings_table.php'))->up();
    }

    public function test_pathao_nepal_advertises_the_roles_it_implements(): void
    {
        $nepal = $this->app->make(PathaoNepalProvider::class);

        $this->assertInstanceOf(PathaoProvider::class, $nepal);
        $this->assertInstanceOf(CreatesOrders::class, $nepal);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $nepal);
        $this->assertInstanceOf(ResolvesLocations::class, $nepal);
        $this->assertInstanceOf(ProvidesStores::class, $nepal);
        $this->assertInstanceOf(CalculatesPrice::class, $nepal);
        $this->assertInstanceOf(HandlesWebhooks::class, $nepal);

        $this->assertSame('catalog', $nepal->addressMode());
        $this->assertSame(['city', 'zone', 'area'], $nepal->locationLevels());
    }

    public function test_pathao_nepal_inherits_the_bangladesh_mapping_without_redeclaring_it(): void
    {
        $inherited = ['createOrder', 'getCities', 'getZones', 'getAreas', 'getStores', 'calculatePrice', 'verifyWebhook', 'parseWebhook', 'token', 'mapStatus'];

        foreach ($inherited as $method) {
            $this->assertSame(
                PathaoProvider::class,
                (new ReflectionMethod(PathaoNepalProvider::class, $method))->getDeclaringClass()->getName(),
                "PathaoNepalProvider must inherit [{$method}] rather than duplicate it.",
            );
        }
    }

    public function test_pathao_quotes_in_bdt_and_pathao_nepal_quotes_in_npr(): void
    {
        $this->fakePathaoPricePlan();

        $bangladesh = $this->makeConfiguredPathao(PathaoProvider::class);
        $nepal = $this->makeConfiguredPathao(PathaoNepalProvider::class);

        $this->assertSame('BDT', $bangladesh->calculatePrice(new QuoteData(weight: 1.0, toCityId: '1', toZoneId: '2'))->currency);
        $this->assertSame('NPR', $nepal->calculatePrice(new QuoteData(weight: 1.0, toCityId: '1', toZoneId: '2'))->currency);

        $this->assertSame('BDT', $bangladesh->currency());
        $this->assertSame('NPR', $nepal->currency());
        $this->assertSame(0.5, $nepal->minimumWeight());
    }

    public function test_pathao_nepal_base_url_override_replaces_only_the_overridden_environment(): void
    {
        $nepal = $this->app->make(PathaoNepalProvider::class);
        $defaults = $nepal->baseUrls();

        $this->assertStringContainsString('.np', $defaults['sandbox']);
        $this->assertStringContainsString('.np', $defaults['live']);

        $nepal->setCredentials(['live_base_url' => 'https://hermes.example.np/']);
        $overridden = $nepal->baseUrls();

        $this->assertSame('https://hermes.example.np', $overridden['live']);
        $this->assertSame($defaults['sandbox'], $overridden['sandbox']);
    }

    public function test_pathao_nepal_never_reuses_the_bangladesh_access_token(): void
    {
        Cache::flush();
        Http::fake([
            '*/issue-token' => Http::response(['access_token' => 'market-token', 'expires_in' => 3000], 200),
            '*/orders/*/info' => Http::response(['data' => ['order_status' => 'Delivered', 'merchant_order_id' => 'ORDER-1']], 200),
        ]);

        $this->makeConfiguredPathao(PathaoProvider::class)->getOrderStatus('C-1');
        $this->makeConfiguredPathao(PathaoNepalProvider::class)->getOrderStatus('C-1');

        $this->assertSame(2, $this->sentCount('/issue-token'));
    }

    public function test_garuda_express_advertises_the_roles_it_implements(): void
    {
        $garuda = $this->app->make(GarudaExpressProvider::class);

        $this->assertInstanceOf(CreatesOrders::class, $garuda);
        $this->assertInstanceOf(ProvidesOrderInfo::class, $garuda);
        $this->assertInstanceOf(TracksOrders::class, $garuda);
        $this->assertInstanceOf(CancelsOrders::class, $garuda);
        $this->assertInstanceOf(ResolvesLocations::class, $garuda);

        $this->assertNotInstanceOf(CalculatesPrice::class, $garuda);
        $this->assertNotInstanceOf(EstimatesDeliveryCharge::class, $garuda);
        $this->assertNotInstanceOf(HandlesWebhooks::class, $garuda);
        $this->assertNotInstanceOf(CollectsCod::class, $garuda);
        $this->assertNotInstanceOf(ProvidesStores::class, $garuda);
        $this->assertNotInstanceOf(UpdatesOrders::class, $garuda);
    }

    public function test_garuda_express_declares_a_single_district_catalog_level(): void
    {
        $garuda = $this->app->make(GarudaExpressProvider::class);

        $this->assertSame('catalog', $garuda->addressMode());
        $this->assertSame(['city'], $garuda->locationLevels());
        $this->assertSame(['city' => 'District'], $garuda->locationLabels());
        $this->assertSame([], $garuda->addressFields());
        $this->assertSame([], $garuda->getZones('1'));
        $this->assertSame([], $garuda->getAreas('1'));
    }

    public function test_garuda_express_falls_back_to_the_bundled_districts_when_tge_is_unreachable(): void
    {
        Cache::flush();
        Http::fake(['*/api/districts' => Http::response('gateway down', 502)]);

        $districts = $this->makeConfiguredGaruda('live')->getCities();

        $this->assertCount(77, $districts);
        $this->assertSame('1', $districts[0]->id);
        $this->assertSame('Kathmandu', $districts[0]->name);
        $this->assertSame('city', $districts[0]->level);

        $codes = array_map(static fn ($district) => $district->raw['code'], $districts);
        $this->assertSame(2, count(array_keys($codes, 'MNG', true)), 'Morang and Manang must both survive as separate districts.');
    }

    public function test_garuda_express_reads_districts_without_a_key_and_caches_them(): void
    {
        Cache::flush();
        Http::fake(['*/api/districts' => Http::response([
            'status'        => 'success',
            'total_results' => 1,
            'data'          => [['id' => 1, 'name' => 'Kathmandu', 'code' => 'KTM', 'state' => ['id' => 1, 'name' => 'State 3']]],
        ], 200)]);

        $garuda = $this->makeConfiguredGaruda('live');
        $garuda->getCities();
        $garuda->getCities();

        $this->assertSame(1, $this->sentCount('/api/districts'));

        Http::assertSent(fn ($request): bool => !str_contains($request->url(), '/api/districts')
            || !$request->hasHeader('apikey'));
    }

    public function test_garuda_express_creates_addresses_before_the_order_and_sends_no_recipient_fields(): void
    {
        $this->createAddressBookTable();
        $this->fakeGarudaOrderFlow();

        $result = $this->makeConfiguredGaruda('live')->createOrder($this->sampleGarudaOrder());

        $this->assertSame('9001', $result->consignmentId);
        $this->assertSame('TGE123456', $result->trackingCode);
        $this->assertSame(ShipmentStatus::Pending, $result->status);

        $this->assertSame(2, $this->sentCount('/api/addresses'));
        $this->assertSame(1, $this->sentCount('/api/orders'));

        Http::assertSent(function ($request): bool {
            if (!str_ends_with($request->url(), '/api/orders')) {
                return false;
            }

            $order = json_decode($request->body(), true)['order'];

            return $order['pickupAddress'] === '7'
                && $order['deliveryAddress'] === '5'
                && $order['packageWeight'] === '1.5'
                && $order['packageTotalItems'] === '2'
                && (float) $order['packageOrderAmount'] === 1550.0
                && str_ends_with($order['pickupDate'], 'Z')
                && !array_key_exists('recipient_name', $order)
                && !array_key_exists('name', $order)
                && !array_key_exists('mobile', $order);
        });

        Http::assertSent(fn ($request): bool => !str_ends_with($request->url(), '/api/addresses')
            || array_key_exists('address', json_decode($request->body(), true)));
    }

    public function test_garuda_express_address_book_reuses_records_and_scopes_them_per_provider_and_environment(): void
    {
        $this->createAddressBookTable();
        $this->fakeGarudaOrderFlow();

        $garuda = $this->makeConfiguredGaruda('live');
        $garuda->createOrder($this->sampleGarudaOrder('ORDER-1'));

        $this->assertSame(2, CourierProviderAddress::query()->count());
        $this->assertSame(2, $this->sentCount('/api/addresses'));

        $garuda->createOrder($this->sampleGarudaOrder('ORDER-2', name: '  ram JI  '));

        $this->assertSame(2, CourierProviderAddress::query()->count(), 'A casing/whitespace variant must reuse the stored address.');
        $this->assertSame(2, $this->sentCount('/api/addresses'));

        $garuda->createOrder($this->sampleGarudaOrder('ORDER-3', phone: '9811111111'));

        $this->assertSame(3, CourierProviderAddress::query()->count(), 'A changed phone must create a new address record.');

        CourierProviderAddress::query()->update(['environment' => 'sandbox']);
        $garuda->createOrder($this->sampleGarudaOrder('ORDER-4'));

        $this->assertSame(5, CourierProviderAddress::query()->count(), 'Live must not reuse a sandbox address id.');
    }

    public function test_garuda_express_stores_no_mapping_when_address_creation_fails(): void
    {
        $this->createAddressBookTable();
        Http::fake(['*/api/addresses' => Http::response(['message' => 'District is invalid'], 422)]);

        $this->assertThrowsCourierException(
            fn () => $this->makeConfiguredGaruda('live')->createOrder($this->sampleGarudaOrder()),
            'District is invalid',
        );

        $this->assertSame(0, CourierProviderAddress::query()->count());
        $this->assertSame(0, $this->sentCount('/api/orders'));
    }

    public function test_garuda_express_reuses_addresses_when_a_retry_follows_a_failed_order(): void
    {
        $this->createAddressBookTable();
        Http::fake([
            '*/api/addresses' => Http::sequence()->push(['id' => 5], 201)->push(['id' => 7], 201),
            '*/api/orders'    => Http::sequence()
                ->push(['message' => 'Pickup date is in the past'], 422)
                ->push(['data' => ['id' => 9001, 'trackingNumber' => 'TGE123456', 'status' => 'NEW']], 201),
        ]);

        $garuda = $this->makeConfiguredGaruda('live');

        $this->assertThrowsCourierException(
            fn () => $garuda->createOrder($this->sampleGarudaOrder()),
            'Pickup date is in the past',
        );

        $garuda->createOrder($this->sampleGarudaOrder());

        $this->assertSame(2, $this->sentCount('/api/addresses'), 'A retry must reuse the addresses created by the failed attempt.');
        $this->assertSame(2, CourierProviderAddress::query()->count());
    }

    public function test_garuda_express_amount_semantics_follow_the_admin_setting(): void
    {
        $this->createAddressBookTable();
        $this->fakeGarudaOrderFlow();

        $prepaid = new OrderData(
            hostOrderReference: 'ORDER-PREPAID',
            recipient: new RecipientData(name: 'Ram Ji', phone: '9800000000', address: 'Koteshwor', cityId: '1'),
            codAmount: 0.0,
            weight: 1.5,
            quantity: 2,
            meta: ['order_value' => 1550.0],
        );

        $this->makeConfiguredGaruda('live')->createOrder($prepaid);
        $this->assertSame(0.0, $this->lastGarudaOrderAmount(), 'COD semantics must send 0 for a prepaid order.');

        $declared = $this->makeConfiguredGaruda('live', ['amount_semantics' => 'declared']);
        $declared->createOrder($prepaid);

        $this->assertSame(1550.0, $this->lastGarudaOrderAmount(), 'Declared semantics must send the order value for a prepaid order.');
    }

    public function test_garuda_express_refuses_an_order_without_a_district_before_any_call(): void
    {
        Http::fake();

        $order = new OrderData(
            hostOrderReference: 'ORDER-NO-DISTRICT',
            recipient: new RecipientData(name: 'Ram Ji', phone: '9800000000', address: 'Koteshwor'),
            codAmount: 100.0,
            weight: 1.0,
        );

        $this->assertThrowsCourierException(
            fn () => $this->makeConfiguredGaruda('live')->createOrder($order),
            'Select a delivery district',
        );

        Http::assertNothingSent();
    }

    public function test_garuda_express_refuses_an_incomplete_pickup_address_before_any_call(): void
    {
        $this->createAddressBookTable();
        Http::fake();

        $garuda = $this->app->make(GarudaExpressProvider::class);
        $garuda->setCredentials(['api_key' => 'key', 'sender_name' => 'Warehouse']);
        $garuda->setEnvironment('live');

        $this->assertThrowsCourierException(
            fn () => $garuda->createOrder($this->sampleGarudaOrder()),
            'Pickup Mobile',
        );
    }

    public function test_garuda_express_treats_a_500_rfc_2616_body_as_an_auth_failure_and_never_retries(): void
    {
        $this->createAddressBookTable();
        config(['courier.http.retries' => 3]);

        Http::fake(['*/api/addresses' => Http::response([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'status' => 500,
            'detail' => 'Internal Server Error',
        ], 500)]);

        $this->assertThrowsCourierException(
            fn () => $this->makeConfiguredGaruda('live')->createOrder($this->sampleGarudaOrder()),
            'API key is invalid',
        );

        $this->assertSame(1, $this->sentCount('/api/addresses'), 'A bad credential must not be retried.');
    }

    public function test_garuda_express_treats_a_401_as_a_missing_key(): void
    {
        $this->createAddressBookTable();
        Http::fake(['*/api/addresses' => Http::response(['message' => 'Authentication Required'], 401)]);

        $this->assertThrowsCourierException(
            fn () => $this->makeConfiguredGaruda('live')->createOrder($this->sampleGarudaOrder()),
            'API key is missing',
        );
    }

    public function test_garuda_express_tracks_and_cancels_by_the_stored_order_id(): void
    {
        Http::fake([
            '*/api/orders/9001' => Http::response(['data' => [
                'id'             => 9001,
                'trackingNumber' => 'TGE123456',
                'status'         => 'In Transit',
                'statuses'       => [
                    ['status' => 'In Transit', 'createdAt' => '2026-07-26T10:00:00Z', 'remarks' => 'Left Kathmandu hub'],
                    ['status' => 'NEW', 'createdAt' => '2026-07-25T10:00:00Z', 'remarks' => 'Order created'],
                ],
            ]], 200),
        ]);

        $garuda = $this->makeConfiguredGaruda('live');
        $events = $garuda->trackOrder('9001');

        $this->assertCount(2, $events);
        $this->assertSame(ShipmentStatus::InTransit, $events[0]->status);
        $this->assertSame('Left Kathmandu hub', $events[0]->description);
        $this->assertSame(ShipmentStatus::Pending, $events[1]->status);

        $this->assertSame(ShipmentStatus::InTransit, $garuda->getOrderStatus('9001')->status);
        $this->assertSame('TGE123456', $garuda->getOrderDetails('9001')->trackingCode);

        Http::assertSent(fn ($request): bool => !str_contains($request->url(), '/api/orders/9001')
            || $request->hasHeader('apikey', 'garuda-key'));
    }

    public function test_garuda_express_cancel_deletes_the_order_and_surfaces_a_refusal(): void
    {
        Http::fake(['*/api/orders/9001' => Http::sequence()
            ->push([], 204)
            ->push(['message' => 'Only NEW orders can be deleted'], 422)]);

        $garuda = $this->makeConfiguredGaruda('live');

        $this->assertSame(ShipmentStatus::Cancelled, $garuda->cancelOrder('9001')->status);

        Http::assertSent(fn ($request): bool => !str_contains($request->url(), '/api/orders/9001')
            || $request->method() === 'DELETE');

        $this->assertThrowsCourierException(
            fn () => $garuda->cancelOrder('9001'),
            'Only NEW orders can be deleted',
        );
    }

    public function test_garuda_express_maps_an_unknown_status_to_unknown(): void
    {
        Http::fake(['*/api/orders/9001' => Http::response(['data' => ['id' => 9001, 'status' => 'TELEPORTED']], 200)]);

        $this->assertSame(ShipmentStatus::Unknown, $this->makeConfiguredGaruda('live')->getOrderStatus('9001')->status);
    }

    public function test_garuda_express_sandbox_returns_synthetic_data_without_http(): void
    {
        Cache::flush();
        Http::fake();

        $garuda = $this->makeConfiguredGaruda('sandbox');

        $districts = $garuda->getCities();
        $this->assertCount(5, $districts);
        $this->assertSame('Kathmandu', $districts[0]->name);

        $shipment = $garuda->createOrder($this->sampleGarudaOrder());

        $this->assertStringStartsWith('TGE-SBX-', $shipment->consignmentId);
        $this->assertStringStartsWith('TGE', $shipment->trackingCode);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertTrue($shipment->raw['simulated']);

        $this->assertNotEmpty($garuda->trackOrder($shipment->consignmentId));
        $this->assertSame(ShipmentStatus::InTransit, $garuda->getOrderStatus($shipment->consignmentId)->status);
        $this->assertSame(ShipmentStatus::Cancelled, $garuda->cancelOrder($shipment->consignmentId)->status);

        Http::assertNothingSent();
    }

    public function test_pathao_hides_injection_probe_stores_from_the_pickup_list(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
            '*/aladdin/api/v1/stores'      => Http::response(['data' => ['data' => [
                ['store_id' => 150662, 'store_name' => 'Mirpur  Warehouse', 'store_address' => "House 1, Road 1\nMirpur, Dhaka"],
                ['store_id' => 150661, 'store_name' => 'test|id', 'store_address' => 'This is a test address that is long enough'],
                ['store_id' => 150660, 'store_name' => 'test`id`', 'store_address' => 'This is a test address that is long enough'],
                ['store_id' => 150659, 'store_name' => 'test$(id)', 'store_address' => 'This is a test address that is long enough'],
                ['store_id' => 150658, 'store_name' => '{{.}}', 'store_address' => 'This is a test address that is long enough'],
                ['store_id' => 150657, 'store_name' => '{{config}}', 'store_address' => 'This is a test address that is long enough'],
                ['store_id' => 150656, 'store_name' => '{{7*7}}', 'store_address' => 'This is a test address that is long enough'],
            ]]], 200),
        ]);

        $stores = $this->makeConfiguredPathao(PathaoProvider::class)->getStores();

        $this->assertCount(1, $stores);
        $this->assertSame('150662', $stores[0]->id);
        $this->assertSame('Mirpur Warehouse — House 1, Road 1 Mirpur, Dhaka', $stores[0]->name);
    }

    public function test_redx_hides_injection_probe_stores_from_the_pickup_list(): void
    {
        Http::fake([
            '*/pickup/stores' => Http::response(['pickup_stores' => [
                ['id' => 91, 'name' => 'Gulshan Hub', 'address' => 'Road 11, Gulshan 1'],
                ['id' => 92, 'name' => '{{7*7}}', 'address' => 'This is a test address that is long enough'],
            ]], 200),
        ]);

        $redx = $this->app->make(RedxProvider::class);
        $redx->setCredentials(['api_access_token' => 'token']);

        $stores = $redx->getStores();

        $this->assertCount(1, $stores);
        $this->assertSame('Gulshan Hub — Road 11, Gulshan 1', $stores[0]->name);
    }

    private function makeConfiguredPathao(string $class): PathaoProvider
    {
        $pathao = $this->app->make($class);
        $pathao->setCredentials([
            'client_id'     => $class === PathaoProvider::class ? 'bd-client' : 'np-client',
            'client_secret' => 'secret',
            'username'      => 'merchant',
            'password'      => 'password',
            'store_id'      => '1',
        ]);
        $pathao->setEnvironment('sandbox');

        return $pathao;
    }

    private function fakePathaoPricePlan(): void
    {
        Cache::flush();
        Http::fake([
            '*/issue-token' => Http::response(['access_token' => 'pathao-token', 'expires_in' => 3000], 200),
            '*/merchant/price-plan' => Http::response(['data' => ['price' => 60, 'final_price' => 70]], 200),
        ]);
    }

    private function makeConfiguredGaruda(string $environment = 'live', array $overrides = []): GarudaExpressProvider
    {
        $garuda = $this->app->make(GarudaExpressProvider::class);
        $garuda->setCredentials(array_merge([
            'api_key'         => 'garuda-key',
            'sender_name'     => 'Warehouse',
            'sender_mobile'   => '9800000001',
            'sender_address'  => 'Balaju, Kathmandu',
            'sender_district' => '1',
        ], $overrides));
        $garuda->setEnvironment($environment);

        return $garuda;
    }

    private function fakeGarudaOrderFlow(): void
    {
        Http::fake([
            '*/api/addresses' => Http::sequence()
                ->push(['id' => 5], 201)
                ->push(['id' => 7], 201)
                ->push(['id' => 11], 201)
                ->push(['id' => 13], 201)
                ->push(['id' => 15], 201)
                ->push(['id' => 17], 201),
            '*/api/orders' => Http::response(['data' => ['id' => 9001, 'trackingNumber' => 'TGE123456', 'status' => 'NEW']], 201),
        ]);
    }

    private function sampleGarudaOrder(
        string $reference = 'ORDER-70001',
        string $name = 'Ram Ji',
        string $phone = '9800000000',
    ): OrderData {
        return new OrderData(
            hostOrderReference: $reference,
            recipient: new RecipientData(
                name: $name,
                phone: $phone,
                address: 'Koteshwor, Mahadevsthan',
                cityId: '1',
                postalCode: '44600',
            ),
            codAmount: 1550.0,
            weight: 1.5,
            quantity: 2,
        );
    }

    private function lastGarudaOrderAmount(): float
    {
        $orders = Http::recorded(fn ($request): bool => str_ends_with($request->url(), '/api/orders'));

        return (float) json_decode($orders->last()[0]->body(), true)['order']['packageOrderAmount'];
    }

    private function createAddressBookTable(): void
    {
        Schema::dropIfExists('courier_provider_addresses');

        (require base_path('Modules/Courier/database/migrations/2026_07_27_100005_create_courier_provider_addresses_table.php'))->up();
        (require base_path('Modules/Courier/database/migrations/2026_07_29_100008_add_owner_to_courier_provider_addresses_table.php'))->up();
    }
}
