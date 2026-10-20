<?php

namespace App\Listeners;

use App\Models\Order;
use App\Services\DeliveryPartnerOrderStatusService;
use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\Events\ShipmentDispatched;

class CourierShipmentDispatchedListener
{
    public function __construct(private readonly DeliveryPartnerOrderStatusService $deliveryPartnerOrderStatusService)
    {
    }

    public function handle(ShipmentDispatched $event): void
    {
        $order = Order::find($event->hostOrderReference);

        if ($order === null) {
            return;
        }

        $order->fill([
            'delivery_type'                    => 'third_party_delivery',
            'delivery_service_name'            => $event->providerLabel,
            'third_party_delivery_tracking_id' => $event->trackingCode ?: $event->consignmentId,
            'delivery_man_id'                  => null,
            'deliveryman_charge'               => 0,
            'expected_delivery_date'           => null,
        ])->save();

        $actor = $this->dispatchingActor();

        $this->deliveryPartnerOrderStatusService->advanceToOutForDelivery(
            order: $order,
            userType: $actor['user_type'],
            userId: $actor['user_id'],
            cause: 'courier:'.$event->consignmentId,
        );
    }

    private function dispatchingActor(): array
    {
        $owner = app(CourierOwnerResolver::class)->resolve();

        return $owner->isPlatform()
            ? ['user_type' => 'admin', 'user_id' => auth('admin')->id() ?? 0]
            : ['user_type' => 'seller', 'user_id' => $owner->id];
    }
}
