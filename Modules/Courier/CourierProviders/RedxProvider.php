<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Request;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ProvidesStores;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\CourierProviders\Contracts\UpdatesOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;

class RedxProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    CancelsOrders,
    UpdatesOrders,
    EstimatesDeliveryCharge,
    ResolvesLocations,
    ProvidesStores,
    HandlesWebhooks
{
    private const API_PREFIX = '/v1.0.0-beta';
    private const NATIONWIDE = 'all';

    public function getName(): string
    {
        return 'redx';
    }

    protected function displayName(): string
    {
        return 'RedX';
    }

    public function supportedCountries(): array
    {
        return ['BD'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://sandbox.redx.com.bd',
            self::ENVIRONMENT_LIVE    => 'https://openapi.redx.com.bd',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_access_token', 'label' => 'API Access Token', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...', 'help' => 'Issued by RedX for your merchant account.'],
            ['key' => 'default_pickup_store_id', 'label' => 'Default Pickup Store ID', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 90341', 'help' => 'The ID of the RedX pickup store parcels are collected from when an order does not name one. Find it in your RedX merchant panel under Pickup Stores.'],
            ['key' => 'default_pickup_area_id', 'label' => 'Default Pickup Area ID', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 1272', 'help' => 'The RedX area ID of that pickup store, shown beside it in your RedX merchant panel. Without it RedX cannot estimate a delivery charge.'],
            ['key' => 'webhook_token', 'label' => 'Webhook Verification Token', 'type' => 'password', 'required' => false, 'placeholder' => 'Ex: r3dx-h00k-8c1a', 'help' => 'A secret you choose. RedX must send it back so status updates can be trusted.'],
        ];
    }

    public function locationLevels(): array
    {
        return ['area'];
    }

    protected function defaultHeaders(): array
    {
        return ['API-ACCESS-TOKEN' => 'Bearer '.($this->credentials['api_access_token'] ?? '')];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $payload = array_filter([
            'customer_name'          => $data->recipient->name,
            'customer_phone'         => $data->recipient->phone,
            'customer_address'       => $data->recipient->address,
            'delivery_area'          => $this->areaName($data->recipient->areaId),
            'delivery_area_id'       => $data->recipient->areaId !== null ? (int) $data->recipient->areaId : null,
            'merchant_invoice_id'    => $data->hostOrderReference,
            'cash_collection_amount' => (string) $data->codAmount,
            'parcel_weight'          => $this->toGrams($data->weight),
            'value'                  => (string) ($data->meta['value'] ?? $data->codAmount),
            'instruction'            => $data->note,
            'pickup_store_id'        => $data->providerStoreId ?? ($this->credentials['default_pickup_store_id'] ?? null),
        ], static fn ($v) => $v !== null && $v !== '');

        $response = $this->http()->post(self::API_PREFIX.'/parcel', $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: (string) $response->json('tracking_id', ''),
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            codAmount: $data->codAmount,
            raw: (array) $response->json(),
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

    private function orderInfo(string $consignmentId): ShipmentResult
    {
        $response = $this->http()->get(self::API_PREFIX."/parcel/info/{$consignmentId}");

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $parcel = (array) $response->json('parcel', []);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus((string) ($parcel['status'] ?? 'unknown')),
            hostOrderReference: $parcel['merchant_invoice_id'] ?? null,
            deliveryFee: isset($parcel['charge']) ? (float) $parcel['charge'] : null,
            codAmount: isset($parcel['cash_collection_amount']) ? (float) $parcel['cash_collection_amount'] : null,
            raw: $parcel,
        );
    }

    public function trackOrder(string $consignmentId): array
    {
        $response = $this->http()->get(self::API_PREFIX."/parcel/track/{$consignmentId}");

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $rows = (array) $response->json('tracking', []);

        return array_map(fn (array $row) => new TrackingEvent(
            status: $this->mapTrackingMessage($row['message_en'] ?? null),
            occurredAt: (string) ($row['time'] ?? ''),
            description: $row['message_en'] ?? null,
            raw: $row,
        ), $rows);
    }

    private function mapTrackingMessage(?string $message): ShipmentStatus
    {
        $key = rtrim(strtolower(trim((string) $message)), '.');

        return match ($key) {
            'package is created successfully' => ShipmentStatus::Pending,
            default                           => ShipmentStatus::Unknown,
        };
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        $response = $this->http()->patch(self::API_PREFIX.'/parcels', [
            'entity_type'    => 'parcel-tracking-id',
            'entity_id'      => $consignmentId,
            'update_details' => [
                'property_name' => 'status',
                'new_value'     => 'cancelled',
                'reason'        => 'Cancelled by merchant',
            ],
        ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Cancelled,
            raw: (array) $response->json(),
        );
    }

    public function updateOrder(string $consignmentId, OrderData $data): ShipmentResult
    {
        $property = $data->meta['property_name'] ?? 'delivery_address';
        $newValue = $data->meta['new_value'] ?? $data->recipient->address;

        $response = $this->http()->patch(self::API_PREFIX.'/parcels', [
            'entity_type'    => 'parcel-tracking-id',
            'entity_id'      => $consignmentId,
            'update_details' => array_filter([
                'property_name' => $property,
                'new_value'     => $newValue,
                'reason'        => $data->note,
            ], static fn ($v) => $v !== null),
        ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $property === 'status' ? $this->mapStatus((string) $newValue) : ShipmentStatus::Unknown,
            hostOrderReference: $data->hostOrderReference,
            raw: (array) $response->json(),
        );
    }

    public function getStores(): array
    {
        $response = $this->http()->get(self::API_PREFIX.'/pickup/stores');

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $rows = (array) $response->json('pickup_stores', []);

        return $this->storeLocations($rows, 'id', 'name', 'address');
    }

    public function getCities(): array
    {
        return [new Location(id: self::NATIONWIDE, name: 'Bangladesh', level: 'city')];
    }

    public function getZones(string $cityId): array
    {
        return [new Location(id: self::NATIONWIDE, name: 'Bangladesh', level: 'zone', parentId: $cityId)];
    }

    public function getAreas(string $zoneId): array
    {
        $response = $this->http()->get(self::API_PREFIX.'/areas');

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $rows = (array) $response->json('areas', []);

        return array_map(static fn (array $row) => new Location(
            id: (string) $row['id'],
            name: (string) $row['name'],
            level: 'area',
            raw: $row,
        ), $rows);
    }

    public function getDeliveryCharges(QuoteData $data): QuoteResult
    {
        $pickupAreaId = $data->meta['pickup_area_id'] ?? ($this->credentials['default_pickup_area_id'] ?? null);

        if (blank($pickupAreaId)) {
            throw new CourierException('Set "Default Pickup Area ID" for RedX in Courier Configuration — a delivery charge cannot be estimated without the pickup area.');
        }

        $response = $this->http()->get(self::API_PREFIX.'/charge/charge_calculator', array_filter([
            'delivery_area_id'       => $data->toAreaId,
            'pickup_area_id'         => $pickupAreaId,
            'cash_collection_amount' => $data->codAmount,
            'weight'                 => $this->toGrams($data->weight),
        ], static fn ($v) => $v !== null));

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();

        return new QuoteResult(
            totalCost: (float) ($body['deliveryCharge'] ?? 0) + (float) ($body['codCharge'] ?? 0),
            baseFee: isset($body['deliveryCharge']) ? (float) $body['deliveryCharge'] : null,
            codFee: isset($body['codCharge']) ? (float) $body['codCharge'] : null,
            currency: 'BDT',
            raw: $body,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $token = $this->credentials['webhook_token'] ?? null;

        return $token !== null
            && hash_equals($token, (string) $request->query('token'));
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        return new WebhookEvent(
            consignmentId: (string) $request->input('tracking_number', ''),
            status: $this->mapStatus((string) $request->input('status', 'unknown')),
            hostOrderReference: $request->input('invoice_number'),
            occurredAt: $request->input('timestamp'),
            raw: $request->all(),
        );
    }

    private function toGrams(float $weightKg): int
    {
        return (int) round($weightKg * 1000);
    }

    private function areaName(?string $areaId): ?string
    {
        if ($areaId === null) {
            return null;
        }

        foreach ($this->getAreas(self::NATIONWIDE) as $area) {
            if ($area->id === (string) $areaId) {
                return $area->name;
            }
        }

        return null;
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        $key = str_replace([' ', '_'], '-', strtolower(trim($raw)));

        return match ($key) {
            'pickup-pending'         => ShipmentStatus::PickupRequested,
            'ready-for-delivery'     => ShipmentStatus::AtHub,
            'delivery-in-progress'   => ShipmentStatus::OutForDelivery,
            'delivered'              => ShipmentStatus::Delivered,
            'agent-hold'             => ShipmentStatus::OnHold,
            'agent-returning'        => ShipmentStatus::ReturnInitiated,
            'returned'               => ShipmentStatus::Returned,
            'agent-area-change'      => ShipmentStatus::InTransit,
            'cancelled'              => ShipmentStatus::Cancelled,
            default                  => ShipmentStatus::Unknown,
        };
    }
}
