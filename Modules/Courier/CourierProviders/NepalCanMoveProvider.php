<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesBatchWebhooks;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\ResolvesLocations;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;

class NepalCanMoveProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    EstimatesDeliveryCharge,
    CancelsOrders,
    ResolvesLocations,
    HandlesWebhooks,
    HandlesBatchWebhooks
{
    private const HOST_SANDBOX = 'https://demo.nepalcanmove.com';
    private const HOST_LIVE    = 'https://portal.nepalcanmove.com';

    private const AUTH_HEADER = 'Authorization';
    private const AUTH_SCHEME = 'Token';

    private const PATHS = [
        'branches'       => '/api/v1/branchlist',
        'deliveryCharge' => '/api/v1/shipping-rate',
        'createOrder'    => '/api/v1/order/create',
        'getOrder'       => '/api/v1/order/detail',
        'statusHistory'  => '/api/v1/order/status',
        'return'         => '/api/v1/order/return',
    ];

    private const DELIVERY_TYPE_MAP = [
        1 => 'DoorToDoor',
        2 => 'BranchToDoor',
        3 => 'DoorToBranch',
        4 => 'BranchToBranch',
    ];

    private const BRANCH_CACHE_TTL = 86400;

    public function getName(): string
    {
        return 'nepal_can_move';
    }

    protected function displayName(): string
    {
        return 'Nepal Can Move';
    }

    public function supportedCountries(): array
    {
        return ['NP'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => self::HOST_SANDBOX,
            self::ENVIRONMENT_LIVE    => self::HOST_LIVE,
        ];
    }

    protected function defaultHeaders(): array
    {
        return [
            self::AUTH_HEADER => trim(self::AUTH_SCHEME.' '.($this->credentials['api_token'] ?? '')),
            'Content-Type'    => 'application/json',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: ncm_7b3e15c9a2d640f8', 'help' => 'Static NCM API token from the vendor portal dashboard; never expires'],
            ['key' => 'source_branch', 'label' => 'Default Source Branch', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: TINKUNE', 'help' => 'The NCM branch your parcels are collected from. Overridable per order.'],
            ['key' => 'package', 'label' => 'Default Package Description', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Parcel', 'help' => 'Parcel description sent with each order when the order carries none of its own.'],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: ncm-h00k-4a9d', 'help' => 'Per-installation random secret appended to the webhook URL (?secret=...); NCM webhooks carry no signature'],
            ['key' => 'sender_name', 'label' => 'Sender Name', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Riverside Traders', 'help' => 'Shown to NCM as the sender contact'],
            ['key' => 'sender_phone', 'label' => 'Sender Phone', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 9800000000', 'help' => 'Sender contact number'],
        ];
    }

    public function locationLevels(): array
    {
        return [];
    }

    public function addressMode(): string
    {
        return 'branch';
    }

    public function deliveryTypes(): array
    {
        return [
            1 => 'Door to Door',
            2 => 'Branch to Door',
            3 => 'Door to Branch',
            4 => 'Branch to Branch',
        ];
    }

    public function getCities(): array
    {
        $branches = Cache::remember(
            'courier.ncm.branches.'.$this->environment(),
            self::BRANCH_CACHE_TTL,
            function (): array {
                $response = $this->http()->get($this->path('branches'));

                if ($response->failed()) {
                    $this->failFromResponse($response);
                }

                return $this->branchList((array) $response->json());
            },
        );

        return array_map(
            static fn (array $branch): Location => new Location(
                id: (string) $branch['id'],
                name: (string) $branch['name'],
                level: 'city',
                raw: $branch,
            ),
            $branches,
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
        $sourceBranch = $data->recipient->sourceBranchName ?: (string) ($this->credentials['source_branch'] ?? '');

        $payload = array_filter([
            'name'              => $data->recipient->name,
            'phone'             => $data->recipient->phone,
            'codCharge'         => (string) $data->codAmount,
            'address'           => $data->recipient->address,
            'sourceBranch'      => $sourceBranch,
            'destinationBranch' => (string) $data->recipient->destinationBranchName,
            'package'           => $data->itemDescription ?: (string) ($this->credentials['package'] ?? 'Parcel'),
            'deliveryType'      => $this->deliveryType($data->meta['delivery_type'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '');

        $response = $this->http()->post($this->path('createOrder'), $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();

        return new ShipmentResult(
            consignmentId: (string) $this->value($body, ['orderid', 'order_id', 'id']),
            status: $this->mapStatus((string) $this->value($body, ['status'], 'Pending')),
            hostOrderReference: $data->hostOrderReference,
            codAmount: $data->codAmount,
            raw: $body + [
                'source_branch_id'      => $data->recipient->sourceBranchId,
                'destination_branch_id' => $data->recipient->destinationBranchId,
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

    public function trackOrder(string $consignmentId): array
    {
        $response = $this->http()->get($this->path('statusHistory'), ['id' => $consignmentId]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return array_map(
            fn (array $event): TrackingEvent => new TrackingEvent(
                status: $this->mapStatus((string) $this->value($event, ['status'])),
                occurredAt: (string) $this->value($event, ['added_time', 'addedTime', 'time'], ''),
                description: $this->value($event, ['status']),
                raw: $event,
            ),
            $this->statusList((array) $response->json()),
        );
    }

    public function getDeliveryCharges(QuoteData $data): QuoteResult
    {
        $payload = array_filter([
            'source'       => (string) $data->sourceBranchName,
            'destination'  => (string) $data->destinationBranchName,
            'deliveryType' => $this->deliveryType($data->deliveryType),
        ], static fn ($value) => $value !== null && $value !== '');

        $response = $this->http()->post($this->path('deliveryCharge'), $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();

        return new QuoteResult(
            totalCost: (float) $this->value($body, ['charge', 'delivery_charge', 'deliveryCharge', 'amount'], 0),
            currency: 'NPR',
            raw: $body,
        );
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        $response = $this->http()->post($this->path('return'), [
            'id'     => $consignmentId,
            'reason' => 'Cancelled by merchant',
        ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::ReturnInitiated,
            raw: (array) $response->json(),
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) ($this->credentials['webhook_secret'] ?? '');

        return $secret !== '' && hash_equals($secret, (string) $request->query('secret', ''));
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        return $this->parseWebhookEvents($request)[0] ?? new WebhookEvent(
            consignmentId: '',
            status: ShipmentStatus::Unknown,
            raw: $request->all(),
        );
    }

    public function parseWebhookEvents(Request $request): array
    {
        $status = $this->mapStatus((string) ($request->input('event') ?? 'unknown'));
        $orderIds = $request->input('orderIds', $request->input('orderId', []));
        $orderIds = is_array($orderIds) ? $orderIds : [$orderIds];

        return array_values(array_map(
            fn ($orderId): WebhookEvent => new WebhookEvent(
                consignmentId: (string) $orderId,
                status: $status,
                occurredAt: $request->input('timestamp'),
                raw: $request->all(),
            ),
            array_filter($orderIds, static fn ($orderId): bool => (string) $orderId !== ''),
        ));
    }

    private function orderInfo(string $consignmentId): ShipmentResult
    {
        $response = $this->http()->get($this->path('getOrder'), ['id' => $consignmentId]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus((string) $this->value($body, ['status'])),
            raw: $body,
        );
    }

    private function path(string $key): string
    {
        return self::PATHS[$key];
    }

    private function deliveryType(int|string|null $selected): ?string
    {
        if ($selected === null || $selected === '') {
            return null;
        }

        return self::DELIVERY_TYPE_MAP[(int) $selected] ?? null;
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

    private function branchList(array $body): array
    {
        $rows = $this->value($body, ['branches', 'data'], $body);

        return array_values(array_map(
            fn (array $row): array => [
                'id'    => (string) $this->value($row, ['id', 'branch_id']),
                'name'  => (string) $this->value($row, ['name', 'branch']),
                'phone' => $this->value($row, ['phone', 'contact']),
            ],
            array_filter((array) $rows, 'is_array'),
        ));
    }

    private function statusList(array $body): array
    {
        $rows = $this->value($body, ['statuses', 'history', 'data'], $body);

        if (is_array($rows) && $rows !== [] && !is_array(reset($rows))) {
            return [$body];
        }

        return array_values(array_filter((array) $rows, 'is_array'));
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        return match (strtolower(trim($raw))) {
            'delivered'                      => ShipmentStatus::Delivered,
            'pending'                        => ShipmentStatus::Pending,
            'pickup'                         => ShipmentStatus::PickupRequested,
            'in transit', 'in_transit'       => ShipmentStatus::InTransit,
            'arrived'                        => ShipmentStatus::AtHub,
            'out for delivery', 'sent for delivery' => ShipmentStatus::OutForDelivery,
            'returned'                       => ShipmentStatus::Returned,
            'cancelled', 'canceled'          => ShipmentStatus::Cancelled,
            default                          => ShipmentStatus::Unknown,
        };
    }
}
