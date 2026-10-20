<?php

namespace Modules\Courier\app\Contracts;

use Modules\Courier\app\ValueObjects\CourierOwner;

interface CourierOwnerResolver
{
    public function resolve(): CourierOwner;
}
