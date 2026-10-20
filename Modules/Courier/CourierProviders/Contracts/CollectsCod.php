<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Responses\CodInfo;

interface CollectsCod
{
    public function getCodCollection(string $consignmentId): CodInfo;
}
