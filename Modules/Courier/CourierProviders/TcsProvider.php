<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CollectsCod;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\ItemData;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Responses\CodInfo;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Support\CountryList;
use Modules\Courier\app\Exceptions\CourierException;

class TcsProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    CancelsOrders,
    ResolvesLocations,
    CollectsCod
{
    private const SANDBOX_BASE_URL = 'https://devconnect.tcscourier.com';
    private const PRODUCTION_BASE_URL = 'https://ociconnect.tcscourier.com';

    private const ENDPOINT_TOKEN_CLIENT = '/auth/api/auth';
    private const ENDPOINT_TOKEN_USER = '/ecom/api/authentication/token';
    private const ENDPOINT_BOOKING_CREATE = '/ecom/api/booking/create';
    private const ENDPOINT_BOOKING_CANCEL = '/ecom/api/booking/cancel';
    private const ENDPOINT_TRACKING = '/tracking/api/Tracking/GetDynamicTrackDetail';
    private const ENDPOINT_CITY_LIST = '/ecom/api/setup/citylistbycountry';
    private const ENDPOINT_AREA_CODE = '/ecom/api/setup/areacode';
    private const ENDPOINT_BLOCK_CODE = '/ecom/api/setup/blockcode';
    private const ENDPOINT_PAYMENT_STATUS = '/ecom/api/Payment/status';

    private const MINIMUM_WEIGHT_KG = 0.5;
    private const MINIMUM_UNIT_PRICE = 1;
    private const MAXIMUM_COD_AMOUNT = 250000;

    private const DEFAULT_SERVICE_CODE = 'O';
    private const CURRENCY = 'PKR';

    private const SERVICE_OPTIONS = [
        ['id' => 'O', 'label' => 'Overnight'],
        ['id' => 'C', 'label' => 'Second Day'],
        ['id' => 'V', 'label' => 'Overland Economy'],
    ];

    private const YES_NO_OPTIONS = [
        ['id' => '0', 'label' => 'No'],
        ['id' => '1', 'label' => 'Yes'],
    ];

    private const SETUP_CACHE_HOURS = 24;
    private const TOKEN_EXPIRY_SKEW_SECONDS = 60;
    private const TOKEN_FALLBACK_MINUTES = 30;

    private const SIMULATED_CONSIGNMENT_PREFIX = 'TCS-SBX-';
    private const REDACTED = '***';

    public function getName(): string
    {
        return 'tcs';
    }

    protected function displayName(): string
    {
        return 'TCS Courier';
    }

    public function supportedCountries(): array
    {
        return ['PK'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => self::SANDBOX_BASE_URL,
            self::ENVIRONMENT_LIVE    => self::PRODUCTION_BASE_URL,
        ];
    }

    public function addressMode(): string
    {
        return 'catalog';
    }

    public function deliveryTypes(): array
    {
        return array_column(self::SERVICE_OPTIONS, 'label', 'id');
    }

    public function locationLevels(): array
    {
        return ['city', 'zone', 'area'];
    }

    public function addressFields(): array
    {
        return [];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'client_id', 'label' => 'TCS Client ID', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: tcs-3f9a7c12', 'help' => 'Issued by TCS. Fill in either this pair or the COD portal login below.'],
            ['key' => 'client_secret', 'label' => 'TCS Client Secret', 'type' => 'password', 'required' => false, 'placeholder' => 'Ex: 8b4d6e2a9c157f30', 'help' => 'Issued by TCS alongside the Client ID.'],
            ['key' => 'username', 'label' => 'COD Portal Username', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: riverside.traders', 'help' => 'Your tcscourier.com/cod login. Used only when no Client ID is set.'],
            ['key' => 'password', 'label' => 'COD Portal Password', 'type' => 'password', 'required' => false, 'help' => 'Password for the COD portal username.'],

            ['key' => 'tcs_account', 'label' => 'TCS Account Number', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 04011K1', 'help' => 'Account number issued by TCS when your contract was signed.'],
            ['key' => 'cost_center_code', 'label' => 'Cost Center Code', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: KHI001', 'help' => 'Must already exist on your TCS account. Every booking is tied to one.'],
            ['key' => 'service_code', 'label' => 'Delivery Service', 'type' => 'select', 'required' => false, 'options' => self::SERVICE_OPTIONS, 'help' => 'Valid services are account-specific — confirm yours with TCS.'],

            ['key' => 'shipper_name', 'label' => 'Business Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Riverside Traders', 'help' => 'Shown as the sender on the shipping label.'],
            ['key' => 'shipper_mobile', 'label' => 'Pickup Contact Mobile', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 03001234567', 'help' => 'The number TCS calls to arrange a pickup. 11 digits.'],
            ['key' => 'shipper_address', 'label' => 'Pickup Street Address', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Shop 12, Block 6, PECHS', 'help' => 'Where TCS collects your parcels.'],
            ['key' => 'shipper_city_name', 'label' => 'Pickup City', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Karachi', 'help' => 'The city TCS collects your parcels from, written the way TCS spells it.'],
            ['key' => 'shipper_city_code', 'label' => 'Pickup City Code', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: KHI', 'help' => 'The TCS short code for the pickup city. Ask TCS if you are unsure.'],
            ['key' => 'shipper_zip', 'label' => 'Pickup Postal Code', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 75400', 'help' => 'Postal code of the pickup address.'],

            ['key' => 'enable_simulation', 'label' => 'Use Test Data in Sandbox', 'type' => 'select', 'required' => false, 'options' => self::YES_NO_OPTIONS, 'help' => 'Returns sample cities, consignment numbers and tracking so you can trial the screens before TCS grants live access.'],
        ];
    }

    public function getCities(): array
    {
        if ($this->simulating()) {
            return $this->simulatedCities();
        }

        return $this->locations(
            cacheKey: 'cities.'.$this->countryCode(),
            endpoint: self::ENDPOINT_CITY_LIST,
            payload: ['countrycode' => [$this->countryCode()]],
            level: 'city',
            codeKey: 'citycode',
            nameKey: 'cityname',
        );
    }

    public function getZones(string $cityId): array
    {
        if ($this->simulating()) {
            return $this->simulatedZones($cityId);
        }

        return $this->locations(
            cacheKey: 'areas.'.$cityId,
            endpoint: self::ENDPOINT_AREA_CODE,
            payload: ['citycode' => $cityId, 'area' => ''],
            level: 'zone',
            codeKey: 'areacode',
            nameKey: 'areaname',
            parentId: $cityId,
        );
    }

    public function getAreas(string $zoneId): array
    {
        if ($this->simulating()) {
            return $this->simulatedAreas($zoneId);
        }

        return $this->locations(
            cacheKey: 'blocks.'.$zoneId,
            endpoint: self::ENDPOINT_BLOCK_CODE,
            payload: ['area' => $zoneId, 'blockcode' => ''],
            level: 'area',
            codeKey: 'blockcode',
            nameKey: 'blockname',
            parentId: $zoneId,
        );
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $this->assertCodWithinLimit($data->codAmount);

        if ($this->simulating()) {
            return $this->simulatedShipment($data);
        }

        $body = $this->decode($this->post(self::ENDPOINT_BOOKING_CREATE, $this->bookingPayload($data)));
        $consignmentNo = (string) ($body['consignmentNo'] ?? '');

        if ($consignmentNo === '' || ($body['status'] ?? null) === false) {
            throw new CourierException($this->errorMessage($body, 'TCS booking failed.'));
        }

        return new ShipmentResult(
            consignmentId: $consignmentNo,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $consignmentNo,
            codAmount: $data->codAmount,
            raw: $body,
        );
    }

    public function getOrderDetails(string $consignmentId): ShipmentResult
    {
        return $this->orderInfo($consignmentId);
    }

    public function getOrderShortInfo(string $consignmentId): ShipmentResult
    {
        return $this->orderInfo($consignmentId);
    }

    public function getOrderStatus(string $consignmentId): ShipmentResult
    {
        return $this->orderInfo($consignmentId);
    }

    public function trackOrder(string $consignmentId): array
    {
        if ($this->simulating()) {
            return $this->simulatedTrackingEvents();
        }

        $checkpoints = (array) ($this->trackingBody($consignmentId)['checkpoints'] ?? []);

        return array_values(array_map(function ($checkpoint): TrackingEvent {
            $checkpoint = (array) $checkpoint;
            $status = (string) ($checkpoint['status'] ?? '');

            return new TrackingEvent(
                status: $this->mapStatusText($status),
                occurredAt: (string) ($checkpoint['datetime'] ?? ''),
                location: $checkpoint['recievedby'] ?? null,
                description: $status !== '' ? $status : null,
                raw: $checkpoint,
            );
        }, $checkpoints));
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        if (!$this->simulating()) {
            $body = $this->decode($this->post(self::ENDPOINT_BOOKING_CANCEL, ['consignmentNumber' => $consignmentId]));

            if (!str_starts_with(strtoupper(trim((string) ($body['message'] ?? ''))), 'SUCCESS')) {
                throw new CourierException($this->errorMessage($body, 'TCS could not cancel this consignment.'));
            }
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Cancelled,
            trackingCode: $consignmentId,
        );
    }

    public function getCodCollection(string $consignmentId): CodInfo
    {
        if ($this->simulating()) {
            return $this->unsettledCod($consignmentId, ['simulated' => true]);
        }

        $body = $this->decode($this->get(self::ENDPOINT_PAYMENT_STATUS, [
            'customerno'    => $this->credential('tcs_account'),
            'consignmentno' => $consignmentId,
        ]));

        $record = $this->paymentRecord($body);

        if ($record === []) {
            return $this->unsettledCod($consignmentId, $body);
        }

        return new CodInfo(
            collectedAmount: (float) $this->pick($record, ['amount paid', 'amountpaid', 'amount_paid'], 0),
            status: (string) $this->pick($record, ['payment status', 'paymentstatus', 'payment_status'], 'unknown'),
            settledAt: $this->pick($record, ['payment date', 'paymentdate', 'payment_date']),
            consignmentId: $consignmentId,
            raw: $record,
        );
    }

    public static function redactCredentials(array $payload): array
    {
        foreach (['client_secret', 'password'] as $secret) {
            if (array_key_exists($secret, $payload)) {
                $payload[$secret] = self::REDACTED;
            }
        }

        return $payload;
    }

    protected function defaultHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token()];
    }

    protected function token(): string
    {
        $key = $this->tokenCacheKey();

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        [$endpoint, $payload] = $this->authRequest();

        $response = Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->acceptJson()
            ->withBody(json_encode($payload), 'application/json')
            ->send('GET', $endpoint);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();
        $token = (string) (($body['result']['accessToken'] ?? null) ?? $body['accesstoken'] ?? '');

        if ($token === '') {
            throw new CourierException($this->errorMessage($body, 'TCS authentication failed.'));
        }

        Cache::put($key, $token, $this->tokenExpiry($body));

        return $token;
    }

    private function authRequest(): array
    {
        if ($this->hasCredentialPair('client_id', 'client_secret')) {
            return [self::ENDPOINT_TOKEN_CLIENT, [
                'clientid'     => $this->credential('client_id'),
                'clientsecret' => $this->credential('client_secret'),
            ]];
        }

        if ($this->hasCredentialPair('username', 'password')) {
            return [self::ENDPOINT_TOKEN_USER, [
                'username' => $this->credential('username'),
                'password' => $this->credential('password'),
            ]];
        }

        throw new CourierException('TCS needs either a Client ID and Client Secret, or a COD portal Username and Password.');
    }

    private function tokenExpiry(array $body): \DateTimeInterface
    {
        $expiry = (string) (($body['result']['expiry'] ?? null) ?? $body['expiry'] ?? '');
        $timestamp = $expiry !== '' ? strtotime($expiry) : false;
        $expiresAt = $timestamp === false ? null : now()->setTimestamp($timestamp)->subSeconds(self::TOKEN_EXPIRY_SKEW_SECONDS);

        return $expiresAt !== null && $expiresAt->isFuture()
            ? $expiresAt
            : now()->addMinutes(self::TOKEN_FALLBACK_MINUTES);
    }

    private function tokenCacheKey(): string
    {
        $identity = $this->credential('client_id', $this->credential('username'));

        return 'courier.tcs.token.'.md5($identity.'|'.$this->baseUrl());
    }

    private function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    private function get(string $endpoint, array $payload = []): Response
    {
        return $this->withAuthRetry(fn (PendingRequest $http): Response => $http
            ->withBody(json_encode($this->withToken($payload)), 'application/json')
            ->send('GET', $endpoint));
    }

    private function post(string $endpoint, array $payload = []): Response
    {
        return $this->withAuthRetry(fn (PendingRequest $http): Response => $http->post($endpoint, $this->withToken($payload)));
    }

    private function withAuthRetry(callable $call): Response
    {
        $response = $call($this->http());

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $call($this->http());
        }

        return $response;
    }

    private function withToken(array $payload): array
    {
        return $payload + ['accesstoken' => $this->token()];
    }

    private function decode(Response $response): array
    {
        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return (array) $response->json();
    }

    private function errorMessage(array $body, string $fallback): string
    {
        $reasons = [];
        $errors = (array) ($body['error'] ?? []);

        array_walk_recursive($errors, static function ($value) use (&$reasons): void {
            if (is_string($value) && $value !== '') {
                $reasons[] = $value;
            }
        });

        if ($reasons !== []) {
            return implode('; ', array_unique($reasons));
        }

        foreach (['message', 'MESSAGE'] as $key) {
            if (isset($body[$key]) && is_string($body[$key]) && $body[$key] !== '') {
                return $body[$key];
            }
        }

        return $fallback;
    }

    private function bookingPayload(OrderData $data): array
    {
        return [
            'consignmentno' => '',
            'shipperinfo'   => $this->shipperInfo(),
            'consigneeinfo' => $this->consigneeInfo($data),
            'shipmentinfo'  => $this->shipmentInfo($data),
        ];
    }

    private function shipperInfo(): array
    {
        return $this->withoutBlanks([
            'tcsaccount'  => $this->credential('tcs_account'),
            'shippername' => $this->credential('shipper_name'),
            'address1'    => $this->credential('shipper_address'),
            'zip'         => $this->credential('shipper_zip'),
            'countrycode' => $this->countryCode(),
            'countryname' => $this->countryName(),
            'citycode'    => $this->credential('shipper_city_code'),
            'cityname'    => $this->credential('shipper_city_name'),
            'mobile'      => $this->credential('shipper_mobile'),
        ]);
    }

    private function consigneeInfo(OrderData $data): array
    {
        $recipient = $data->recipient;
        [$firstName, $middleName, $lastName] = $this->nameParts($recipient->name);

        $cityCode = (string) ($recipient->cityId ?? '');
        $areaCode = (string) ($recipient->zoneId ?? '');
        $blockCode = (string) ($recipient->areaId ?? '');
        $cityName = (string) ($recipient->cityName ?? '') ?: $this->cityName($cityCode);

        if ($cityName === '') {
            throw new CourierException('TCS requires a destination city — select one before sending this order.');
        }

        return $this->withoutBlanks([
            'firstname'   => $firstName,
            'middlename'  => $middleName,
            'lastname'    => $lastName,
            'address1'    => $recipient->address,
            'zip'         => $recipient->postalCode,
            'countrycode' => $recipient->countryCode ?: $this->country(),
            'countryname' => $this->countryName(),
            'citycode'    => $cityCode,
            'cityname'    => $cityName,
            'email'       => $recipient->meta['email'] ?? ($data->meta['email'] ?? ''),
            'areacode'    => $areaCode,
            'areaname'    => $areaCode === '' ? '' : $this->areaName($cityCode, $areaCode),
            'blockcode'   => $blockCode,
            'blockname'   => $blockCode === '' ? '' : $this->blockName($areaCode, $blockCode),
            'lat'         => $recipient->latitude,
            'lng'         => $recipient->longitude,
            'landmark'    => $recipient->meta['landmark'] ?? '',
            'mobile'      => $recipient->phone,
        ]);
    }

    private function shipmentInfo(OrderData $data): array
    {
        $weight = $this->billableWeight($data->weight);

        return $this->withoutBlanks([
            'costcentercode' => $this->credential('cost_center_code'),
            'referenceno'    => $data->hostOrderReference,
            'contentdesc'    => $data->itemDescription,
            'servicecode'    => $this->serviceCode($data),
            'currency'       => self::CURRENCY,
            'codamount'      => round($data->codAmount, 2),
            'weightinkg'     => $weight,
            'pieces'         => $this->pieces($data),
            'remarks'        => $data->note,
            'fragile'        => false,
            'skus'           => $this->skus($data, $weight),
        ]);
    }

    private function skus(OrderData $data, float $weight): array
    {
        if ($data->items === []) {
            return [[
                'description' => $data->itemDescription ?? $data->hostOrderReference,
                'quantity'    => $this->pieces($data),
                'weight'      => $weight,
                'uom'         => 'KG',
                'unitprice'   => max(self::MINIMUM_UNIT_PRICE, round($data->codAmount, 2)),
            ]];
        }

        return array_map(fn (ItemData $item): array => [
            'description' => $item->name,
            'quantity'    => max(1, $item->quantity),
            'weight'      => $this->billableWeight((float) ($item->weight ?? $weight)),
            'uom'         => 'KG',
            'unitprice'   => max(self::MINIMUM_UNIT_PRICE, round($item->price, 2)),
        ], $data->items);
    }

    private function pieces(OrderData $data): int
    {
        return max(1, $data->quantity ?? count($data->items));
    }

    private function billableWeight(float $weight): float
    {
        return max(self::MINIMUM_WEIGHT_KG, round($weight, 2));
    }

    private function assertCodWithinLimit(float $codAmount): void
    {
        if ($codAmount > self::MAXIMUM_COD_AMOUNT) {
            throw new CourierException('TCS cannot collect more than '.number_format(self::MAXIMUM_COD_AMOUNT).' on delivery.');
        }
    }

    private function nameParts(string $name): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: [], 'strlen'));
        $firstName = $parts[0] ?? $name;

        return [
            $firstName,
            count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : $firstName,
            count($parts) > 1 ? (string) end($parts) : '',
        ];
    }

    private function withoutBlanks(array $payload): array
    {
        return array_filter($payload, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function locations(
        string $cacheKey,
        string $endpoint,
        array $payload,
        string $level,
        string $codeKey,
        string $nameKey,
        ?string $parentId = null,
    ): array {
        $rows = Cache::remember(
            $this->setupCacheKey($cacheKey),
            now()->addHours(self::SETUP_CACHE_HOURS),
            fn (): array => $this->setupRows($endpoint, $payload),
        );

        return array_values(array_filter(array_map(
            static function (array $row) use ($level, $codeKey, $nameKey, $parentId): ?Location {
                $code = (string) ($row[$codeKey] ?? '');

                return $code === '' ? null : new Location(
                    id: $code,
                    name: (string) ($row[$nameKey] ?? '') ?: $code,
                    level: $level,
                    parentId: $parentId,
                    raw: $row,
                );
            },
            $rows,
        )));
    }

    private function setupRows(string $endpoint, array $payload): array
    {
        $body = $this->decode($this->get($endpoint, $payload));
        $rows = $body['data'] ?? $body['detail'] ?? $body['result'] ?? [];

        if (!is_array($rows) || $rows === []) {
            throw new CourierException($this->errorMessage($body, 'TCS returned no locations for this selection.'));
        }

        return array_values(array_map(static fn ($row): array => (array) $row, $rows));
    }

    private function setupCacheKey(string $key): string
    {
        return 'courier.tcs.setup.'.md5($this->baseUrl().'|'.$key);
    }

    private function cityName(string $cityCode): string
    {
        return $cityCode === '' ? '' : $this->locationName($this->getCities(), $cityCode);
    }

    private function areaName(string $cityCode, string $areaCode): string
    {
        return $cityCode === '' ? '' : $this->locationName($this->getZones($cityCode), $areaCode);
    }

    private function blockName(string $areaCode, string $blockCode): string
    {
        return $areaCode === '' ? '' : $this->locationName($this->getAreas($areaCode), $blockCode);
    }

    private function locationName(array $locations, string $id): string
    {
        foreach ($locations as $location) {
            if ($location->id === $id) {
                return $location->name;
            }
        }

        return '';
    }

    private function orderInfo(string $consignmentId): ShipmentResult
    {
        if ($this->simulating()) {
            return new ShipmentResult(
                consignmentId: $consignmentId,
                status: ShipmentStatus::InTransit,
                trackingCode: $consignmentId,
                raw: ['simulated' => true],
            );
        }

        $body = $this->trackingBody($consignmentId);
        $deliveryInfo = (array) ($body['deliveryinfo'] ?? []);
        $checkpoints = (array) ($body['checkpoints'] ?? []);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->resolveStatus((array) ($deliveryInfo[0] ?? []), (array) ($checkpoints[0] ?? [])),
            trackingCode: $consignmentId,
            raw: $body,
        );
    }

    private function trackingBody(string $consignmentId): array
    {
        $body = $this->decode($this->get(self::ENDPOINT_TRACKING, ['consignee' => [$consignmentId]]));

        if (strtoupper(trim((string) ($body['message'] ?? ''))) !== 'SUCCESS') {
            $summary = (string) ($body['shipmentsummary'] ?? '');

            throw new CourierException($summary !== '' ? $summary : $this->errorMessage($body, 'TCS could not track this consignment.'));
        }

        return $body;
    }

    private function resolveStatus(array $delivery, array $latestCheckpoint): ShipmentStatus
    {
        return $this->mapStatusCode((string) ($delivery['code'] ?? ''))
            ?? $this->mapStatusText((string) ($delivery['status'] ?? $latestCheckpoint['status'] ?? ''));
    }

    private function mapStatusCode(string $code): ?ShipmentStatus
    {
        return match (strtoupper(trim($code))) {
            'OK' => ShipmentStatus::Delivered,
            'RO' => ShipmentStatus::Returned,
            'SC', 'DBC', 'DFC', 'DAE', 'DFM' => ShipmentStatus::OnHold,
            default => null,
        };
    }

    private function mapStatusText(string $raw): ShipmentStatus
    {
        $status = strtolower(trim($raw));

        return match (true) {
            str_contains($status, 'out for delivery') => ShipmentStatus::OutForDelivery,
            str_contains($status, 'return')           => ShipmentStatus::Returned,
            str_contains($status, 'deliver')          => ShipmentStatus::Delivered,
            str_contains($status, 'pick')             => ShipmentStatus::PickedUp,
            str_contains($status, 'transit'), str_contains($status, 'arrived'), str_contains($status, 'facility'), str_contains($status, 'dispatch') => ShipmentStatus::InTransit,
            str_contains($status, 'cancel')           => ShipmentStatus::Cancelled,
            str_contains($status, 'hold'), str_contains($status, 'delay') => ShipmentStatus::OnHold,
            str_contains($status, 'book'), str_contains($status, 'pending') => ShipmentStatus::Pending,
            default                                   => ShipmentStatus::Unknown,
        };
    }

    private function paymentRecord(array $body): array
    {
        $rows = (array) ($body['data'] ?? $body['result'] ?? []);

        if ($rows === []) {
            return [];
        }

        return (array) (array_is_list($rows) ? ($rows[0] ?? []) : $rows);
    }

    private function pick(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return $default;
    }

    private function unsettledCod(string $consignmentId, array $raw): CodInfo
    {
        return new CodInfo(
            collectedAmount: 0.0,
            status: 'unsettled',
            consignmentId: $consignmentId,
            raw: $raw,
        );
    }

    private function countryCode(): string
    {
        return $this->country();
    }

    private function serviceCode(OrderData $data): string
    {
        $services = $this->deliveryTypes();
        $selected = (string) ($data->meta['delivery_type'] ?? '');
        $configured = $this->credential('service_code');

        return match (true) {
            isset($services[$selected])   => $selected,
            isset($services[$configured]) => $configured,
            default                       => self::DEFAULT_SERVICE_CODE,
        };
    }

    private function countryName(): string
    {
        return (string) CountryList::nameFor($this->country());
    }

    public function credentialsComplete(array $credentials): bool
    {
        $pair = static fn (string $first, string $second): bool => filled($credentials[$first] ?? null) && filled($credentials[$second] ?? null);

        return $pair('client_id', 'client_secret') || $pair('username', 'password');
    }

    private function credential(string $key, string $default = ''): string
    {
        $value = $this->credentials[$key] ?? null;

        return filled($value) ? (string) $value : $default;
    }

    private function hasCredentialPair(string $first, string $second): bool
    {
        return $this->credential($first) !== '' && $this->credential($second) !== '';
    }

    private function simulating(): bool
    {
        return $this->environment() === self::ENVIRONMENT_SANDBOX
            && filter_var($this->credentials['enable_simulation'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function simulatedCities(): array
    {
        return array_map(
            static fn (array $city): Location => new Location(
                id: $city['citycode'],
                name: $city['cityname'],
                level: 'city',
                raw: $city,
            ),
            [
                ['citycode' => 'KHI', 'cityname' => 'Karachi'],
                ['citycode' => 'LHE', 'cityname' => 'Lahore'],
                ['citycode' => 'ISB', 'cityname' => 'Islamabad'],
                ['citycode' => 'RWP', 'cityname' => 'Rawalpindi'],
                ['citycode' => 'FSD', 'cityname' => 'Faisalabad'],
            ],
        );
    }

    private function simulatedZones(string $cityId): array
    {
        return array_map(
            static fn (array $area): Location => new Location(
                id: $area['areacode'],
                name: $area['areaname'],
                level: 'zone',
                parentId: $cityId,
                raw: $area,
            ),
            [
                ['areacode' => $cityId.'00001', 'areaname' => 'Gulshan'],
                ['areacode' => $cityId.'00002', 'areaname' => 'Clifton'],
                ['areacode' => $cityId.'00003', 'areaname' => 'DHA'],
            ],
        );
    }

    private function simulatedAreas(string $zoneId): array
    {
        return array_map(
            static fn (array $block): Location => new Location(
                id: $block['blockcode'],
                name: $block['blockname'],
                level: 'area',
                parentId: $zoneId,
                raw: $block,
            ),
            [
                ['blockcode' => $zoneId.'B1', 'blockname' => 'Block 1'],
                ['blockcode' => $zoneId.'B2', 'blockname' => 'Block 2'],
            ],
        );
    }

    private function simulatedShipment(OrderData $data): ShipmentResult
    {
        $consignmentNo = self::SIMULATED_CONSIGNMENT_PREFIX.strtoupper(substr(md5($data->hostOrderReference), 0, 9));

        return new ShipmentResult(
            consignmentId: $consignmentNo,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $consignmentNo,
            codAmount: $data->codAmount,
            raw: ['simulated' => true],
        );
    }

    private function simulatedTrackingEvents(): array
    {
        return [
            new TrackingEvent(
                status: ShipmentStatus::InTransit,
                occurredAt: (string) now(),
                location: 'Karachi',
                description: 'Arrived at TCS Facility',
                raw: ['simulated' => true],
            ),
            new TrackingEvent(
                status: ShipmentStatus::Pending,
                occurredAt: (string) now()->subDay(),
                location: 'Karachi',
                description: 'Shipment Booked',
                raw: ['simulated' => true],
            ),
        ];
    }
}
