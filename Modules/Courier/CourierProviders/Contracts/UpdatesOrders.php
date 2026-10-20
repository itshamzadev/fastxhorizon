<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;

interface UpdatesOrders
{
    public function updateOrder(string $consignmentId, OrderData $data): ShipmentResult;
}
