<?php

namespace App\Services\Courier;

use App\Models\Order;
use Modules\Courier\app\Contracts\CourierShipmentAccessResolver;
use Modules\Courier\app\ValueObjects\CourierOwner;

class HostCourierShipmentAccessResolver implements CourierShipmentAccessResolver
{
    public function allowsRead(CourierOwner $viewer, CourierOwner $bookedBy, ?string $hostOrderReference): bool
    {
        if ($viewer->equals($bookedBy) || $viewer->isPlatform()) {
            return true;
        }

        if ($hostOrderReference === null) {
            return false;
        }

        return Order::where('id', $hostOrderReference)
            ->where('seller_id', $viewer->id)
            ->where('seller_is', 'seller')
            ->exists();
    }
}
