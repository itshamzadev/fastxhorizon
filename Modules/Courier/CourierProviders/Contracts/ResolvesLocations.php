<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Modules\Courier\app\DataTransferObjects\Responses\Location;

interface ResolvesLocations
{
    public function getCities(): array;

    public function getZones(string $cityId): array;

    public function getAreas(string $zoneId): array;
}
