<?php

namespace Modules\Courier\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Courier\CourierProviders\RedxProvider;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Http\Concerns\PresentsTrackingEvents;
use Modules\Courier\Tests\CourierTestCase;

class TrackingEventPresentationTest extends CourierTestCase
{
    use PresentsTrackingEvents;

    public function test_a_status_less_event_is_labelled_with_the_carrier_message(): void
    {
        $presented = $this->presentTrackingEvents([
            new TrackingEvent(
                status: ShipmentStatus::Unknown,
                occurredAt: '2026-08-04T10:41:00.000Z',
                description: 'Package is created successfully',
            ),
        ]);

        $this->assertSame('Package is created successfully', $presented[0]['label']);
        $this->assertSame('unknown', $presented[0]['status']);
        $this->assertSame('neutral', $presented[0]['tone']);
    }

    public function test_a_confirmed_redx_message_resolves_to_the_status_the_shipment_carries(): void
    {
        $events = $this->trackRedx('Package is created successfully');

        $this->assertCount(1, $events);
        $this->assertSame(ShipmentStatus::Pending, $events[0]->status);
        $this->assertSame('Package is created successfully', $events[0]->description);
    }

    public function test_an_unconfirmed_redx_message_stays_unmapped_and_keeps_the_carrier_wording(): void
    {
        $events = $this->trackRedx('Parcel handed over to the delivery agent');
        $presented = $this->presentTrackingEvents($events);

        $this->assertSame(ShipmentStatus::Unknown, $events[0]->status);
        $this->assertSame('Parcel handed over to the delivery agent', $presented[0]['label']);
        $this->assertSame('04 Aug 2026, 04:41 PM', $presented[0]['time']);
    }

    private function trackRedx(string $message): array
    {
        Http::fake([
            '*/parcel/track/*' => Http::response([
                'tracking' => [
                    ['message_en' => $message, 'message_bn' => '…', 'time' => '2026-08-04T10:41:00.000Z'],
                ],
            ], 200),
        ]);

        $redx = $this->app->make(RedxProvider::class);
        $redx->setEnvironment('sandbox');
        $redx->setCredentials(['api_access_token' => 'test-token']);

        return $redx->trackOrder('20A316MOG0DI');
    }

    public function test_status_wording_comes_from_the_enum_alone(): void
    {
        $this->assertSame('Pending', ShipmentStatus::Pending->label());
        $this->assertSame('Partially Delivered', ShipmentStatus::PartialDelivered->label());
        $this->assertSame('Out For Delivery', ShipmentStatus::OutForDelivery->label());
        $this->assertNull($this->presentShipmentStatus(null));
    }

    public function test_a_carrier_timestamp_follows_the_timezone_configured_in_system_setup(): void
    {
        $event = new TrackingEvent(
            status: ShipmentStatus::Unknown,
            occurredAt: '2020-03-16T05:44:24.000Z',
            description: 'Package is created successfully',
        );

        $this->assertSame('16 Mar 2020, 11:44 AM', $this->presentIn('Asia/Dhaka', $event));
        $this->assertSame('16 Mar 2020, 10:44 AM', $this->presentIn('Asia/Karachi', $event));
        $this->assertSame('16 Mar 2020, 05:44 AM', $this->presentIn('UTC', $event));
    }

    public function test_an_unparsable_or_missing_carrier_timestamp_is_kept_as_received(): void
    {
        $presented = $this->presentTrackingEvents([
            new TrackingEvent(status: ShipmentStatus::Unknown, occurredAt: 'awaiting scan', description: 'Awaiting scan'),
            new TrackingEvent(status: ShipmentStatus::Unknown, occurredAt: '', description: 'Awaiting scan'),
        ]);

        $this->assertSame('awaiting scan', $presented[0]['time']);
        $this->assertSame('', $presented[1]['time']);
    }

    private function presentIn(string $timezone, TrackingEvent $event): string
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);

        $presented = $this->presentTrackingEvents([$event]);

        date_default_timezone_set($previousTimezone);

        return $presented[0]['time'];
    }
}
