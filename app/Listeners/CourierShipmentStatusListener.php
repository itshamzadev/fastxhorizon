<?php

namespace App\Listeners;

use App\Models\Order;
use App\Traits\CommonTrait;
use Modules\Courier\app\Events\ShipmentStatusUpdated;

/**
 * Syncs the host order status/timeline when a courier reports a shipment status
 * change (via webhook). Intentionally uses a side-effect-free status write: it
 * does NOT run the financial/stock finalization that the admin "delivered" flow
 * performs (seller payout, commission, wallet, marking paid, stock). Moving money
 * from an external webhook is left to a deliberate admin action.
 */
class CourierShipmentStatusListener
{
    use CommonTrait;

    private const FINALIZED = ['delivered', 'canceled', 'returned', 'failed'];

    public function handle(ShipmentStatusUpdated $event): void
    {
        if ($event->hostOrderReference === null) {
            return;
        }

        $orderStatus = $this->mapToOrderStatus($event->status->value);

        if ($orderStatus === null) {
            return;
        }

        $order = Order::find($event->hostOrderReference);

        if ($order === null || $order->order_status === $orderStatus) {
            return;
        }

        if (in_array($order->order_status, self::FINALIZED, true)) {
            return;
        }

        $order->order_status = $orderStatus;
        $order->save();

        self::add_order_status_history(
            order_id: $order->id,
            user_id: '0',
            status: $orderStatus,
            user_type: 'admin',
            cause: 'courier:'.$event->consignmentId,
        );
    }

    private function mapToOrderStatus(string $shipmentStatus): ?string
    {
        return match ($shipmentStatus) {
            'pickup_requested', 'picked_up', 'at_hub', 'in_transit' => 'processing',
            'out_for_delivery' => 'out_for_delivery',
            'delivered'        => 'delivered',
            'returned'         => 'returned',
            'cancelled'        => 'canceled',
            'failed'           => 'failed',
            default            => null,
        };
    }
}
