<?php

namespace Modules\Courier\app\DataTransferObjects\Responses;

use Modules\Courier\app\Enums\ShipmentStatus;

class ShipmentResult
{
    public function __construct(
        public readonly string         $consignmentId,
        public readonly ShipmentStatus $status,
        public readonly ?string        $hostOrderReference = null,
        public readonly ?string        $trackingCode = null,
        public readonly ?float         $deliveryFee = null,
        public readonly ?float         $codAmount = null,
        public readonly array          $raw = [],
    ) {}
}
