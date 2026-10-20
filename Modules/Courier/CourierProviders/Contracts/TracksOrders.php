<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;

interface TracksOrders
{
    public function trackOrder(string $consignmentId): array;
}
