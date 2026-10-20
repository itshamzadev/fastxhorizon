<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;

interface CalculatesPrice
{
    public function calculatePrice(QuoteData $data): QuoteResult;
}
