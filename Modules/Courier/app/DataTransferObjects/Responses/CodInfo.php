<?php

namespace Modules\Courier\app\DataTransferObjects\Responses;

class CodInfo
{
    public function __construct(
        public readonly float   $collectedAmount,
        public readonly string  $status,
        public readonly ?string $settledAt = null,
        public readonly ?string $consignmentId = null,
        public readonly array   $raw = [],
    ) {}
}
