<?php

namespace App\Services\Courier;

use App\Models\Seller;
use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\ValueObjects\CourierOwner;

class HostCourierOwnerResolver implements CourierOwnerResolver
{
    public function resolve(): CourierOwner
    {
        $apiSeller = request()->get('seller');

        if ($apiSeller instanceof Seller) {
            return CourierOwner::vendor(id: (int) $apiSeller->id);
        }

        if (!str_starts_with((string) request()->route()?->getName(), 'vendor.')) {
            return CourierOwner::platform();
        }

        $vendorId = auth('seller')->id();

        throw_if($vendorId === null, CourierException::class, 'A vendor panel request cannot resolve its vendor owner.');

        return CourierOwner::vendor(id: (int) $vendorId);
    }
}
