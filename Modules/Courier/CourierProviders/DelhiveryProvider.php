<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Request;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\CourierProviders\Contracts\UpdatesOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Support\CountryList;

class DelhiveryProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    CancelsOrders,
    UpdatesOrders,
    EstimatesDeliveryCharge,
    HandlesWebhooks
{
    private const SHIPPING_MODE_OPTIONS = [
        ['id' => 'Surface', 'label' => 'Surface'],
        ['id' => 'Express', 'label' => 'Express'],
    ];

    public function getName(): string
    {
        return 'delhivery';
    }

    protected function displayName(): string
    {
        return 'Delhivery';
    }

    public function supportedCountries(): array
    {
        return ['IN'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://staging-express.delhivery.com',
            self::ENVIRONMENT_LIVE    => 'https://track.delhivery.com',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: 5c1f0a83b47e9d26f8a0c3b512d7e4906fa1b8c2', 'help' => 'From your Delhivery ONE panel, under API Setup.'],
            ['key' => 'pickup_location', 'label' => 'Registered Pickup Location Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Riverside Traders Warehouse', 'help' => 'The warehouse name exactly as saved in your Delhivery ONE panel — spelling, spacing and capitals must match.'],
            ['key' => 'origin_pincode', 'label' => 'Pickup PIN Code', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 110001', 'help' => '6-digit PIN code of the pickup warehouse. Delivery charges are estimated from here.'],
            ['key' => 'seller_name', 'label' => 'Business Name', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Riverside Traders Pvt. Ltd.', 'help' => 'Shown as the sender on the shipping label.'],
            ['key' => 'seller_address', 'label' => 'Business Address', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 12 Nehru Place, New Delhi', 'help' => 'Printed on the shipping label under your business name.'],
            ['key' => 'default_shipping_mode', 'label' => 'Delivery Speed', 'type' => 'select', 'required' => false, 'options' => self::SHIPPING_MODE_OPTIONS, 'help' => 'Surface is cheaper and slower, Express is faster and costs more.'],
            ['key' => 'default_weight_grams', 'label' => 'Fallback Parcel Weight (grams)', 'type' => 'number', 'required' => false, 'placeholder' => 'Ex: 500', 'help' => 'Used only when an order carries no weight of its own.'],
            ['key' => 'webhook_token', 'label' => 'Webhook Verification Token', 'type' => 'password', 'required' => false, 'placeholder' => 'Ex: d3lhv-h00k-6b4f', 'help' => 'A secret you choose. Delhivery must send it back so status updates can be trusted.'],
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
        return [
            'Authorization' => 'Token '.($this->credentials['api_token'] ?? ''),
            'Accept'        => 'application/json',
        ];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $recipient = $data->recipient;

        $shipment = array_filter([
            'name'          => $recipient->name,
            'phone'         => $recipient->phone,
            'add'           => $recipient->address,
            'pin'           => $recipient->postalCode,
            'city'          => $recipient->cityName,
            'state'         => $recipient->stateProvince,
            'country'       => CountryList::nameFor($recipient->countryCode) ?? 'India',
            'order'         => $data->hostOrderReference,
            'payment_mode'  => $data->codAmount > 0 ? 'COD' : 'Prepaid',
            'cod_amount'    => $data->codAmount > 0 ? (string) $data->codAmount : '',
            'total_amount'  => (string) ($data->meta['value'] ?? $data->codAmount),
            'weight'        => $this->weightInGrams($data->weight),
            'products_desc' => $data->itemDescription,
            'shipping_mode' => $this->shippingMode(),
            'quantity'      => $data->quantity !== null ? (string) $data->quantity : null,
            'seller_name'   => $this->credentials['seller_name'] ?? null,
            'seller_add'    => $this->credentials['seller_address'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $payload = [
            'shipments'       => [$shipment],
            'pickup_location' => ['name' => $this->credentials['pickup_location'] ?? ''],
        ];

        $response = $this->http()->asForm()->post('/api/cmu/create.json', [
            'format' => 'json',
            'data'   => json_encode($payload),
        ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $package = (array) ($response->json('packages.0') ?? []);
        $waybill = (string) ($package['waybill'] ?? '');

        if ($response->json('success') === false || $waybill === '') {
            throw new CourierException($this->manifestError($response, $package));
        }

        return new ShipmentResult(
            consignmentId: $waybill,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $waybill,
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
        $shipment = $this->trackingShipment($consignmentId);
        $status = (array) ($shipment['Status'] ?? []);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus((string) ($status['StatusType'] ?? ''), (string) ($status['Status'] ?? '')),
            hostOrderReference: $shipment['ReferenceNo'] ?? null,
            trackingCode: $consignmentId,
            raw: $shipment,
        );
    }

    public function trackOrder(string $consignmentId): array
    {
        $shipment = $this->trackingShipment($consignmentId);
        $scans = (array) ($shipment['Scans'] ?? []);

        return array_map(function (array $scan): TrackingEvent {
            $detail = (array) ($scan['ScanDetail'] ?? []);

            return new TrackingEvent(
                status: $this->mapStatus((string) ($detail['StatusType'] ?? ''), (string) ($detail['Scan'] ?? '')),
                occurredAt: (string) ($detail['ScanDateTime'] ?? ''),
                location: $detail['ScannedLocation'] ?? null,
                description: $detail['Instructions'] ?? ($detail['Scan'] ?? null),
                raw: $detail,
            );
        }, $scans);
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        $response = $this->http()->post('/api/p/edit', [
            'waybill'      => $consignmentId,
            'cancellation' => 'true',
        ]);

        if ($response->failed() || $response->json('status') === false) {
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
        $payload = array_filter([
            'waybill'         => $consignmentId,
            'name'            => $data->recipient->name,
            'phone'           => $data->recipient->phone,
            'add'             => $data->recipient->address,
            'pt'              => $data->codAmount > 0 ? 'COD' : 'Pre-paid',
            'cod'             => $data->codAmount > 0 ? $data->codAmount : null,
            'gm'              => $this->weightInGrams($data->weight) ?: null,
            'products_desc'   => $data->itemDescription,
            'shipment_length' => $data->meta['length'] ?? null,
            'shipment_width'  => $data->meta['width'] ?? null,
            'shipment_height' => $data->meta['height'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = $this->http()->post('/api/p/edit', $payload);

        if ($response->failed() || $response->json('status') === false) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Unknown,
            hostOrderReference: $data->hostOrderReference,
            raw: (array) $response->json(),
        );
    }

    public function getDeliveryCharges(QuoteData $data): QuoteResult
    {
        $response = $this->http()->get('/api/kinko/v1/invoice/charges/.json', array_filter([
            'md'    => $this->billingMode(),
            'cgm'   => $this->weightInGrams($data->weight),
            'o_pin' => $this->credentials['origin_pincode'] ?? null,
            'd_pin' => $data->toPostalCode,
            'ss'    => 'Delivered',
            'pt'    => $data->codAmount > 0 ? 'COD' : 'Pre-paid',
        ], static fn ($v) => $v !== null && $v !== ''));

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();
        $charge = (array) ($body[0] ?? $body);

        return new QuoteResult(
            totalCost: (float) ($charge['total_amount'] ?? $charge['gross_amount'] ?? 0),
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

        $provided = (string) ($request->header('X-Delhivery-Token') ?? $request->query('token', ''));

        return hash_equals($token, $provided);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $shipment = (array) $request->input('Shipment', []);
        $status = (array) ($shipment['Status'] ?? []);

        $statusType = (string) ($status['StatusType'] ?? $request->input('status_type', ''));
        $statusText = (string) ($status['Status'] ?? $request->input('status', ''));

        return new WebhookEvent(
            consignmentId: (string) ($shipment['AWB'] ?? $request->input('waybill', '')),
            status: $this->mapStatus($statusType, $statusText),
            hostOrderReference: $shipment['ReferenceNo'] ?? $request->input('order'),
            occurredAt: $status['StatusDateTime'] ?? $request->input('status_datetime'),
            raw: $request->all(),
        );
    }

    private function trackingShipment(string $consignmentId): array
    {
        $response = $this->http()->get('/api/v1/packages/json/', ['waybill' => $consignmentId]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return (array) ($response->json('ShipmentData.0.Shipment') ?? []);
    }

    private function shippingMode(): string
    {
        return strcasecmp((string) ($this->credentials['default_shipping_mode'] ?? 'Surface'), 'Express') === 0
            ? 'Express'
            : 'Surface';
    }

    private function billingMode(): string
    {
        return $this->shippingMode() === 'Express' ? 'E' : 'S';
    }

    private function weightInGrams(float $weightKg): int
    {
        $grams = (int) round($weightKg * 1000);

        return $grams > 0 ? $grams : (int) ($this->credentials['default_weight_grams'] ?? 0);
    }

    private function manifestError(\Illuminate\Http\Client\Response $response, array $package): string
    {
        $remarks = array_filter((array) ($package['remarks'] ?? []), static fn ($v) => is_string($v) && $v !== '');

        if ($remarks !== []) {
            return implode('; ', $remarks);
        }

        $message = (string) ($response->json('rmk') ?? $response->json('error') ?? $response->json('message', ''));

        return $message !== '' ? $message : 'Delhivery manifestation failed.';
    }

    protected function mapStatus(string $statusType, string $status = ''): ShipmentStatus
    {
        $type = strtoupper(trim($statusType));
        $label = strtolower(trim($status));

        return match (true) {
            $type === 'DL' && (str_contains($label, 'rto') || str_contains($label, 'dto')) => ShipmentStatus::Returned,
            $type === 'DL'                                          => ShipmentStatus::Delivered,
            $type === 'UD' && str_contains($label, 'transit')      => ShipmentStatus::InTransit,
            $type === 'UD' && str_contains($label, 'dispatched')   => ShipmentStatus::OutForDelivery,
            $type === 'UD' && str_contains($label, 'pending')      => ShipmentStatus::AtHub,
            $type === 'UD'                                          => ShipmentStatus::Pending,
            $type === 'RT'                                          => ShipmentStatus::ReturnInitiated,
            $type === 'PP'                                          => ShipmentStatus::PickupRequested,
            $type === 'PU' && str_contains($label, 'pending')      => ShipmentStatus::AtHub,
            $type === 'PU' && str_contains($label, 'dispatched')   => ShipmentStatus::OutForDelivery,
            $type === 'PU'                                         => ShipmentStatus::InTransit,
            $type === 'CN'                                          => ShipmentStatus::Cancelled,
            $type === 'LT'                                          => ShipmentStatus::Failed,
            default                                                 => ShipmentStatus::Unknown,
        };
    }
}
