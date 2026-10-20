<?php

namespace Modules\Courier\app\Contracts;

use Modules\Courier\app\ValueObjects\CourierOwner;

interface CourierShipmentAccessResolver
{
    public function allowsRead(CourierOwner $viewer, CourierOwner $bookedBy, ?string $hostOrderReference): bool;
}
