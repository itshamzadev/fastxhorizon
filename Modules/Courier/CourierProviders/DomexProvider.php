<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\Response;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;

class DomexProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders
{
    public function getName(): string
    {
        return 'domex';
    }

    protected function displayName(): string
    {
        return 'Domex';
    }

    public function supportedCountries(): array
    {
        return ['LK'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://apps.domexweb.com',
            self::ENVIRONMENT_LIVE    => 'https://apps.domexweb.com',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: 3d81f0a75c9b4e26ceb42', 'help' => 'Sent as the apiToken header (not Authorization). Obtained from the Domex client-portal user profile; it does not expire.'],
            ['key' => 'sender_name', 'label' => 'Sender Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Riverside Traders'],
            ['key' => 'sender_address', 'label' => 'Sender Address', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 24 Baridhara, Dhaka'],
            ['key' => 'sender_city', 'label' => 'Sender City', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Dhaka'],
            ['key' => 'sender_phone', 'label' => 'Sender Phone', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: +8801700000000'],
            ['key' => 'sender_email', 'label' => 'Sender Email', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: pickup@example.com'],
            ['key' => 'pickup_branch', 'label' => 'Pickup Branch', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: DHK-CENTRAL', 'help' => 'Collection branch identifier. Domex has not published the valid branch list — request it with your account.'],
            ['key' => 'cod_payment_mode', 'label' => 'COD Payment Mode Value', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: COD', 'help' => 'paymentMode value sent for COD orders. Domex has not published the allowed values; default COD.'],
            ['key' => 'prepaid_payment_mode', 'label' => 'Prepaid Payment Mode Value', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Prepaid', 'help' => 'paymentMode value sent for prepaid orders. Domex has not published the allowed values; default Prepaid.'],
        ];
    }

    public function locationLevels(): array
    {
        return [];
    }

    public function addressFields(): array
    {
        return [
            ['key' => 'city_name', 'label' => 'City', 'type' => 'text', 'required' => true],
            ['key' => 'postal_code', 'label' => 'Postal Code', 'type' => 'text', 'required' => false],
        ];
    }

    public function addressMode(): string
    {
        return 'postal';
    }

    protected function defaultHeaders(): array
    {
        return [
            'apiToken'     => (string) ($this->credentials['api_token'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        if ($this->isSandbox()) {
            return $this->simulatedShipment($data);
        }

        $response = $this->http()->post('/api/pickupData', $this->pickupPayload($data));

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $body = (array) $response->json();
        $waybill = $this->extractWaybill($body);

        if ($waybill === '') {
            throw new CourierException('Domex did not return a waybill number. Raw response captured for review.');
        }

        return new ShipmentResult(
            consignmentId: $waybill,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $waybill,
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

    private function orderInfo(string $consignmentId): ShipmentResult
    {
        $events = $this->trackOrder($consignmentId);
        $latest = end($events);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $latest instanceof TrackingEvent ? $latest->status : ShipmentStatus::Unknown,
            trackingCode: $consignmentId,
            raw: array_map(static fn (TrackingEvent $event): array => $event->raw, $events),
        );
    }

    public function trackOrder(string $consignmentId): array
    {
        if ($this->isSandbox()) {
            return $this->simulatedTracking();
        }

        $response = $this->track($consignmentId);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return $this->parseTrackingEvents((array) $response->json(), $consignmentId);
    }

    private function track(string $wayBillNumbers): Response
    {
        return $this->http()->post('/api/orderTracking', ['way_bill_numbers' => $wayBillNumbers]);
    }

    private function pickupPayload(OrderData $data): array
    {
        $recipient = $data->recipient;

        return [
            'senderName'      => (string) ($this->credentials['sender_name'] ?? ''),
            'senderAdddress'  => (string) ($this->credentials['sender_address'] ?? ''),
            'senderCity'      => (string) ($this->credentials['sender_city'] ?? ''),
            'senderPhone'     => (string) ($this->credentials['sender_phone'] ?? ''),
            'senderEmail'     => (string) ($this->credentials['sender_email'] ?? ''),
            'receiverName'    => $recipient->name,
            'receiverPhone'   => $recipient->phone,
            'receiverAddress' => $recipient->address,
            'receiverCity'    => (string) ($recipient->cityName ?? ''),
            'paymentMode'     => $this->paymentMode($data),
            'pickupBranch'    => (string) ($this->credentials['pickup_branch'] ?? ''),
        ];
    }

    private function paymentMode(OrderData $data): string
    {
        return $data->codAmount > 0
            ? (string) ($this->credentials['cod_payment_mode'] ?? 'COD')
            : (string) ($this->credentials['prepaid_payment_mode'] ?? 'Prepaid');
    }

    private function isSandbox(): bool
    {
        return $this->environment === self::ENVIRONMENT_SANDBOX;
    }

    private function simulatedShipment(OrderData $data): ShipmentResult
    {
        $waybill = $this->simulatedWaybill($data->hostOrderReference);

        return new ShipmentResult(
            consignmentId: $waybill,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $waybill,
            codAmount: $data->codAmount,
            raw: ['simulated' => true, 'request' => $this->pickupPayload($data)],
        );
    }

    private function simulatedTracking(): array
    {
        return [new TrackingEvent(
            status: ShipmentStatus::Pending,
            occurredAt: now()->toDateTimeString(),
            location: null,
            description: 'Pickup request received (sandbox simulation).',
            raw: ['simulated' => true],
        )];
    }

    private function simulatedWaybill(string $hostOrderReference): string
    {
        $reference = preg_replace('/[^A-Za-z0-9]/', '', $hostOrderReference);

        if ($reference === null || $reference === '') {
            $reference = substr(sha1($hostOrderReference), 0, 12);
        }

        return 'DX-SBX-'.strtoupper($reference);
    }

    private function extractWaybill(array $body): string
    {
        $candidates = ['waybill', 'way_bill_number', 'waybillNumber', 'waybill_no', 'tracking_number', 'awb', 'awb_number', 'consignment_no'];

        foreach ([$body, (array) ($body['data'] ?? []), (array) ($body['result'] ?? [])] as $scope) {
            foreach ($candidates as $key) {
                $value = $scope[$key] ?? null;

                if (is_scalar($value) && (string) $value !== '') {
                    return (string) $value;
                }
            }
        }

        return '';
    }

    private function parseTrackingEvents(array $body, string $waybill): array
    {
        return array_map(fn (array $event): TrackingEvent => new TrackingEvent(
            status: $this->mapStatus($this->firstValue($event, ['status', 'current_status', 'statusName', 'state'])),
            occurredAt: $this->firstValue($event, ['date', 'datetime', 'timestamp', 'updated_at', 'time', 'statusDate']),
            location: $this->firstValue($event, ['location', 'city', 'branch', 'hub']) ?: null,
            description: $this->firstValue($event, ['description', 'remarks', 'message', 'statusName', 'status']) ?: null,
            raw: $event,
        ), $this->locateEventList($body, $waybill));
    }

    private function locateEventList(array $body, string $waybill): array
    {
        $scopes = [
            $body[$waybill] ?? null,
            $body['data'][$waybill] ?? null,
            $body['tracking'] ?? null,
            $body['events'] ?? null,
            $body['data'] ?? null,
            $body['result'] ?? null,
            $body,
        ];

        foreach ($scopes as $scope) {
            if (is_array($scope) && $scope !== [] && array_is_list($scope)) {
                return array_values(array_filter($scope, 'is_array'));
            }
        }

        return [];
    }

    private function firstValue(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        $status = strtolower(trim($raw));

        return match (true) {
            str_contains($status, 'out for delivery')                            => ShipmentStatus::OutForDelivery,
            str_contains($status, 'delivered')                                    => ShipmentStatus::Delivered,
            str_contains($status, 'picked up') || str_contains($status, 'pickup') => ShipmentStatus::PickedUp,
            str_contains($status, 'returned') || str_contains($status, 'rto')     => ShipmentStatus::Returned,
            str_contains($status, 'hold')                                         => ShipmentStatus::OnHold,
            str_contains($status, 'cancel')                                       => ShipmentStatus::Cancelled,
            str_contains($status, 'exception') || str_contains($status, 'failed') => ShipmentStatus::Failed,
            str_contains($status, 'hub') || str_contains($status, 'facility')     => ShipmentStatus::AtHub,
            str_contains($status, 'transit') || str_contains($status, 'received') => ShipmentStatus::InTransit,
            str_contains($status, 'pending')                                      => ShipmentStatus::Pending,
            default                                                               => ShipmentStatus::Unknown,
        };
    }
}
