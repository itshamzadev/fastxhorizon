<?php

namespace Modules\Courier\app\Services;

use Modules\Courier\app\Contracts\CourierShipmentAccessResolver;
use Modules\Courier\app\ValueObjects\CourierOwner;

class BookingOwnerAccessResolver implements CourierShipmentAccessResolver
{
    public function allowsRead(CourierOwner $viewer, CourierOwner $bookedBy, ?string $hostOrderReference): bool
    {
        return $viewer->equals($bookedBy);
    }
}
