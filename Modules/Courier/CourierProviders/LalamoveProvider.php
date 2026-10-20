<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;

class LalamoveProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    EstimatesDeliveryCharge,
    CancelsOrders,
    HandlesWebhooks
{
    private const PAYMENT_METHOD_OPTIONS = [
        ['id' => 'CREDIT', 'label' => 'Lalamove Wallet'],
        ['id' => 'CASH', 'label' => 'Cash to Rider'],
        ['id' => 'POSTPAID', 'label' => 'Monthly Invoice'],
    ];

    private const YES_NO_OPTIONS = [
        ['id' => '0', 'label' => 'No'],
        ['id' => '1', 'label' => 'Yes'],
    ];

    private const ERROR_MESSAGES = [
        'ERR_OUT_OF_SERVICE_AREA'     => 'Lalamove does not deliver to this address yet. The pickup or delivery point is outside their service area.',
        'ERR_REVERSE_GEOCODE_FAILURE' => 'Lalamove could not locate this address on the map. Set the delivery address on the map so it has valid coordinates.',
        'ERR_INVALID_MARKET'          => 'Lalamove is not available in the selected country. Choose a country Lalamove operates in on the delivery partner settings.',
        'ERR_INVALID_COUNTRY'         => 'Lalamove is not available in the selected country. Choose a country Lalamove operates in on the delivery partner settings.',
        'ERR_INVALID_SERVICE_TYPE'    => 'The selected vehicle type is not offered in this city. Pick another delivery type and try again.',
        'ERR_INVALID_SPECIAL_REQUEST' => 'The selected add-on service is not offered for this vehicle type. Remove it or pick another delivery type.',
        'ERR_INVALID_PHONE_NUMBER'    => 'The sender or customer phone number is not valid. Correct the number, including its country code, and try again.',
        'ERR_INVALID_SCHEDULE_TIME'   => 'The requested pickup time has already passed. Choose a future pickup time and try again.',
        'ERR_INSUFFICIENT_STOPS'      => 'Lalamove needs both a pickup and a delivery address. Complete the store address and the customer address, then try again.',
        'ERR_TOO_MANY_STOPS'          => 'This booking has more delivery points than Lalamove allows.',
        'ERR_REQUIRED_FIELD'          => 'Some required delivery information is missing. Complete the store and customer details, then try again.',
        'ERR_MISSING_FIELD'           => 'Some required delivery information is missing. Complete the store and customer details, then try again.',
        'ERR_INVALID_FIELD'           => 'Some of the delivery information is not in the format Lalamove expects. Review the store and customer details, then try again.',
        'ERR_INVALID_QUOTATION_ID'    => 'The Lalamove price quote expired before the booking was placed. Try sending the order again.',
        'ERR_INSUFFICIENT_CREDIT'     => 'The Lalamove wallet does not have enough credit for this booking. Top up the wallet and try again.',
        'ERR_ORDER_NOT_FOUND'         => 'Lalamove has no record of this booking. It may have been removed on the Lalamove side.',
        'ERR_INVALID_ORDER_STATUS'    => 'This booking has moved past the stage where Lalamove allows this change.',
        'ERR_CANCELLATION_FORBIDDEN'  => 'Lalamove no longer allows this booking to be cancelled. A rider has already been assigned or is on the way.',
        'ERR_CHANGE_DRIVER'           => 'Lalamove no longer allows the rider to be changed for this booking.',
        'ERR_INVALID_TIPS'            => 'The priority fee amount is not valid. Enter a higher amount and try again.',
        'ERR_EXCEED_MIN_TIPS'         => 'The priority fee is below the minimum Lalamove accepts.',
        'ERR_EXCEED_MAX_TIPS'         => 'The priority fee is above the maximum Lalamove accepts.',
        'ERR_RATE_LIMIT_EXCEEDED'     => 'Too many requests were sent to Lalamove. Wait a moment and try again.',
        'ERR_INVALID_RESPONSE'        => 'Lalamove could not reach this store for delivery updates. Check that the site is publicly reachable.',
    ];

    public function getName(): string
    {
        return 'lalamove';
    }

    protected function displayName(): string
    {
        return 'Lalamove';
    }

    public function countryProfiles(): array
    {
        return [
            'BD' => ['market' => 'BD', 'currency' => 'BDT', 'calling_code' => '880', 'languages' => ['en_BD', 'bn_BD'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'HK' => ['market' => 'HK', 'currency' => 'HKD', 'calling_code' => '852', 'languages' => ['en_HK', 'zh_HK'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK330' => 'Truck 3.3m', 'TRUCK550' => 'Truck 5.5m', 'WALKER' => 'Walker']],
            'TH' => ['market' => 'TH', 'currency' => 'THB', 'calling_code' => '66', 'languages' => ['en_TH', 'th_TH'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'SG' => ['market' => 'SG', 'currency' => 'SGD', 'calling_code' => '65', 'languages' => ['en_SG'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck', 'WALKER' => 'Walker']],
            'PH' => ['market' => 'PH', 'currency' => 'PHP', 'calling_code' => '63', 'languages' => ['en_PH'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'SEDAN' => 'Sedan', 'MPV' => 'MPV', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'MY' => ['market' => 'MY', 'currency' => 'MYR', 'calling_code' => '60', 'languages' => ['en_MY', 'ms_MY'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'ID' => ['market' => 'ID', 'currency' => 'IDR', 'calling_code' => '62', 'languages' => ['en_ID', 'id_ID'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'VN' => ['market' => 'VN', 'currency' => 'VND', 'calling_code' => '84', 'languages' => ['en_VN', 'vi_VN'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'TW' => ['market' => 'TW', 'currency' => 'TWD', 'calling_code' => '886', 'languages' => ['en_TW', 'zh_TW'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'JP' => ['market' => 'JP', 'currency' => 'JPY', 'calling_code' => '81', 'languages' => ['en_JP', 'ja_JP'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'MX' => ['market' => 'MX', 'currency' => 'MXN', 'calling_code' => '52', 'languages' => ['en_MX', 'es_MX'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
            'BR' => ['market' => 'BR', 'currency' => 'BRL', 'calling_code' => '55', 'languages' => ['en_BR', 'pt_BR'], 'service_types' => ['MOTORCYCLE' => 'Motorcycle', 'CAR' => 'Car', 'VAN' => 'Van', 'TRUCK' => 'Truck']],
        ];
    }

    public function supportedCountries(): array
    {
        return array_keys($this->countryProfiles());
    }

    public function legacyCountryCredential(): ?string
    {
        return 'market';
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://rest.sandbox.lalamove.com',
            self::ENVIRONMENT_LIVE    => 'https://rest.lalamove.com',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: pk_test_4a7b91c3e5d2', 'help' => 'From the Developers tab of your Lalamove Partner Portal.'],
            ['key' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: sk_test_9f2e8d6c4b1a7350', 'help' => 'Issued alongside the API Key.'],
            ['key' => 'language', 'label' => 'Address Language', 'type' => 'select', 'required' => true, 'options' => $this->languageOptions(), 'translatable_options' => false, 'help' => 'Language the rider sees addresses in.'],
            ['key' => 'default_service_type', 'label' => 'Delivery Vehicle', 'type' => 'select', 'required' => true, 'options' => $this->serviceTypeOptions(), 'help' => 'Vehicle booked when an order does not name one. Only vehicles available in the operating country selected above are listed.'],
            ['key' => 'sender_name', 'label' => 'Pickup Contact Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Alex Karim', 'help' => 'Shown to the driver at pickup.'],
            ['key' => 'sender_phone', 'label' => 'Pickup Contact Phone', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: +8801700000000', 'help' => 'The number the driver calls at pickup. Include the country code.'],
            ['key' => 'origin_address', 'label' => 'Pickup Street Address', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 1/B/3 Farmgate, Tejgaon, Dhaka', 'help' => 'Where the rider collects the parcel.'],
            ['key' => 'origin_latitude', 'label' => 'Pickup Map Latitude', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 23.7808', 'help' => 'Open Google Maps, right-click your pickup point and click the numbers that appear. The first one is the latitude.'],
            ['key' => 'origin_longitude', 'label' => 'Pickup Map Longitude', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 90.4093', 'help' => 'The second of the two numbers from the same Google Maps click. Lalamove sends the rider to this exact point.'],
            ['key' => 'payment_method', 'label' => 'How Lalamove Charges You', 'type' => 'select', 'required' => false, 'options' => self::PAYMENT_METHOD_OPTIONS, 'help' => 'Wallet draws from your prepaid Lalamove credit, Cash is paid to the rider, Monthly Invoice bills you later.'],
            ['key' => 'is_pod_enabled', 'label' => 'Collect Proof of Delivery', 'type' => 'select', 'required' => false, 'options' => self::YES_NO_OPTIONS, 'help' => 'Asks the rider to capture a delivery photo or signature.'],
        ];
    }

    public function deliveryTypes(): array
    {
        return $this->profile()['service_types'];
    }

    public function locationLevels(): array
    {
        return [];
    }

    public function addressMode(): string
    {
        return 'geo';
    }

    public function addressFields(): array
    {
        return [
            ['key' => 'latitude', 'label' => 'Latitude', 'type' => 'text', 'required' => true],
            ['key' => 'longitude', 'label' => 'Longitude', 'type' => 'text', 'required' => true],
        ];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $quotation = $this->requestQuotation(
            $this->dropoffCoordinates($data->recipient),
            $data->recipient->address,
            $this->serviceType($data->meta['delivery_type'] ?? null),
        );

        $stops = (array) ($quotation['stops'] ?? []);

        $payload = ['data' => array_filter([
            'quotationId'  => (string) ($quotation['quotationId'] ?? ''),
            'paymentMethod'=> (string) ($this->credentials['payment_method'] ?? 'CREDIT'),
            'sender'       => [
                'stopId' => (string) ($stops[0]['stopId'] ?? ''),
                'name'   => (string) ($this->credentials['sender_name'] ?? ''),
                'phone'  => $this->toE164((string) ($this->credentials['sender_phone'] ?? '')),
            ],
            'recipients'   => [array_filter([
                'stopId'  => (string) ($stops[1]['stopId'] ?? ''),
                'name'    => $data->recipient->name,
                'phone'   => $this->toE164($data->recipient->phone),
                'remarks' => $data->note,
            ], static fn ($value) => $value !== null && $value !== '')],
            'isPODEnabled' => $this->flag('is_pod_enabled'),
            'metadata'     => ['hostOrderReference' => $data->hostOrderReference],
        ], static fn ($value) => $value !== null && $value !== '' && $value !== false)];

        $response = $this->send('POST', '/v3/orders', $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: (string) $response->json('data.orderId', ''),
            status: $this->mapStatus((string) $response->json('data.status', '')),
            hostOrderReference: $data->hostOrderReference,
            deliveryFee: $this->totalFromBreakdown((array) $response->json('data.priceBreakdown', [])),
            codAmount: $data->codAmount,
            raw: (array) $response->json('data', []),
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
        $order = $this->fetchOrder($consignmentId);
        $status = (string) ($order['status'] ?? '');

        if ($status === '') {
            return [];
        }

        return [new TrackingEvent(
            status: $this->mapStatus($status),
            occurredAt: '',
            location: $order['shareLink'] ?? null,
            description: null,
            raw: $order,
        )];
    }

    public function getDeliveryCharges(QuoteData $data): QuoteResult
    {
        $quotation = $this->requestQuotation(
            ['lat' => (string) ($data->toLatitude ?? ''), 'lng' => (string) ($data->toLongitude ?? '')],
            (string) ($data->meta['address'] ?? ''),
            $this->serviceType($data->deliveryType),
        );

        $breakdown = (array) ($quotation['priceBreakdown'] ?? []);

        return new QuoteResult(
            totalCost: (float) ($breakdown['total'] ?? 0),
            currency: (string) ($breakdown['currency'] ?? $this->profile()['currency']),
            raw: $quotation,
        );
    }

    public function cancelOrder(string $consignmentId): ShipmentResult
    {
        $response = $this->send('DELETE', "/v3/orders/{$consignmentId}");

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: ShipmentStatus::Cancelled,
            raw: (array) $response->json(),
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return $request->has('eventType') || $request->has('data');
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $order = (array) ($request->input('data.order') ?? $request->input('data') ?? []);

        return new WebhookEvent(
            consignmentId: (string) ($order['orderId'] ?? ''),
            status: $this->mapStatus((string) ($order['status'] ?? 'unknown')),
            hostOrderReference: $order['metadata']['hostOrderReference'] ?? null,
            occurredAt: $request->input('timestamp'),
            raw: $request->all(),
        );
    }

    private function orderInfo(string $consignmentId): ShipmentResult
    {
        $order = $this->fetchOrder($consignmentId);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->mapStatus((string) ($order['status'] ?? '')),
            deliveryFee: $this->totalFromBreakdown((array) ($order['priceBreakdown'] ?? [])),
            codAmount: null,
            raw: $order,
        );
    }

    private function fetchOrder(string $consignmentId): array
    {
        $response = $this->send('GET', "/v3/orders/{$consignmentId}");

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return (array) $response->json('data', []);
    }

    private function requestQuotation(array $dropoff, string $dropoffAddress, string $serviceType): array
    {
        $payload = ['data' => array_filter([
            'serviceType'      => $serviceType,
            'language'         => $this->language(),
            'stops'            => [
                ['coordinates' => $this->pickupCoordinates(), 'address' => (string) ($this->credentials['origin_address'] ?? '')],
                ['coordinates' => $dropoff, 'address' => $dropoffAddress],
            ],
        ], static fn ($value) => $value !== null && $value !== '' && $value !== false)];

        $response = $this->send('POST', '/v3/quotations', $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return (array) $response->json('data', []);
    }

    private function pickupCoordinates(): array
    {
        return [
            'lat' => (string) ($this->credentials['origin_latitude'] ?? ''),
            'lng' => (string) ($this->credentials['origin_longitude'] ?? ''),
        ];
    }

    private function dropoffCoordinates(RecipientData $recipient): array
    {
        return [
            'lat' => (string) ($recipient->latitude ?? ''),
            'lng' => (string) ($recipient->longitude ?? ''),
        ];
    }

    private function profile(): array
    {
        return $this->countryProfiles()[$this->country()];
    }

    private function languageOptions(): array
    {
        return array_map(
            static fn (string $language): array => ['id' => $language, 'label' => $language],
            $this->profile()['languages'],
        );
    }

    private function serviceTypeOptions(): array
    {
        $types = $this->profile()['service_types'];

        return array_map(
            static fn (string $type, string $label): array => ['id' => $type, 'label' => $label],
            array_keys($types),
            array_values($types),
        );
    }

    private function language(): string
    {
        $selected = (string) ($this->credentials['language'] ?? '');
        $languages = $this->profile()['languages'];

        return in_array($selected, $languages, true) ? $selected : $languages[0];
    }

    private function serviceType(?string $selected): string
    {
        $types = $this->profile()['service_types'];
        $configured = (string) ($this->credentials['default_service_type'] ?? '');

        return match (true) {
            $selected !== null && isset($types[$selected]) => $selected,
            isset($types[$configured])                     => $configured,
            default                                        => (string) array_key_first($types),
        };
    }

    private function flag(string $key): bool
    {
        return filter_var($this->credentials[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function toE164(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return $phone;
        }

        if (str_starts_with($phone, '+')) {
            return '+'.preg_replace('/\D/', '', substr($phone, 1));
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        $code = $this->profile()['calling_code'];

        if (str_starts_with($digits, $code)) {
            return '+'.$digits;
        }

        return '+'.$code.ltrim($digits, '0');
    }

    private function totalFromBreakdown(array $breakdown): ?float
    {
        return isset($breakdown['total']) ? (float) $breakdown['total'] : null;
    }

    protected function failFromResponse(Response $response): never
    {
        $messages = [];

        foreach ((array) $response->json('errors', []) as $error) {
            $code = strtoupper(trim((string) (is_array($error) ? ($error['message'] ?? '') : $error)));
            $messages[] = self::ERROR_MESSAGES[$code] ?? $this->statusMessage($response->status());
        }

        $messages = array_values(array_unique(array_filter($messages)));

        throw new CourierException($messages !== []
            ? implode(' ', $messages)
            : $this->statusMessage($response->status()));
    }

    private function statusMessage(int $status): string
    {
        return match ($status) {
            400     => 'Lalamove could not read this booking request. Review the store and customer details, then try again.',
            401     => 'Lalamove rejected the API credentials. Update the API key and secret in the delivery partner settings.',
            402     => 'The Lalamove wallet does not have enough credit for this booking. Top up the wallet and try again.',
            403     => 'Lalamove blocked this request. Contact Lalamove support to review the account.',
            404     => 'Lalamove has no record of this booking or rider.',
            429     => 'Too many requests were sent to Lalamove. Wait a moment and try again.',
            default => $status >= 500
                ? 'Lalamove is not responding right now. Try again in a few minutes.'
                : 'Lalamove could not complete this request. Review the store and customer details, then try again.',
        };
    }

    private function send(string $method, string $path, ?array $data = null): Response
    {
        $method = strtoupper($method);
        $timestamp = (string) (int) round(microtime(true) * 1000);
        $rawBody = $data !== null ? json_encode($data) : '';
        $signature = hash_hmac('sha256', $timestamp."\r\n".$method."\r\n".$path."\r\n\r\n".$rawBody, (string) ($this->credentials['api_secret'] ?? ''));

        $request = Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->retry((int) config('courier.http.retries', 0), 200)
            ->withHeaders([
                'Authorization' => 'hmac '.($this->credentials['api_key'] ?? '').':'.$timestamp.':'.$signature,
                'Market'        => $this->profile()['market'],
                'Request-ID'    => (string) Str::uuid(),
                'Content-Type'  => 'application/json',
            ])
            ->acceptJson();

        if ($rawBody !== '') {
            $request = $request->withBody($rawBody, 'application/json');
        }

        return $request->send($method, $path);
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        return match (strtoupper(trim($raw))) {
            'ASSIGNING_DRIVER'    => ShipmentStatus::Pending,
            'ON_GOING'            => ShipmentStatus::InTransit,
            'PICKED_UP'           => ShipmentStatus::PickedUp,
            'COMPLETED'           => ShipmentStatus::Delivered,
            'CANCELED'            => ShipmentStatus::Cancelled,
            'REJECTED', 'EXPIRED' => ShipmentStatus::Failed,
            default               => ShipmentStatus::Unknown,
        };
    }
}
