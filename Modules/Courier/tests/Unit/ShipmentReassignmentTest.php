<?php

namespace Modules\Courier\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Courier\app\Events\ShipmentDispatched;
use Modules\Courier\app\Events\ShipmentStatusUpdated;
use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Models\CourierProviderSetting;
use Modules\Courier\app\Models\CourierShipment;
use Modules\Courier\app\Services\CourierService;
use Modules\Courier\app\Services\ProviderRegistry;
use Modules\Courier\app\ValueObjects\CourierOwner;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\CourierProvider;
use Modules\Courier\Tests\CourierTestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class ShipmentReassignmentTest extends CourierTestCase
{
    private const ORDER = '4021';

    private const OTHER_ORDER = '4022';

    private const VENDOR_ID = 77;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['courier_provider_settings', 'courier_shipments', 'courier_tracking_events', 'courier_webhook_logs'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach ([
            '2026_07_19_100001_create_courier_provider_settings_table',
            '2026_07_19_100002_create_courier_shipments_table',
            '2026_07_19_100003_create_courier_tracking_events_table',
            '2026_07_19_100004_create_courier_webhook_logs_table',
            '2026_07_29_100006_add_owner_to_courier_provider_settings_table',
            '2026_07_29_100007_add_owner_to_courier_shipments_table',
            '2026_08_08_100009_add_supersede_and_dispatch_details_to_courier_shipments_table',
            '2026_08_10_100010_scope_courier_shipment_consignment_uniqueness',
        ] as $migration) {
            (require base_path("Modules/Courier/database/migrations/{$migration}.php"))->up();
        }

        config(['courier.providers' => [
            'partner_a' => PartnerAProvider::class,
            'partner_b' => PartnerBProvider::class,
        ]]);

        Event::fake([ShipmentDispatched::class, ShipmentStatusUpdated::class]);

        PartnerAProvider::$fails = false;
        PartnerBProvider::$fails = false;
        PartnerAProvider::$consignmentId = null;
        PartnerBProvider::$consignmentId = null;

        $this->enableProviderFor(CourierOwner::platform(), 'partner_a');
        $this->enableProviderFor(CourierOwner::platform(), 'partner_b');

        $this->ownedBy(CourierOwner::platform());
    }

    public function test_dispatching_twice_without_the_replace_intent_is_refused(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('already been sent to a courier');

        $this->courier()->ship($this->orderData(), 'partner_b');
    }

    public function test_a_switch_supersedes_the_previous_shipment_and_creates_a_new_active_one(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');
        $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);

        $shipments = CourierShipment::query()->forHostReference(self::ORDER)->orderBy('id')->get();

        $this->assertCount(2, $shipments);
        $this->assertNotNull($shipments[0]->superseded_at, 'The previous shipment was not superseded.');
        $this->assertNull($shipments[1]->superseded_at);
        $this->assertSame('partner_b', $this->courier()->shipmentFor(self::ORDER)->provider);
    }

    public function test_switching_to_the_same_partner_is_refused(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');

        $this->expectException(CourierException::class);

        $this->courier()->ship($this->orderData(), 'partner_a', replaceExisting: true);
    }

    public function test_the_replace_intent_without_an_active_shipment_is_refused(): void
    {
        $this->expectException(CourierException::class);

        $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);
    }

    public function test_a_failed_switch_leaves_the_original_shipment_active(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');

        PartnerBProvider::$fails = true;

        try {
            $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);
            $this->fail('The failing carrier call should have thrown.');
        } catch (CourierException) {
        }

        $this->assertSame(1, CourierShipment::query()->forHostReference(self::ORDER)->count());
        $this->assertSame('partner_a', $this->courier()->shipmentFor(self::ORDER)->provider);
        $this->assertNull($this->courier()->shipmentFor(self::ORDER)->superseded_at);
    }

    public function test_a_revision_writes_only_the_dispatch_details(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');

        $before = $this->courier()->shipmentFor(self::ORDER);

        $this->courier()->reviseDispatchDetails($this->orderData(recipientName: 'Corrected Name', phone: '01999999999'));

        $after = $this->courier()->shipmentFor(self::ORDER)->fresh();

        $this->assertSame($before->id, $after->id, 'A revision must not create a second shipment.');
        $this->assertSame($before->consignment_id, $after->consignment_id);
        $this->assertSame($before->tracking_code, $after->tracking_code);
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->payload, $after->payload);
        $this->assertSame('Corrected Name', $after->dispatch_details['recipient_name']);
        $this->assertSame('01999999999', $after->dispatch_details['recipient_phone']);
    }

    public function test_a_revision_without_an_active_shipment_is_refused(): void
    {
        $this->expectException(CourierException::class);

        $this->courier()->reviseDispatchDetails($this->orderData());
    }

    public function test_a_webhook_for_a_superseded_consignment_leaves_the_active_shipment_alone(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');
        $superseded = $this->courier()->shipmentFor(self::ORDER);

        $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);

        PartnerAProvider::$webhookConsignment = $superseded->consignment_id;
        $this->courier()->handleWebhook('partner_a', Request::create('/courier/webhook/partner_a', 'POST'));

        $this->assertSame(ShipmentStatus::Delivered, $superseded->fresh()->status);
        $this->assertSame(ShipmentStatus::Pending, $this->courier()->shipmentFor(self::ORDER)->status);
        $this->assertSame('partner_b', $this->courier()->shipmentFor(self::ORDER)->provider);
    }

    public function test_a_vendor_cannot_switch_or_revise_a_platform_owned_shipment(): void
    {
        $this->courier()->ship($this->orderData(), 'partner_a');

        $vendor = CourierOwner::vendor(self::VENDOR_ID);
        $this->enableProviderFor($vendor, 'partner_a');
        $this->enableProviderFor($vendor, 'partner_b');
        $this->ownedBy($vendor);

        $this->assertNull($this->courier()->activeShipmentForOwner(self::ORDER));

        try {
            $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);
            $this->fail('A vendor must not be able to supersede the platform shipment.');
        } catch (CourierException) {
        }

        try {
            $this->courier()->reviseDispatchDetails($this->orderData());
            $this->fail('A vendor must not be able to revise the platform shipment.');
        } catch (CourierException) {
        }

        $this->assertSame(1, CourierShipment::query()->forHostReference(self::ORDER)->count());
    }

    public function test_a_live_carrier_reusing_a_consignment_id_across_orders_is_reported_as_a_courier_error(): void
    {
        $this->goLive('partner_a', 'partner_b');
        PartnerAProvider::$consignmentId = 'reused-live-id';

        $this->courier()->ship($this->orderData(), 'partner_a');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('already recorded');

        $this->courier()->ship($this->orderData(hostOrderReference: self::OTHER_ORDER), 'partner_a');
    }

    public function test_a_sandbox_carrier_reusing_a_consignment_id_across_orders_still_books(): void
    {
        PartnerAProvider::$consignmentId = 'sandbox-fixed-id';

        $this->courier()->ship($this->orderData(), 'partner_a');
        $this->courier()->ship($this->orderData(hostOrderReference: self::OTHER_ORDER), 'partner_a');

        $this->assertSame('sandbox-fixed-id', $this->courier()->shipmentFor(self::ORDER)->consignment_id);
        $this->assertSame('sandbox-fixed-id', $this->courier()->shipmentFor(self::OTHER_ORDER)->consignment_id);
    }

    public function test_a_sandbox_round_trip_back_to_the_first_partner_reuses_the_canned_id(): void
    {
        PartnerAProvider::$consignmentId = 'sandbox-fixed-id';

        $this->courier()->ship($this->orderData(), 'partner_a');
        $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);
        $this->courier()->ship($this->orderData(), 'partner_a', replaceExisting: true);

        $shipments = CourierShipment::query()->forHostReference(self::ORDER)->orderBy('id')->get();

        $this->assertCount(3, $shipments);
        $this->assertSame(['partner_a', 'partner_b', 'partner_a'], $shipments->pluck('provider')->all());
        $this->assertSame(1, $shipments->whereNull('superseded_at')->count());
        $this->assertSame('partner_a', $shipments->last()->provider);
    }

    public function test_a_live_carrier_reissuing_the_same_id_for_the_same_order_is_recorded(): void
    {
        $this->goLive('partner_a', 'partner_b');
        PartnerAProvider::$consignmentId = 'idempotent-live-id';

        $this->courier()->ship($this->orderData(), 'partner_a');
        $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);
        $this->courier()->ship($this->orderData(), 'partner_a', replaceExisting: true);

        $active = $this->courier()->shipmentFor(self::ORDER);

        $this->assertSame('partner_a', $active->provider);
        $this->assertSame('idempotent-live-id', $active->consignment_id);
        $this->assertSame(3, CourierShipment::query()->forHostReference(self::ORDER)->count());
    }

    public function test_a_switch_onto_a_consignment_id_recorded_for_another_order_leaves_the_original_shipment_active(): void
    {
        $this->goLive('partner_a', 'partner_b');
        PartnerBProvider::$consignmentId = 'reused-live-id';

        $this->courier()->ship($this->orderData(hostOrderReference: self::OTHER_ORDER), 'partner_b');
        $this->courier()->ship($this->orderData(), 'partner_a');

        try {
            $this->courier()->ship($this->orderData(), 'partner_b', replaceExisting: true);
            $this->fail('A carrier-reused consignment id should have refused the switch.');
        } catch (CourierException) {
        }

        $active = $this->courier()->shipmentFor(self::ORDER);

        $this->assertSame('partner_a', $active->provider);
        $this->assertNull($active->superseded_at);
        $this->assertSame(1, CourierShipment::query()->forHostReference(self::ORDER)->count());
    }

    private function courier(): CourierService
    {
        return $this->app->make(CourierService::class);
    }

    private function orderData(string $recipientName = 'Test Receiver', string $phone = '01700000000', string $hostOrderReference = self::ORDER): OrderData
    {
        return new OrderData(
            hostOrderReference: $hostOrderReference,
            recipient: new RecipientData(name: $recipientName, phone: $phone, address: '1/B/3 Farmgate, Dhaka'),
            codAmount: 250.0,
            weight: 1.5,
        );
    }

    private function enableProviderFor(CourierOwner $owner, string $provider, string $environment = CourierProvider::ENVIRONMENT_SANDBOX): void
    {
        CourierProviderSetting::query()
            ->where('owner_type', $owner->type)
            ->where('owner_id', $owner->id)
            ->where('provider', $provider)
            ->delete();

        CourierProviderSetting::create([
            'owner_type'  => $owner->type,
            'owner_id'    => $owner->id,
            'provider'    => $provider,
            'credentials' => ['token' => 'test'],
            'settings'    => ['environment' => $environment],
            'is_active'   => true,
        ]);
    }

    private function goLive(string ...$providers): void
    {
        foreach ($providers as $provider) {
            $this->enableProviderFor(CourierOwner::platform(), $provider, CourierProvider::ENVIRONMENT_LIVE);
        }

        $this->ownedBy(CourierOwner::platform());
    }

    private function ownedBy(CourierOwner $owner): void
    {
        $this->app->bind(CourierOwnerResolver::class, fn () => new class($owner) implements CourierOwnerResolver {
            public function __construct(private readonly CourierOwner $owner) {}

            public function resolve(): CourierOwner
            {
                return $this->owner;
            }
        });

        $this->app->forgetInstance(ProviderRegistry::class);
        $this->app->forgetInstance(CourierService::class);
    }
}

