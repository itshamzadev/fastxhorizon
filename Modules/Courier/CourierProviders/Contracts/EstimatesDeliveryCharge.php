<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;

interface EstimatesDeliveryCharge
{
    public function getDeliveryCharges(QuoteData $data): QuoteResult;
}
