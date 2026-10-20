<?php

namespace Modules\Courier\app\DataTransferObjects\Requests;

class ItemData
{
    public function __construct(
        public readonly string  $name,
        public readonly int     $quantity,
        public readonly float   $price,
        public readonly ?float  $weight = null,
        public readonly ?string $sku = null,
        public readonly array   $meta = [],
    ) {}
}
