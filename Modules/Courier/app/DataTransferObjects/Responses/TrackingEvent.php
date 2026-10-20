<?php

namespace Modules\Courier\app\DataTransferObjects\Responses;

use Modules\Courier\app\Enums\ShipmentStatus;

class TrackingEvent
{
    public function __construct(
        public readonly ShipmentStatus $status,
        public readonly string         $occurredAt,
        public readonly ?string        $location = null,
        public readonly ?string        $description = null,
        public readonly array          $raw = [],
    ) {}
}
