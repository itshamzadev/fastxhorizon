<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Models\CourierProviderAddress;

class GarudaExpressProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    CancelsOrders,
    ResolvesLocations
{
    private const HOST = 'https://www.thegarudaexpress.com';

    private const ENDPOINT_DISTRICTS = '/api/districts';
    private const ENDPOINT_ADDRESSES = '/api/addresses';
    private const ENDPOINT_ORDERS = '/api/orders';

    private const DISTRICT_CACHE_TTL = 86400;

    private const AMOUNT_AS_COD = 'cod';
    private const AMOUNT_AS_DECLARED = 'declared';

    private const CURRENCY = 'NPR';
    private const MINIMUM_ITEMS = 1;

    private const SIMULATED_ORDER_PREFIX = 'TGE-SBX-';
    private const SIMULATED_TRACKING_PREFIX = 'TGE';

    private const RFC_2616_TYPE = 'tools.ietf.org/html/rfc2616';

    public function getName(): string
    {
        return 'garuda_express';
    }

    protected function displayName(): string
    {
        return 'The Garuda Express';
    }

    public function supportedCountries(): array
    {
        return ['NP'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => self::HOST,
            self::ENVIRONMENT_LIVE    => self::HOST,
        ];
    }

    public function locationLevels(): array
    {
        return ['city'];
    }

    public function locationLabels(): array
    {
        return ['city' => 'District'];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: tge_live_6c2a94e1b7f3', 'help' => 'Request one from The Garuda Express for your registered merchant account.'],
            ['key' => 'amount_semantics', 'label' => 'Order Amount Meaning', 'type' => 'select', 'required' => false, 'options' => [
                ['id' => self::AMOUNT_AS_COD, 'label' => 'COD amount to collect'],
                ['id' => self::AMOUNT_AS_DECLARED, 'label' => 'Declared value only'],
            ], 'help' => 'TGE does not document what packageOrderAmount means. Confirm with them in writing: choosing COD sends 0 for prepaid orders, declared value always sends the order value.'],

            ['key' => 'sender_name', 'label' => 'Pickup Contact Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Alex Karim'],
            ['key' => 'sender_mobile', 'label' => 'Pickup Contact Mobile', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 9800000000', 'help' => 'The number Garuda calls to arrange a pickup. 10 digits.'],
            ['key' => 'sender_address', 'label' => 'Pickup Street Address', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Koteshwor, Mahadevsthan', 'help' => 'Where Garuda collects your parcels.'],
            ['key' => 'sender_district', 'label' => 'Pickup District', 'type' => 'select', 'required' => true, 'options' => $this->districtOptions(), 'translatable_options' => false, 'help' => 'The district your parcels are collected from.'],
            ['key' => 'sender_email', 'label' => 'Pickup Contact Email', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: pickup@example.com'],
            ['key' => 'sender_pin_code', 'label' => 'Pickup Postal Code', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 44600', 'help' => 'Postal code of the pickup address. 5 digits.'],
            ['key' => 'pickup_note', 'label' => 'Default Pickup Note', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Collect from the back gate after 4 PM', 'help' => 'Standing instruction for the rider, used when an order carries no note of its own.'],
        ];
    }

    public function getCities(): array
    {
        return array_map(
            static fn (array $district): Location => new Location(
                id: (string) $district['id'],
                name: (string) $district['name'],
                level: 'city',
                raw: $district,
            ),
            $this->districts(),
        );
    }

    public function getZones(string $cityId): array
    {
        return [];
    }

    public function getAreas(string $zoneId): array
    {
        return [];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $districtId = $this->recipientDistrict($data->recipient);
        $pickup = $this->pickupAddress();

        if ($this->simulating()) {
            return $this->simulatedShipment($data);
        }

        $deliveryAddressId = $this->resolveAddress([
            'name'     => $data->recipient->name,
            'mobile'   => $data->recipient->phone,
            'address'  => $data->recipient->address,
            'district' => $districtId,
            'email'    => $data->recipient->meta['email'] ?? null,
            'pinCode'  => $data->recipient->postalCode,
            'latitude' => $data->recipient->latitude,
            'longitude' => $data->recipient->longitude,
        ]);

        $pickupAddressId = $this->resolveAddress($pickup);

        $body = $this->decode($this->post(self::ENDPOINT_ORDERS, ['order' => array_filter([
            'pickupAddress'     => $pickupAddressId,
            'deliveryAddress'   => $deliveryAddressId,
            'packageWeight'     => (string) $data->weight,
            'packageTotalItems' => (string) max(self::MINIMUM_ITEMS, $data->quantity ?? count($data->items)),
            'packageOrderAmount' => $this->packageOrderAmount($data),
            'pickupDate'        => $this->pickupDate(),
            'note'              => $data->note ?: ($this->credentials['pickup_note'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '')]));

        $order = $this->record($body);
        $orderId = (string) $this->value($order, ['id', 'orderId', 'order_id']);

        if ($orderId === '') {
            throw new CourierException($this->errorMessage($body, 'The Garuda Express did not return an order id.'));
        }

        return new ShipmentResult(
            consignmentId: $orderId,
            status: $this->mapStatus((string) $this->value($order, ['status'], 'NEW'), $orderId),
            hostOrderReference: $data->hostOrderReference,
            trackingCode: (string) $this->value($order, ['trackingNumber', 'tracking_number', 'number', 'trackingCode'], $orderId),
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

        $order = $this->record($this->decode($this->get(self::ENDPOINT_ORDERS.'/'.rawurlencode($consignmentId))));
        $history = $this->value($order, ['statuses', 'history', 'trackingHistory', 'events']);

        if (!is_array($history) || $history === []) {
            return [new TrackingEvent(
                status: $this->mapStatus((string) $this->value($order, ['status'], ''), $consignmentId),
                occurredAt: (string) $this->value($order, ['updatedAt', 'updated_at', 'createdAt', 'created_at'], ''),
                description: (string) $this->value($order, ['status'], ''),
                raw: $order,
            )];
        }

        return array_values(array_map(
            fn (array $event): TrackingEvent => new TrackingEvent(
                status: $this->mapStatus((string) $this->value($event, ['status', 'state']), $consignmentId),
                occurredAt: (string) $this->value($event, ['createdAt', 'created_at', 'time', 'date'], ''),
                description: (string) $this->value($event, ['remarks', 'description', 'status'], ''),
                raw: $event,
            ),
            array_filter($history, 'is_array'),
        ));
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        if ($this->simulating()) {
            return new ShipmentResult(
                consignmentId: $consignmentId,
                status: ShipmentStatus::Cancelled,
                raw: ['simulated' => true],
            );
        }

        $response = $this->client()->delete(self::ENDPOINT_ORDERS.'/'.rawurlencode($consignmentId));

        if ($response->failed()) {
            $this->fail($response);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Cancelled,
            raw: (array) $response->json(),
        );
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

        $body = $this->decode($this->get(self::ENDPOINT_ORDERS.'/'.rawurlencode($consignmentId)));
        $order = $this->record($body);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus((string) $this->value($order, ['status'], ''), $consignmentId),
            trackingCode: (string) $this->value($order, ['trackingNumber', 'tracking_number', 'number'], $consignmentId),
            raw: $body,
        );
    }

    private function resolveAddress(array $address): string
    {
        $payload = array_filter($address, static fn ($value) => $value !== null && $value !== '');
        $hash = $this->addressHash($payload);

        $stored = CourierProviderAddress::query()
            ->forAddress($this->owner(), $this->getName(), $this->environment(), $hash)
            ->value('remote_address_id');

        if ($stored !== null) {
            return (string) $stored;
        }

        $body = $this->decode($this->post(self::ENDPOINT_ADDRESSES, ['address' => array_map('strval', $payload)]));
        $addressId = (string) $this->value($this->record($body), ['id', 'addressId', 'address_id']);

        if ($addressId === '') {
            throw new CourierException($this->errorMessage($body, 'The Garuda Express did not return an address id.'));
        }

        CourierProviderAddress::create([
            'owner_type'        => $this->owner()->type,
            'owner_id'          => $this->owner()->id,
            'provider'          => $this->getName(),
            'environment'       => $this->environment(),
            'address_hash'      => $hash,
            'remote_address_id' => $addressId,
            'payload'           => $payload,
        ]);

        return $addressId;
    }

    private function addressHash(array $address): string
    {
        $normalized = array_map(
            static fn (string $key): string => strtolower(preg_replace('/\s+/', ' ', trim((string) ($address[$key] ?? '')))),
            ['name', 'mobile', 'address', 'district', 'pinCode'],
        );

        return md5(implode('|', $normalized));
    }

    private function pickupAddress(): array
    {
        $missing = array_values(array_filter(
            ['sender_name' => 'Pickup Contact Name', 'sender_mobile' => 'Pickup Mobile', 'sender_address' => 'Pickup Address', 'sender_district' => 'Pickup District ID'],
            fn (string $label, string $key): bool => trim((string) ($this->credentials[$key] ?? '')) === '',
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($missing !== []) {
            throw new CourierException('The Garuda Express pickup address is incomplete. Set '.implode(', ', $missing).' in the courier settings.');
        }

        return [
            'name'     => $this->credentials['sender_name'],
            'mobile'   => $this->credentials['sender_mobile'],
            'address'  => $this->credentials['sender_address'],
            'district' => $this->credentials['sender_district'],
            'email'    => $this->credentials['sender_email'] ?? null,
            'pinCode'  => $this->credentials['sender_pin_code'] ?? null,
        ];
    }

    private function recipientDistrict(RecipientData $recipient): string
    {
        $districtId = trim((string) ($recipient->cityId ?? ''));

        if ($districtId === '') {
            throw new CourierException('Select a delivery district for The Garuda Express.');
        }

        return $districtId;
    }

    private function packageOrderAmount(OrderData $data): float
    {
        if ($this->amountSemantics() === self::AMOUNT_AS_DECLARED) {
            return (float) ($data->meta['order_value'] ?? $data->codAmount);
        }

        return $data->codAmount;
    }

    private function amountSemantics(): string
    {
        return ($this->credentials['amount_semantics'] ?? null) === self::AMOUNT_AS_DECLARED
            ? self::AMOUNT_AS_DECLARED
            : self::AMOUNT_AS_COD;
    }

    private function pickupDate(): string
    {
        return now()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    private function districts(): array
    {
        if ($this->simulating()) {
            return array_slice($this->bundledDistricts(), 0, 5);
        }

        return Cache::remember(
            'courier.garuda_express.districts.'.md5($this->baseUrl()),
            self::DISTRICT_CACHE_TTL,
            function (): array {
                $rows = $this->fetchDistricts();

                return $rows !== [] ? $rows : $this->bundledDistricts();
            },
        );
    }

    private function fetchDistricts(): array
    {
        $response = Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->acceptJson()
            ->get(self::ENDPOINT_DISTRICTS);

        if ($response->failed()) {
            return [];
        }

        return array_values(array_map(
            fn (array $row): array => [
                'id'       => (string) $this->value($row, ['id']),
                'name'     => (string) $this->value($row, ['name']),
                'code'     => (string) $this->value($row, ['code'], ''),
                'province' => (string) $this->value((array) $this->value($row, ['state'], []), ['name'], ''),
            ],
            array_filter((array) $response->json('data', []), 'is_array'),
        ));
    }

    private function districtOptions(): array
    {
        return array_map(
            static fn (array $district): array => ['id' => (string) $district['id'], 'label' => (string) $district['name']],
            $this->bundledDistricts(),
        );
    }

    private function bundledDistricts(): array
    {
        return require dirname(__DIR__).'/resources/data/garuda-districts.php';
    }

    private function post(string $endpoint, array $payload): Response
    {
        $response = $this->client()->post($endpoint, $payload);

        if ($response->failed()) {
            $this->fail($response);
        }

        return $response;
    }

    private function get(string $endpoint, array $query = []): Response
    {
        $response = $this->client()->get($endpoint, $query);

        if ($response->failed()) {
            $this->fail($response);
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->withHeaders([
                'apikey'       => (string) ($this->credentials['api_key'] ?? ''),
                'Content-Type' => 'application/json',
            ])
            ->acceptJson();
    }

    private function fail(Response $response): never
    {
        if ($response->status() === 401) {
            throw new CourierException('The Garuda Express rejected the request: the API key is missing.');
        }

        if ($response->status() === 500 && str_contains((string) $response->json('type'), self::RFC_2616_TYPE)) {
            throw new CourierException('The Garuda Express rejected the request: the API key is invalid.');
        }

        $this->failFromResponse($response);
    }

    private function decode(Response $response): array
    {
        return (array) $response->json();
    }

    private function record(array $body): array
    {
        $data = $this->value($body, ['data', 'order', 'address', 'result'], $body);

        if (is_array($data) && $data !== [] && array_is_list($data)) {
            $data = $data[0] ?? [];
        }

        return (array) $data;
    }

    private function value(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return $default;
    }

    private function errorMessage(array $body, string $fallback): string
    {
        $message = $this->value($body, ['message', 'detail', 'title', 'error']);

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    protected function mapStatus(string $raw, string $consignmentId = ''): ShipmentStatus
    {
        $status = str_replace(['_', '-'], ' ', strtolower(trim($raw)));

        $mapped = match ($status) {
            ''                              => ShipmentStatus::Unknown,
            'new', 'pending', 'created'     => ShipmentStatus::Pending,
            'pickup requested', 'pickup assigned' => ShipmentStatus::PickupRequested,
            'picked', 'picked up', 'pickup complete' => ShipmentStatus::PickedUp,
            'at hub', 'arrived', 'received' => ShipmentStatus::AtHub,
            'in transit', 'dispatched'      => ShipmentStatus::InTransit,
            'out for delivery'              => ShipmentStatus::OutForDelivery,
            'delivered', 'complete', 'completed' => ShipmentStatus::Delivered,
            'return initiated', 'return requested' => ShipmentStatus::ReturnInitiated,
            'returned'                      => ShipmentStatus::Returned,
            'cancelled', 'canceled', 'deleted' => ShipmentStatus::Cancelled,
            'on hold', 'hold'               => ShipmentStatus::OnHold,
            'failed', 'delivery failed'     => ShipmentStatus::Failed,
            default                         => null,
        };

        if ($mapped === null) {
            Log::warning('Unrecognised The Garuda Express shipment status.', [
                'provider'       => $this->getName(),
                'consignment_id' => $consignmentId,
                'status'         => $raw,
            ]);

            return ShipmentStatus::Unknown;
        }

        return $mapped;
    }

    private function simulating(): bool
    {
        return $this->environment() === self::ENVIRONMENT_SANDBOX;
    }

    private function simulatedShipment(OrderData $data): ShipmentResult
    {
        $reference = strtoupper(substr(md5($data->hostOrderReference), 0, 9));

        return new ShipmentResult(
            consignmentId: self::SIMULATED_ORDER_PREFIX.$reference,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: self::SIMULATED_TRACKING_PREFIX.$reference,
            codAmount: $data->codAmount,
            raw: ['simulated' => true, 'currency' => self::CURRENCY],
        );
    }

    private function simulatedTrackingEvents(): array
    {
        return [
            new TrackingEvent(
                status: ShipmentStatus::InTransit,
                occurredAt: (string) now(),
                location: 'Kathmandu',
                description: 'In transit',
                raw: ['simulated' => true],
            ),
            new TrackingEvent(
                status: ShipmentStatus::Pending,
                occurredAt: (string) now()->subDay(),
                location: 'Kathmandu',
                description: 'Order created',
                raw: ['simulated' => true],
            ),
        ];
    }
}
