<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Responses\Location;

interface ProvidesStores
{
    public function getStores(): array;
}
