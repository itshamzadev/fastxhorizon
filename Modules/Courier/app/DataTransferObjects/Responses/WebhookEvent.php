<?php

namespace Modules\Courier\app\DataTransferObjects\Responses;

use Modules\Courier\app\Enums\ShipmentStatus;

class WebhookEvent
{
    public function __construct(
        public readonly string         $consignmentId,
        public readonly ShipmentStatus $status,
        public readonly ?string        $hostOrderReference = null,
        public readonly ?string        $occurredAt = null,
        public readonly array          $raw = [],
    ) {}
}
