<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;

interface ProvidesOrderInfo
{
    public function getOrderDetails(string $consignmentId): ShipmentResult;

    public function getOrderShortInfo(string $consignmentId): ShipmentResult;

    public function getOrderStatus(string $consignmentId): ShipmentResult;
}
