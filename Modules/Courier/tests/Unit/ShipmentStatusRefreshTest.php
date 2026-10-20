<?php

namespace Modules\Courier\Tests\Unit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Events\ShipmentDispatched;
use Modules\Courier\app\Events\ShipmentStatusUpdated;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Models\CourierProviderSetting;
use Modules\Courier\app\Models\CourierShipment;
use Modules\Courier\app\Services\CourierService;
use Modules\Courier\app\ValueObjects\CourierOwner;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\CourierProvider;
use Modules\Courier\Tests\CourierTestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class ShipmentStatusRefreshTest extends CourierTestCase
{
    private const ORDER = '5011';

    private const SHARED_CONSIGNMENT = 'shared-consignment-1';

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
            'status_partner' => StatusPartnerProvider::class,
            'silent_partner' => SilentPartnerProvider::class,
        ]]);

        Event::fake([ShipmentDispatched::class, ShipmentStatusUpdated::class]);

        StatusPartnerProvider::$status = ShipmentStatus::Pending;
        StatusPartnerProvider::$fails = false;

        $this->enableProvider('status_partner');
        $this->enableProvider('silent_partner');
    }

    public function test_the_carrier_status_lookup_replaces_the_status_stored_at_booking(): void
    {
        $shipment = $this->ship('status_partner');
        StatusPartnerProvider::$status = ShipmentStatus::PickupRequested;

        $refreshed = $this->courier()->refreshStatus($shipment->consignmentId);

        $this->assertSame(ShipmentStatus::PickupRequested, $refreshed);
        $this->assertSame(ShipmentStatus::PickupRequested, $this->courier()->shipmentFor(self::ORDER)->status);
        Event::assertDispatched(ShipmentStatusUpdated::class);
    }

    public function test_a_status_the_driver_cannot_map_leaves_the_stored_status_standing(): void
    {
        $shipment = $this->ship('status_partner');
        StatusPartnerProvider::$status = ShipmentStatus::Unknown;

        $refreshed = $this->courier()->refreshStatus($shipment->consignmentId);

        $this->assertSame(ShipmentStatus::Pending, $refreshed);
        $this->assertSame(ShipmentStatus::Pending, $this->courier()->shipmentFor(self::ORDER)->status);
        Event::assertNotDispatched(ShipmentStatusUpdated::class);
    }

    public function test_a_failing_carrier_lookup_leaves_the_stored_status_standing(): void
    {
        $shipment = $this->ship('status_partner');
        StatusPartnerProvider::$fails = true;

        $this->assertSame(ShipmentStatus::Pending, $this->courier()->refreshStatus($shipment->consignmentId));
        $this->assertSame(ShipmentStatus::Pending, $this->courier()->shipmentFor(self::ORDER)->status);
    }

    public function test_a_driver_without_a_status_lookup_reports_the_stored_status(): void
    {
        $shipment = $this->ship('silent_partner');

        $this->assertSame(ShipmentStatus::Pending, $this->courier()->refreshStatus($shipment->consignmentId));
    }

    public function test_an_unknown_consignment_has_no_status_to_report(): void
    {
        $this->assertNull($this->courier()->refreshStatus('never-booked'));
    }

    public function test_an_owner_scoped_refresh_reaches_its_own_shipment_when_another_owner_holds_the_same_consignment(): void
    {
        $own = $this->recordShipment(CourierOwner::platform(), self::ORDER, now()->subHour());
        $foreign = $this->recordShipment(CourierOwner::vendor(7), '9099', now());
        StatusPartnerProvider::$status = ShipmentStatus::PickupRequested;

        $refreshed = $this->courier()->refreshStatus(self::SHARED_CONSIGNMENT, CourierOwner::platform());

        $this->assertSame(ShipmentStatus::PickupRequested, $refreshed);
        $this->assertSame(ShipmentStatus::PickupRequested, $own->fresh()->status);
        $this->assertSame(ShipmentStatus::Pending, $foreign->fresh()->status);
        Event::assertDispatched(
            ShipmentStatusUpdated::class,
            fn (ShipmentStatusUpdated $event): bool => $event->hostOrderReference === self::ORDER,
        );
    }

    public function test_an_owner_scoped_track_refuses_a_consignment_owned_by_someone_else(): void
    {
        $this->recordShipment(CourierOwner::vendor(7), '9099');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('No shipment was found for this consignment.');

        $this->courier()->track(self::SHARED_CONSIGNMENT, CourierOwner::platform());
    }

    private function recordShipment(CourierOwner $owner, string $hostOrderReference, ?Carbon $bookedAt = null): CourierShipment
    {
        $shipment = CourierShipment::create([
            'owner_type'           => $owner->type,
            'owner_id'             => $owner->id,
            'provider'             => 'status_partner',
            'consignment_id'       => self::SHARED_CONSIGNMENT,
            'host_order_reference' => $hostOrderReference,
            'status'               => ShipmentStatus::Pending,
        ]);

        $shipment->forceFill(['created_at' => $bookedAt ?? now()])->save();

        return $shipment;
    }

    private function ship(string $provider): ShipmentResult
    {
        return $this->courier()->ship(new OrderData(
            hostOrderReference: self::ORDER,
            recipient: new RecipientData(name: 'Test Receiver', phone: '01700000000', address: '1/B/3 Farmgate, Dhaka'),
            codAmount: 250.0,
            weight: 1.5,
        ), $provider);
    }

    private function courier(): CourierService
    {
        return $this->app->make(CourierService::class);
    }

    private function enableProvider(string $provider): void
    {
        CourierProviderSetting::create([
            'owner_type'  => CourierOwner::platform()->type,
            'owner_id'    => CourierOwner::platform()->id,
            'provider'    => $provider,
            'credentials' => ['token' => 'test'],
            'settings'    => ['environment' => CourierProvider::ENVIRONMENT_SANDBOX],
            'is_active'   => true,
        ]);
    }
}

class SilentPartnerProvider extends CourierProvider implements CreatesOrders
{
    public function getName(): string
    {
        return 'silent_partner';
    }

    public function displayName(): string
    {
        return 'Silent Partner';
    }

    public function supportedCountries(): array
    {
        return ['BD'];
    }

    public function createOrder(OrderData $order): ShipmentResult
    {
        return new ShipmentResult(
            consignmentId: $this->getName().'-'.uniqid(),
            status: ShipmentStatus::Pending,
            raw: [],
        );
    }
}

class StatusPartnerProvider extends SilentPartnerProvider implements ProvidesOrderInfo
{
    public static ShipmentStatus $status = ShipmentStatus::Pending;

    public static bool $fails = false;

    public function getName(): string
    {
        return 'status_partner';
    }

    public function displayName(): string
    {
        return 'Status Partner';
    }

    public function getOrderDetails(string $consignmentId): ShipmentResult
    {
        return $this->getOrderStatus($consignmentId);
    }

    public function getOrderShortInfo(string $consignmentId): ShipmentResult
    {
        return $this->getOrderStatus($consignmentId);
    }

    public function getOrderStatus(string $consignmentId): ShipmentResult
    {
        if (static::$fails) {
            throw new CourierException('The carrier is unreachable.');
        }

        return new ShipmentResult(consignmentId: $consignmentId, status: static::$status);
    }
}
