<?php

namespace Modules\Courier\app\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Courier\app\Enums\ShipmentStatus;

class ShipmentStatusUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string         $consignmentId,
        public readonly ShipmentStatus $status,
        public readonly ?string        $hostOrderReference = null,
    ) {}
}
