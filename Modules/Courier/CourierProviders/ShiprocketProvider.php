<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\ItemData;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Support\CountryList;

class ShiprocketProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    CancelsOrders,
    EstimatesDeliveryCharge,
    HandlesWebhooks
{
    private const YES_NO_OPTIONS = [
        ['id' => '0', 'label' => 'No'],
        ['id' => '1', 'label' => 'Yes'],
    ];

    public function getName(): string
    {
        return 'shiprocket';
    }

    protected function displayName(): string
    {
        return 'Shiprocket';
    }

    public function supportedCountries(): array
    {
        return ['IN'];
    }

    public function webhookSlug(): string
    {
        return 'logistics-in';
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://apiv2.shiprocket.in',
            self::ENVIRONMENT_LIVE    => 'https://apiv2.shiprocket.in',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'email', 'label' => 'Shiprocket API User Email', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: api-user@example.com', 'help' => 'Create one under Settings → API → Add New API User. This is not your main Shiprocket login.'],
            ['key' => 'password', 'label' => 'Shiprocket API User Password', 'type' => 'password', 'required' => true, 'help' => 'Emailed to the account owner when the API user was created. Shiprocket has no sandbox — test with real orders you cancel before pickup.'],
            ['key' => 'pickup_location', 'label' => 'Registered Pickup Location Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Primary Warehouse', 'help' => 'The nickname exactly as saved under Settings → Pickup Addresses — spelling, spacing and capitals must match.'],
            ['key' => 'origin_pincode', 'label' => 'Pickup PIN Code', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 110001', 'help' => '6-digit PIN code of the pickup warehouse. Delivery charges are estimated from here.'],
            ['key' => 'auto_request_pickup', 'label' => 'Request Pickup Automatically', 'type' => 'select', 'required' => false, 'options' => self::YES_NO_OPTIONS, 'help' => 'No means you schedule each pickup yourself from the Shiprocket panel.'],
            ['key' => 'default_weight_kg', 'label' => 'Fallback Parcel Weight (kg)', 'type' => 'number', 'required' => false, 'placeholder' => 'Ex: 0.5', 'help' => 'Used only when an order carries no weight of its own.'],
            ['key' => 'default_length_cm', 'label' => 'Usual Parcel Length (cm)', 'type' => 'number', 'required' => false, 'placeholder' => 'Ex: 30', 'help' => 'Shiprocket needs parcel measurements. These are used when the order has none.'],
            ['key' => 'default_breadth_cm', 'label' => 'Usual Parcel Width (cm)', 'type' => 'number', 'required' => false, 'placeholder' => 'Ex: 20'],
            ['key' => 'default_height_cm', 'label' => 'Usual Parcel Height (cm)', 'type' => 'number', 'required' => false, 'placeholder' => 'Ex: 15'],
            ['key' => 'webhook_token', 'label' => 'Webhook Verification Token', 'type' => 'password', 'required' => false, 'placeholder' => 'Ex: sh1pr-h00k-7d2e', 'help' => 'A secret you choose. Register it with the webhook URL ending in /courier/webhook/logistics-in so status updates can be trusted.'],
        ];
    }

    public function locationLevels(): array
    {
        return [];
    }

    public function addressFields(): array
    {
        return [
            ['key' => 'country_code', 'label' => 'Country', 'type' => 'select', 'required' => true, 'options' => CountryList::options()],
            ['key' => 'postal_code', 'label' => 'Pincode', 'type' => 'text', 'required' => true],
            ['key' => 'city_name', 'label' => 'City', 'type' => 'text', 'required' => true],
            ['key' => 'state_province', 'label' => 'State', 'type' => 'text', 'required' => true],
        ];
    }

    public function addressMode(): string
    {
        return 'postal';
    }

    protected function defaultHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token()];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $order = $this->guard($this->withAuthRetry(
            fn ($http) => $http->post('/v1/external/orders/create/adhoc', $this->adhocPayload($data))
        ));

        $shiprocketOrderId = $order['order_id'] ?? null;
        $shipmentId = $order['shipment_id'] ?? null;

        if (empty($shipmentId)) {
            throw new CourierException($this->errorMessage($order, 'Shiprocket order creation failed.'));
        }

        $courierId = $this->pickCourier($data);
        $assigned = $this->assignAwb((int) $shipmentId, $courierId);

        if ($this->autoRequestPickup()) {
            $this->requestPickup((int) $shipmentId);
        }

        $awb = (string) ($assigned['awb_code'] ?? '');

        return new ShipmentResult(
            consignmentId: $awb,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $awb,
            deliveryFee: isset($assigned['freight_charges']) ? (float) $assigned['freight_charges'] : null,
            codAmount: $data->codAmount,
            raw: [
                'sr_order_id'        => $shiprocketOrderId,
                'sr_shipment_id'     => $shipmentId,
                'courier_company_id' => $assigned['courier_company_id'] ?? $courierId,
                'courier_name'       => $assigned['courier_name'] ?? null,
            ],
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

    private function orderInfo(string $awb): ShipmentResult
    {
        $track = (array) ($this->trackingData($awb)['shipment_track'][0] ?? []);

        return new ShipmentResult(
            consignmentId: $awb,
            status: $this->mapStatus((string) ($track['current_status'] ?? '')),
            hostOrderReference: $track['order_id'] ?? null,
            trackingCode: $awb,
            raw: $track,
        );
    }

    public function trackOrder(string $consignmentId): array
    {
        $activities = (array) ($this->trackingData($consignmentId)['shipment_track_activities'] ?? []);
        $events = [];

        foreach ($activities as $activity) {
            if (($activity['sr-status'] ?? null) === 'NA') {
                continue;
            }

            $events[] = new TrackingEvent(
                status: $this->mapStatus((string) ($activity['sr-status-label'] ?? $activity['activity'] ?? '')),
                occurredAt: (string) ($activity['date'] ?? ''),
                location: $activity['location'] ?? null,
                description: $activity['activity'] ?? ($activity['sr-status-label'] ?? null),
                raw: (array) $activity,
            );
        }

        return $events;
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        $this->guard($this->withAuthRetry(
            fn ($http) => $http->post('/v1/external/orders/cancel/shipment/awbs', ['awbs' => [$consignmentId]])
        ));

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Cancelled,
        );
    }

    public function getDeliveryCharges(QuoteData $data): QuoteResult
    {
        $body = $this->serviceability(
            (string) ($this->credentials['origin_pincode'] ?? ''),
            (string) $data->toPostalCode,
            $data->weight,
            $data->codAmount > 0 ? 1 : 0,
        );

        $courier = $this->recommendedCourier((array) ($body['data'] ?? []));

        return new QuoteResult(
            totalCost: (float) ($courier['rate'] ?? 0),
            codFee: isset($courier['cod_charges']) ? (float) $courier['cod_charges'] : null,
            currency: 'INR',
            raw: $body,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $token = $this->credentials['webhook_token'] ?? null;

        if ($token === null || $token === '') {
            return true;
        }

        return hash_equals($token, (string) $request->header('x-api-key'));
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $label = (string) ($request->input('current_status') ?? '');

        $scans = array_values(array_filter(
            (array) $request->input('scans', []),
            static fn ($scan): bool => ($scan['sr-status'] ?? null) !== 'NA',
        ));

        $latest = end($scans);

        if (is_array($latest) && isset($latest['sr-status-label'])) {
            $label = (string) $latest['sr-status-label'];
        }

        return new WebhookEvent(
            consignmentId: (string) $request->input('awb', ''),
            status: $this->mapStatus($label),
            hostOrderReference: $this->hostReferenceFromWebhook($request),
            occurredAt: $request->input('current_timestamp'),
            raw: $request->all(),
        );
    }

    private function adhocPayload(OrderData $data): array
    {
        $recipient = $data->recipient;
        [$firstName, $lastName] = $this->splitName($recipient->name);

        return array_filter([
            'order_id'              => $data->hostOrderReference,
            'order_date'            => now()->format('Y-m-d H:i'),
            'pickup_location'       => $this->credentials['pickup_location'] ?? '',
            'comment'               => $data->note,
            'billing_customer_name' => $firstName,
            'billing_last_name'     => $lastName,
            'billing_address'       => $recipient->address,
            'billing_city'          => $recipient->cityName,
            'billing_pincode'       => $recipient->postalCode,
            'billing_state'         => $recipient->stateProvince,
            'billing_country'       => CountryList::nameFor($recipient->countryCode) ?? 'India',
            'billing_email'         => $recipient->meta['email'] ?? ($data->meta['email'] ?? null),
            'billing_phone'         => $recipient->phone,
            'shipping_is_billing'   => true,
            'order_items'           => $this->orderItems($data),
            'payment_method'        => $data->codAmount > 0 ? 'COD' : 'Prepaid',
            'sub_total'             => $this->subTotal($data),
            'length'                => (float) ($data->meta['length'] ?? $this->credentials['default_length_cm'] ?? 10),
            'breadth'               => (float) ($data->meta['breadth'] ?? $this->credentials['default_breadth_cm'] ?? 10),
            'height'                => (float) ($data->meta['height'] ?? $this->credentials['default_height_cm'] ?? 10),
            'weight'                => $this->weight($data),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function orderItems(OrderData $data): array
    {
        if ($data->items === []) {
            return [array_filter([
                'name'          => $data->itemDescription ?? 'Order '.$data->hostOrderReference,
                'sku'           => $data->hostOrderReference,
                'units'         => $data->quantity ?? 1,
                'selling_price' => (string) $this->subTotal($data),
            ], static fn ($value): bool => $value !== null && $value !== '')];
        }

        return array_map(static fn (ItemData $item): array => array_filter([
            'name'          => $item->name,
            'sku'           => $item->sku ?? $item->name,
            'units'         => $item->quantity,
            'selling_price' => (string) $item->price,
        ], static fn ($value): bool => $value !== null && $value !== ''), $data->items);
    }

    private function subTotal(OrderData $data): float
    {
        if ($data->items !== []) {
            return array_reduce(
                $data->items,
                static fn (float $sum, ItemData $item): float => $sum + $item->price * $item->quantity,
                0.0,
            );
        }

        return (float) ($data->meta['value'] ?? $data->codAmount);
    }

    private function weight(OrderData $data): float
    {
        $weight = $data->weight > 0 ? $data->weight : (float) ($this->credentials['default_weight_kg'] ?? 0.5);

        return $weight > 0 ? $weight : 0.5;
    }

    private function pickCourier(OrderData $data): int
    {
        $body = $this->serviceability(
            (string) ($this->credentials['origin_pincode'] ?? ''),
            (string) $data->recipient->postalCode,
            $this->weight($data),
            $data->codAmount > 0 ? 1 : 0,
        );

        return (int) ($this->recommendedCourier((array) ($body['data'] ?? []))['courier_company_id'] ?? 0);
    }

    private function recommendedCourier(array $data): array
    {
        $companies = (array) ($data['available_courier_companies'] ?? []);

        if ($companies === []) {
            throw new CourierException('No Shiprocket courier is serviceable for this destination.');
        }

        $recommendedId = $data['recommended_courier_company_id'] ?? null;

        foreach ($companies as $company) {
            if ($recommendedId !== null && (int) ($company['courier_company_id'] ?? 0) === (int) $recommendedId) {
                return $company;
            }
        }

        usort($companies, static fn ($a, $b): int => ($a['rate'] ?? PHP_INT_MAX) <=> ($b['rate'] ?? PHP_INT_MAX));

        return $companies[0];
    }

    private function serviceability(string $originPincode, string $destinationPincode, float $weight, int $cod): array
    {
        return $this->guard($this->withAuthRetry(fn ($http) => $http->get('/v1/external/courier/serviceability/', [
            'pickup_postcode'   => $originPincode,
            'delivery_postcode' => $destinationPincode,
            'weight'            => $weight,
            'cod'               => $cod,
        ])));
    }

    private function assignAwb(int $shipmentId, int $courierId): array
    {
        $body = $this->guard($this->withAuthRetry(fn ($http) => $http->post('/v1/external/courier/assign/awb', [
            'shipment_id' => $shipmentId,
            'courier_id'  => $courierId,
        ])));

        $data = (array) ($body['response']['data'] ?? []);

        if (($body['awb_assign_status'] ?? 0) !== 1 || empty($data['awb_code'])) {
            throw new CourierException($this->errorMessage($body, 'Shiprocket AWB assignment failed.'));
        }

        return $data;
    }

    private function requestPickup(int $shipmentId): void
    {
        $this->guard($this->withAuthRetry(
            fn ($http) => $http->post('/v1/external/courier/generate/pickup', ['shipment_id' => [$shipmentId]])
        ));
    }

    private function trackingData(string $awb): array
    {
        $body = $this->guard($this->withAuthRetry(
            fn ($http) => $http->get('/v1/external/courier/track/awb/'.$awb)
        ));

        return (array) ($body['tracking_data'] ?? []);
    }

    private function autoRequestPickup(): bool
    {
        return filter_var($this->credentials['auto_request_pickup'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function hostReferenceFromWebhook(Request $request): ?string
    {
        $composite = (string) $request->input('order_id', '');

        if ($composite === '') {
            return null;
        }

        return explode('_', $composite, 2)[1] ?? $composite;
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
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

    private function guard(Response $response): array
    {
        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return (array) $response->json();
    }

    private function errorMessage(array $body, string $fallback): string
    {
        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return $body['message'];
        }

        $reasons = [];
        $errors = (array) ($body['errors'] ?? []);
        array_walk_recursive($errors, static function ($value) use (&$reasons): void {
            if (is_string($value) && $value !== '') {
                $reasons[] = $value;
            }
        });

        return $reasons !== [] ? implode('; ', array_unique($reasons)) : $fallback;
    }

    protected function token(): string
    {
        $key = $this->tokenCacheKey();

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $response = Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->acceptJson()
            ->post('/v1/external/auth/login', [
                'email'    => $this->credentials['email'] ?? '',
                'password' => $this->credentials['password'] ?? '',
            ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $token = (string) $response->json('token');

        if ($token === '') {
            throw new CourierException($this->errorMessage((array) $response->json(), 'Shiprocket authentication failed.'));
        }

        Cache::put($key, $token, now()->addDays(9));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'courier.shiprocket.token.'.md5(($this->credentials['email'] ?? '').'|'.$this->baseUrl());
    }

    private function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        $status = strtolower(trim($raw));

        return match (true) {
            str_contains($status, 'out for delivery')                             => ShipmentStatus::OutForDelivery,
            str_contains($status, 'rto') && str_contains($status, 'delivered')     => ShipmentStatus::Returned,
            str_contains($status, 'delivered')                                     => ShipmentStatus::Delivered,
            str_contains($status, 'rto'), str_contains($status, 'return')          => ShipmentStatus::ReturnInitiated,
            str_contains($status, 'picked up')                                     => ShipmentStatus::PickedUp,
            str_contains($status, 'pickup')                                        => ShipmentStatus::PickupRequested,
            str_contains($status, 'in transit'), str_contains($status, 'shipped')  => ShipmentStatus::InTransit,
            str_contains($status, 'manifest'), str_contains($status, 'new')        => ShipmentStatus::Pending,
            str_contains($status, 'cancel')                                        => ShipmentStatus::Cancelled,
            str_contains($status, 'lost')                                          => ShipmentStatus::Failed,
            str_contains($status, 'hold')                                          => ShipmentStatus::OnHold,
            default                                                                => ShipmentStatus::Unknown,
        };
    }
}
