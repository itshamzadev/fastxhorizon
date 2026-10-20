<?php

namespace Modules\Courier\app\Services;

use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\ValueObjects\CourierOwner;

class PlatformOwnerResolver implements CourierOwnerResolver
{
    public function resolve(): CourierOwner
    {
        return CourierOwner::platform();
    }
}