class PartnerAProvider extends CourierProvider implements CreatesOrders, HandlesWebhooks
{
    public static bool $fails = false;

    public static ?string $webhookConsignment = null;

    public static ?string $consignmentId = null;

    public function getName(): string
    {
        return 'partner_a';
    }

    public function displayName(): string
    {
        return 'Partner A';
    }

    public function supportedCountries(): array
    {
        return ['BD'];
    }

    public function createOrder(OrderData $order): ShipmentResult
    {
        if (static::$fails) {
            throw new CourierException(static::class.' rejected the booking.');
        }

        return new ShipmentResult(
            consignmentId: static::$consignmentId ?? $this->getName().'-'.uniqid(),
            status: ShipmentStatus::Pending,
            trackingCode: $this->getName().'-track',
            raw: ['provider' => $this->getName()],
        );
    }

    public function verifyWebhook(\Illuminate\Http\Request $request): bool
    {
        return true;
    }

    public function parseWebhook(\Illuminate\Http\Request $request): WebhookEvent
    {
        return new WebhookEvent(
            consignmentId: (string) static::$webhookConsignment,
            status: ShipmentStatus::Delivered,
        );
    }
}

class PartnerBProvider extends PartnerAProvider
{
    public static bool $fails = false;

    public static ?string $consignmentId = null;

    public function getName(): string
    {
        return 'partner_b';
    }

    public function displayName(): string
    {
        return 'Partner B';
    }
}
