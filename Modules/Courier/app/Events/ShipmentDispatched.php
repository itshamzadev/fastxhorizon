<?php

namespace Modules\Courier\app\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ShipmentDispatched
{
    use Dispatchable;

    public function __construct(
        public readonly string  $hostOrderReference,
        public readonly string  $provider,
        public readonly string  $providerLabel,
        public readonly string  $consignmentId,
        public readonly ?string $trackingCode = null,
    ) {}
}
