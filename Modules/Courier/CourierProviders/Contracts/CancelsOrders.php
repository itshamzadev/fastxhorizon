<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;

interface CancelsOrders
{
    public function cancelOrder(string $consignmentId): ShipmentResult;
}
