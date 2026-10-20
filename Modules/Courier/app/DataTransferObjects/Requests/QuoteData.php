<?php

namespace Modules\Courier\app\DataTransferObjects\Requests;

class QuoteData
{
    public function __construct(
        public readonly float   $weight,
        public readonly float   $codAmount = 0.0,
        public readonly ?string $fromCityId = null,
        public readonly ?string $toCityId = null,
        public readonly ?string $toZoneId = null,
        public readonly ?string $toAreaId = null,
        public readonly ?string $deliveryType = null,
        public readonly ?string $toCountryCode = null,
        public readonly ?string $toPostalCode = null,
        public readonly ?string $toCityName = null,
        public readonly ?string $toLatitude = null,
        public readonly ?string $toLongitude = null,
        public readonly ?string $fromLatitude = null,
        public readonly ?string $fromLongitude = null,
        public readonly ?string $sourceBranchName = null,
        public readonly ?string $sourceBranchId = null,
        public readonly ?string $destinationBranchName = null,
        public readonly ?string $destinationBranchId = null,
        public readonly array   $meta = [],
    ) {}
}
