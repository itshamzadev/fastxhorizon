<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;

interface CreatesOrders
{
    public function createOrder(OrderData $data): ShipmentResult;
}
