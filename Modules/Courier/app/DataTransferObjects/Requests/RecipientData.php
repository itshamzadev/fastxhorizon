<?php

namespace Modules\Courier\app\DataTransferObjects\Requests;

class RecipientData
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $phone,
        public readonly string  $address,
        public readonly ?string $cityId = null,
        public readonly ?string $zoneId = null,
        public readonly ?string $areaId = null,
        public readonly ?string $secondaryPhone = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $cityName = null,
        public readonly ?string $stateProvince = null,
        public readonly ?string $latitude = null,
        public readonly ?string $longitude = null,
        public readonly ?string $sourceBranchName = null,
        public readonly ?string $sourceBranchId = null,
        public readonly ?string $destinationBranchName = null,
        public readonly ?string $destinationBranchId = null,
        public readonly array   $meta = [],
    ) {}
}
