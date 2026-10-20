<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\Response;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;

class LeopardsProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    CancelsOrders,
    ResolvesLocations
{
    private const PRODUCTION_BASE_URL = 'https://merchantapi.leopardscourier.com/api';

    private const STAGING_BASE_URL = 'https://merchantapi.leopardscourier.com/api';

    private const ENDPOINT_CITIES = '/getAllCities/format/json/';
    private const ENDPOINT_BOOK = '/bookPacket/format/json/';
    private const ENDPOINT_TRACK = '/trackBookedPacket/format/json/';
    private const ENDPOINT_CANCEL = '/cancelBookedPackets/format/json/';

    private const SELF = 'self';
    private const MIN_WEIGHT_GRAMS = 100;
    private const REDACTED = '***';

    public function getName(): string
    {
        return 'leopards';
    }

    protected function displayName(): string
    {
        return 'Leopards Courier';
    }

    public function supportedCountries(): array
    {
        return ['PK'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => $this->credentials['staging_base_url'] ?? self::STAGING_BASE_URL,
            self::ENVIRONMENT_LIVE    => self::PRODUCTION_BASE_URL,
        ];
    }

    public function addressMode(): string
    {
        return 'catalog';
    }

    public function locationLevels(): array
    {
        return ['city'];
    }

    public function addressFields(): array
    {
        return [];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: lcs_9f4b2e7a', 'help' => 'API Key issued by Leopards Courier Service (LCS) to your account'],
            ['key' => 'api_password', 'label' => 'API Password', 'type' => 'password', 'required' => true, 'help' => 'API Password issued by Leopards Courier Service (LCS) alongside the API Key.'],
            ['key' => 'origin_city', 'label' => 'Origin City', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Lahore', 'help' => "Leave blank to ship from your registered LCS account city (sent as 'self')"],
            ['key' => 'shipment_name', 'label' => 'Sender Name', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Riverside Traders', 'help' => "Leave blank to use your LCS account name (sent as 'self')"],
            ['key' => 'shipment_email', 'label' => 'Sender Email', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: pickup@example.com', 'help' => "Leave blank to use your LCS account email (sent as 'self')"],
            ['key' => 'shipment_phone', 'label' => 'Sender Phone', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 03001234567', 'help' => "Leave blank to use your LCS account phone (sent as 'self')"],
            ['key' => 'shipment_address', 'label' => 'Sender Address', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 24 Gulberg III, Lahore', 'help' => "Leave blank to use your LCS account address (sent as 'self')"],
            ['key' => 'staging_base_url', 'label' => 'Staging Base URL', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: https://staging.leopardscourier.com', 'help' => 'LCS test host for the sandbox environment. Unpublished — set once LCS provides it.'],
            ['key' => 'enable_simulation', 'label' => 'Enable Simulation (Sandbox)', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 1', 'help' => 'Enter 1 to return synthetic cities/CN/status for UI testing before live credentials arrive. Sandbox only; leave blank once real credentials work. Weight is billed in grams and the API is COD-first.'],
        ];
    }

    public function getCities(): array
    {
        if ($this->simulating()) {
            return $this->simulatedCities();
        }

        $rows = (array) $this->value($this->request(self::ENDPOINT_CITIES), ['city_list', 'data', 'cities'], []);

        return array_values(array_filter(array_map(function ($row): ?Location {
            $row = (array) $row;
            $id = $this->value($row, ['id', 'city_id'], null);

            if ($id === null) {
                return null;
            }

            return new Location(
                id: (string) $id,
                name: (string) $this->value($row, ['name', 'city_name'], ''),
                level: 'city',
                raw: $row,
            );
        }, $rows)));
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
        if ($this->simulating()) {
            return $this->simulatedShipment($data);
        }

        $body = $this->request(self::ENDPOINT_BOOK, $this->bookPayload($data));
        $cn = (string) $this->value($body, ['track_number', 'cn_number', 'cn', 'booked_packet_number'], '');

        if ($cn === '') {
            throw new CourierException((string) $this->value($body, ['error_msg', 'message'], 'Leopards booking failed: no CN number returned.'));
        }

        return new ShipmentResult(
            consignmentId: $cn,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $cn,
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
            return [new TrackingEvent(
                status: ShipmentStatus::InTransit,
                occurredAt: (string) now(),
                description: 'Simulated in-transit checkpoint',
            )];
        }

        $checkpoints = (array) $this->value($this->trackingBody($consignmentId), ['Tracking Detail', 'tracking_detail', 'checkpoints', 'history'], []);

        return array_values(array_map(fn ($checkpoint): TrackingEvent => $this->trackingEvent((array) $checkpoint), $checkpoints));
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        if (!$this->simulating()) {
            $this->request(self::ENDPOINT_CANCEL, ['cn_numbers' => $consignmentId]);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Cancelled,
        );
    }

    public static function redactCredentials(array $payload): array
    {
        foreach (['api_key', 'api_password'] as $secret) {
            if (array_key_exists($secret, $payload)) {
                $payload[$secret] = self::REDACTED;
            }
        }

        return $payload;
    }

    private function orderInfo(string $consignmentId): ShipmentResult
    {
        if ($this->simulating()) {
            return new ShipmentResult(consignmentId: $consignmentId, status: ShipmentStatus::InTransit);
        }

        $packet = (array) $this->value($this->trackingBody($consignmentId), ['packet', 'data', 'packet_detail'], []);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus((string) $this->value($packet, ['booked_packet_status', 'status'], 'unknown')),
            trackingCode: $consignmentId,
            raw: $packet,
        );
    }

    private function trackingBody(string $consignmentId): array
    {
        return $this->request(self::ENDPOINT_TRACK, ['track_numbers' => $consignmentId]);
    }

    private function trackingEvent(array $checkpoint): TrackingEvent
    {
        return new TrackingEvent(
            status: $this->mapStatus((string) $this->value($checkpoint, ['Status', 'status'], 'unknown')),
            occurredAt: (string) $this->value($checkpoint, ['Activity_datetime', 'datetime', 'date'], ''),
            location: $this->value($checkpoint, ['Reception', 'location'], null),
            description: $this->value($checkpoint, ['Status', 'activity', 'reason'], null),
            raw: $checkpoint,
        );
    }

    private function bookPayload(OrderData $data): array
    {
        $recipient = $data->recipient;

        return array_filter([
            'booked_packet_weight'         => $this->grams($data),
            'booked_packet_vol_weight_w'   => $data->meta['width'] ?? '',
            'booked_packet_vol_weight_h'   => $data->meta['height'] ?? '',
            'booked_packet_vol_weight_l'   => $data->meta['length'] ?? '',
            'booked_packet_no_piece'       => $data->quantity ?? max(1, count($data->items)),
            'booked_packet_collect_amount' => (int) round($data->codAmount),
            'booked_packet_order_id'       => $data->hostOrderReference,

            'origin_city'      => $this->shipperField('origin_city'),
            'destination_city' => $recipient->cityId,

            'shipment_name_eng' => $this->shipperField('shipment_name'),
            'shipment_email'    => $this->shipperField('shipment_email'),
            'shipment_phone'    => $this->shipperField('shipment_phone'),
            'shipment_address'  => $this->shipperField('shipment_address'),

            'consignment_name_eng' => $recipient->name,
            'consignment_email'    => $recipient->meta['email'] ?? ($data->meta['email'] ?? ''),
            'consignment_phone'    => $recipient->phone,
            'consignment_address'  => $recipient->address,
            'special_instructions' => $data->note ?? 'n/a',
        ], static fn ($value): bool => $value !== null);
    }

    private function shipperField(string $credentialKey): string
    {
        $value = $this->credentials[$credentialKey] ?? null;

        return filled($value) ? (string) $value : self::SELF;
    }

    private function grams(OrderData $data): int
    {
        return max(self::MIN_WEIGHT_GRAMS, (int) round($data->weight * 1000));
    }

    private function withCredentials(array $payload): array
    {
        $credentials = [
            'api_key'      => $this->credentials['api_key'] ?? '',
            'api_password' => $this->credentials['api_password'] ?? '',
        ];

        if ($this->environment() === self::ENVIRONMENT_SANDBOX) {
            $credentials['enable_test_mode'] = true;
        }

        return $credentials + $payload;
    }

    private function request(string $endpoint, array $payload = []): array
    {
        $response = $this->http()->post($endpoint, $this->withCredentials($payload));

        return $this->guard($response);
    }

    private function guard(Response $response): array
    {
        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();
        $status = $this->value($body, ['status'], null);

        if ($status !== null && !in_array((int) $status, [1, 200], true)) {
            throw new CourierException((string) $this->value($body, ['error_msg', 'message'], 'Leopards request failed.'));
        }

        return $body;
    }

    private function value(array $body, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '') {
                return $body[$key];
            }
        }

        return $default;
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
                id: (string) $city['id'],
                name: $city['name'],
                level: 'city',
                raw: $city + ['is_origin' => 1, 'is_destination' => 1],
            ),
            [
                ['id' => 789, 'name' => 'Lahore'],
                ['id' => 202, 'name' => 'Karachi'],
                ['id' => 51, 'name' => 'Islamabad'],
                ['id' => 91, 'name' => 'Rawalpindi'],
                ['id' => 145, 'name' => 'Faisalabad'],
            ],
        );
    }

    private function simulatedShipment(OrderData $data): ShipmentResult
    {
        $cn = 'SIM'.substr(md5($data->hostOrderReference), 0, 9);

        return new ShipmentResult(
            consignmentId: $cn,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $cn,
            codAmount: $data->codAmount,
            raw: ['simulated' => true],
        );
    }

    private function mapStatus(string $raw): ShipmentStatus
    {
        $status = strtolower(trim($raw));

        return match (true) {
            str_contains($status, 'out for delivery')                        => ShipmentStatus::OutForDelivery,
            str_contains($status, 'return')                                  => ShipmentStatus::ReturnInitiated,
            str_contains($status, 'delivered')                              => ShipmentStatus::Delivered,
            str_contains($status, 'pickup'), str_contains($status, 'picked') => ShipmentStatus::PickedUp,
            str_contains($status, 'transit'), str_contains($status, 'arrived'), str_contains($status, 'dispatch') => ShipmentStatus::InTransit,
            str_contains($status, 'cancel')                                 => ShipmentStatus::Cancelled,
            str_contains($status, 'hold')                                   => ShipmentStatus::OnHold,
            str_contains($status, 'assign'), str_contains($status, 'book'), str_contains($status, 'pending') => ShipmentStatus::Pending,
            default                                                          => ShipmentStatus::Unknown,
        };
    }
}
