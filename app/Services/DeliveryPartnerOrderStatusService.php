<?php

namespace App\Services;

use App\Contracts\Repositories\OrderStatusHistoryRepositoryInterface;
use App\Models\Order;

class DeliveryPartnerOrderStatusService
{
    private const ADVANCEABLE_STATUSES = ['pending', 'confirmed', 'processing'];

    public function __construct(
        private readonly OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepo,
        private readonly OrderStatusHistoryService             $orderStatusHistoryService,
    )
    {
    }

    public function advanceToOutForDelivery(object|array $order, string $userType = 'admin', int|string $userId = 0, ?string $cause = null): bool
    {
        if (!in_array($order['order_status'], self::ADVANCEABLE_STATUSES, true) || !deliveryPartnerAssigned(order: $order)) {
            return false;
        }

        Order::where('id', $order['id'])->update(['order_status' => 'out_for_delivery']);

        $this->orderStatusHistoryRepo->add(data: $this->orderStatusHistoryService->getOrderHistoryData(
            orderId: $order['id'],
            userId: $userId,
            userType: $userType,
            status: 'out_for_delivery',
            cause: $cause,
        ));

        return true;
    }
}
