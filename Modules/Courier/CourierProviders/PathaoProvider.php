<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Courier\CourierProviders\Contracts\CalculatesPrice;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ProvidesStores;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;

class PathaoProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    ResolvesLocations,
    ProvidesStores,
    CalculatesPrice,
    HandlesWebhooks
{
    protected const DELIVERY_TYPE_NORMAL = 48;
    protected const DELIVERY_TYPE_ON_DEMAND = 12;
    protected const ITEM_TYPE_PARCEL = 2;
    protected const MIN_WEIGHT_KG = 0.5;

    protected const CURRENCY = 'BDT';

    public function deliveryTypes(): array
    {
        return [
            self::DELIVERY_TYPE_NORMAL    => 'Standard Delivery',
            self::DELIVERY_TYPE_ON_DEMAND => 'On Demand Delivery',
        ];
    }

    public function currency(): string
    {
        return self::CURRENCY;
    }

    public function minimumWeight(): float
    {
        return self::MIN_WEIGHT_KG;
    }

    public function getName(): string
    {
        return 'pathao';
    }

    protected function displayName(): string
    {
        return 'Pathao';
    }

    public function supportedCountries(): array
    {
        return ['BD'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://courier-api-sandbox.pathao.com',
            self::ENVIRONMENT_LIVE    => 'https://api-hermes.pathao.com',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 7f3c9a1e4b62', 'help' => 'Issued by Pathao for your merchant account.'],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: wJ8kQ2mR5tV9xZ1bN4cL7pS0dF3gH6jA', 'help' => 'Issued alongside the Client ID.'],
            ['key' => 'username', 'label' => 'Pathao Account Email', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: merchant@example.com', 'help' => 'The email you sign in to your Pathao merchant panel with.'],
            ['key' => 'password', 'label' => 'Pathao Account Password', 'type' => 'password', 'required' => true, 'help' => 'The password you sign in to your Pathao merchant panel with.'],
            ['key' => 'store_id', 'label' => 'Default Pickup Store ID', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 148126', 'help' => 'The ID of the Pathao store parcels are collected from when an order does not name one. Find it in your Pathao merchant panel under Stores.'],
            ['key' => 'webhook_secret', 'label' => 'Webhook Verification Secret', 'type' => 'password', 'required' => false, 'placeholder' => 'Ex: p4th40-h00k-2f9c', 'help' => 'A secret you choose. Pathao must send it back so status updates can be trusted.'],
        ];
    }

    protected function defaultHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token()];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $payload = array_filter([
            'store_id'            => $data->providerStoreId ?? ($this->credentials['store_id'] ?? null),
            'merchant_order_id'   => $data->hostOrderReference,
            'recipient_name'      => $data->recipient->name,
            'recipient_phone'     => $data->recipient->phone,
            'recipient_address'   => $data->recipient->address,
            'recipient_city'      => $data->recipient->cityId,
            'recipient_zone'      => $data->recipient->zoneId,
            'recipient_area'      => $data->recipient->areaId,
            'delivery_type'       => (int) ($data->meta['delivery_type'] ?? self::DELIVERY_TYPE_NORMAL),
            'item_type'           => $data->meta['item_type'] ?? self::ITEM_TYPE_PARCEL,
            'special_instruction' => $data->note,
            'item_quantity'       => $data->quantity ?? max(1, count($data->items)),
            'item_weight'         => max($this->minimumWeight(), $data->weight),
            'amount_to_collect'   => $data->codAmount,
            'item_description'    => $data->itemDescription,
        ], static fn ($v) => $v !== null);

        $response = $this->http()->post('/aladdin/api/v1/orders', $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $order = (array) $response->json('data', []);

        return new ShipmentResult(
            consignmentId: (string) ($order['consignment_id'] ?? ''),
            status: $this->mapStatus($order['order_status'] ?? 'pending'),
            hostOrderReference: $order['merchant_order_id'] ?? $data->hostOrderReference,
            deliveryFee: isset($order['delivery_fee']) ? (float) $order['delivery_fee'] : null,
            codAmount: $data->codAmount,
            raw: $order,
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
        $response = $this->http()->get("/aladdin/api/v1/orders/{$consignmentId}/info");

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $order = (array) $response->json('data', []);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus($order['order_status'] ?? 'unknown'),
            hostOrderReference: $order['merchant_order_id'] ?? null,
            raw: $order,
        );
    }

    public function getStores(): array
    {
        $response = $this->http()->get('/aladdin/api/v1/stores');

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $rows = $response->json('data.data') ?? $response->json('data') ?? [];

        return $this->storeLocations($rows, 'store_id', 'store_name', 'store_address');
    }

    public function getCities(): array
    {
        return $this->locations('/aladdin/api/v1/city-list', 'city_id', 'city_name', 'city');
    }

    public function getZones(string $cityId): array
    {
        return $this->locations("/aladdin/api/v1/cities/{$cityId}/zone-list", 'zone_id', 'zone_name', 'zone', $cityId);
    }

    public function getAreas(string $zoneId): array
    {
        return $this->locations("/aladdin/api/v1/zones/{$zoneId}/area-list", 'area_id', 'area_name', 'area', $zoneId);
    }

    private function locations(string $uri, string $idKey, string $nameKey, string $level, ?string $parentId = null): array
    {
        $response = $this->http()->get($uri);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $rows = $response->json('data.data') ?? $response->json('data') ?? [];

        return array_map(static fn (array $row) => new Location(
            id: (string) $row[$idKey],
            name: (string) $row[$nameKey],
            level: $level,
            parentId: $parentId,
            raw: $row,
        ), $rows);
    }

    public function calculatePrice(QuoteData $data): QuoteResult
    {
        $payload = array_filter([
            'store_id'       => $data->meta['store_id'] ?? ($this->credentials['store_id'] ?? null),
            'item_type'      => $data->meta['item_type'] ?? self::ITEM_TYPE_PARCEL,
            'delivery_type'  => (int) ($data->deliveryType ?? self::DELIVERY_TYPE_NORMAL),
            'item_weight'    => max($this->minimumWeight(), $data->weight),
            'recipient_city' => $data->toCityId,
            'recipient_zone' => $data->toZoneId,
        ], static fn ($v) => $v !== null);

        $response = $this->http()->post('/aladdin/api/v1/merchant/price-plan', $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $plan = (array) $response->json('data', []);

        return new QuoteResult(
            totalCost: (float) ($plan['final_price'] ?? $plan['price'] ?? 0),
            baseFee: isset($plan['price']) ? (float) $plan['price'] : null,
            currency: $this->currency(),
            raw: $plan,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->credentials['webhook_secret'] ?? null;

        return $secret !== null
            && hash_equals($secret, (string) $request->header('X-PATHAO-Signature'));
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $status = $request->input('order_status') ?? $request->input('event') ?? 'unknown';

        return new WebhookEvent(
            consignmentId: (string) $request->input('consignment_id', ''),
            status: $this->mapStatus((string) $status),
            hostOrderReference: $request->input('merchant_order_id'),
            occurredAt: $request->input('updated_at') ?? $request->input('timestamp'),
            raw: $request->all(),
        );
    }

    public function webhookAcknowledgement(): array
    {
        return [
            'status'  => 202,
            'headers' => ['X-Pathao-Merchant-Webhook-Integration-Secret' => $this->credentials['webhook_secret'] ?? ''],
            'body'    => '',
        ];
    }

    protected function token(): string
    {
        $key = 'courier.pathao.token.'.md5(($this->credentials['client_id'] ?? '').'|'.$this->baseUrl());

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $response = Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->acceptJson()
            ->post('/aladdin/api/v1/issue-token', [
                'client_id'     => $this->credentials['client_id'] ?? '',
                'client_secret' => $this->credentials['client_secret'] ?? '',
                'username'      => $this->credentials['username'] ?? '',
                'password'      => $this->credentials['password'] ?? '',
                'grant_type'    => $this->credentials['grant_type'] ?? 'password',
            ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3000) - 60);
        Cache::put($key, $token, $ttl);

        return $token;
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        $key = str_replace(['order.', '_', ' '], ['', '-', '-'], strtolower(trim($raw)));

        return match ($key) {
            'pending', 'created', 'order-created'      => ShipmentStatus::Pending,
            'pickup-requested', 'assigned-for-pickup'  => ShipmentStatus::PickupRequested,
            'picked', 'picked-up'                      => ShipmentStatus::PickedUp,
            'at-the-sorting-hub', 'received-at-last-mile-hub' => ShipmentStatus::AtHub,
            'in-transit'                               => ShipmentStatus::InTransit,
            'assigned-for-delivery'                    => ShipmentStatus::OutForDelivery,
            'delivered'                                => ShipmentStatus::Delivered,
            'partial-delivery', 'partial-delivered'    => ShipmentStatus::PartialDelivered,
            'returned', 'paid-return'                  => ShipmentStatus::Returned,
            'delivery-failed', 'pickup-failed'         => ShipmentStatus::Failed,
            'on-hold'                                  => ShipmentStatus::OnHold,
            'cancelled', 'pickup-cancelled'            => ShipmentStatus::Cancelled,
            default                                    => ShipmentStatus::Unknown,
        };
    }
}
