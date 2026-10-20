<?php

namespace Modules\Courier\app\DataTransferObjects\Responses;

class QuoteResult
{
    public function __construct(
        public readonly float   $totalCost,
        public readonly ?float  $baseFee = null,
        public readonly ?float  $codFee = null,
        public readonly string  $currency = '',
        public readonly array   $raw = [],
    ) {}
}
