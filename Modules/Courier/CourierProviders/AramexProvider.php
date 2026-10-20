<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Support\CountryList;

class AramexProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders
{
    private const DEFAULT_PRODUCT_TYPE = 'PPX';
    private const WEIGHT_UNIT = 'KG';
    private const CLIENT_INFO_VERSION = 'v1';
    private const CLIENT_INFO_SOURCE = 24;
    private const DEFAULT_GOODS_DESCRIPTION = 'Goods';

    private const PRODUCT_TYPE_OPTIONS = [
        ['id' => 'PPX', 'label' => 'Priority Parcel Express'],
        ['id' => 'PDX', 'label' => 'Priority Document Express'],
        ['id' => 'EPX', 'label' => 'Economy Parcel Express'],
        ['id' => 'OND', 'label' => 'Domestic Overnight'],
    ];

    private const PAYMENT_TYPE_OPTIONS = [
        ['id' => 'P', 'label' => 'Prepaid by Me'],
        ['id' => 'C', 'label' => 'Receiver Pays'],
        ['id' => '3', 'label' => 'Third Party Pays'],
    ];

    public function getName(): string
    {
        return 'aramex';
    }

    protected function displayName(): string
    {
        return 'Aramex';
    }

    public function countryProfiles(): array
    {
        return [
            'AE' => ['currency' => 'AED', 'entities' => ['DXB', 'AUH', 'SHJ'], 'default_entity' => 'DXB'],
            'SA' => ['currency' => 'SAR', 'entities' => ['RUH', 'JED', 'DMM'], 'default_entity' => 'RUH'],
            'JO' => ['currency' => 'JOD', 'entities' => ['AMM'], 'default_entity' => 'AMM'],
            'KW' => ['currency' => 'KWD', 'entities' => ['KWI'], 'default_entity' => 'KWI'],
            'QA' => ['currency' => 'QAR', 'entities' => ['DOH'], 'default_entity' => 'DOH'],
            'BH' => ['currency' => 'BHD', 'entities' => ['BAH'], 'default_entity' => 'BAH'],
            'OM' => ['currency' => 'OMR', 'entities' => ['MCT'], 'default_entity' => 'MCT'],
            'LB' => ['currency' => 'LBP', 'entities' => ['BEY'], 'default_entity' => 'BEY'],
            'EG' => ['currency' => 'EGP', 'entities' => ['CAI', 'ALY'], 'default_entity' => 'CAI'],
            'TR' => ['currency' => 'TRY', 'entities' => ['IST'], 'default_entity' => 'IST'],
            'BD' => ['currency' => 'BDT', 'entities' => ['DAC'], 'default_entity' => 'DAC'],
            'PK' => ['currency' => 'PKR', 'entities' => ['KHI', 'LHE', 'ISB'], 'default_entity' => 'KHI'],
            'IN' => ['currency' => 'INR', 'entities' => ['BOM', 'DEL'], 'default_entity' => 'BOM'],
            'GB' => ['currency' => 'GBP', 'entities' => ['LON'], 'default_entity' => 'LON'],
        ];
    }

    public function supportedCountries(): array
    {
        return array_keys($this->countryProfiles());
    }

    public function legacyCountryCredential(): ?string
    {
        return 'account_country_code';
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://ws.dev.aramex.net/shippingapi.v2/shipping/service_1_0.svc/json',
            self::ENVIRONMENT_LIVE    => 'https://ws.aramex.net/shippingapi.v2/shipping/service_1_0.svc/json',
        ];
    }

    private function trackingBaseUrl(): string
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://ws.dev.aramex.net/shippingapi.v2/tracking/service_1_0.svc/json',
            self::ENVIRONMENT_LIVE    => 'https://ws.aramex.net/shippingapi.v2/tracking/service_1_0.svc/json',
        ][$this->environment] ?? 'https://ws.dev.aramex.net/shippingapi.v2/tracking/service_1_0.svc/json';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'username', 'label' => 'Aramex Account Email', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: merchant@example.com', 'help' => 'The email you registered with aramex.com.'],
            ['key' => 'password', 'label' => 'Aramex Account Password', 'type' => 'password', 'required' => true, 'help' => 'The password you sign in to aramex.com with.'],
            ['key' => 'account_number', 'label' => 'Aramex Account Number', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 20016', 'help' => 'Issued when you signed your Aramex contract.'],
            ['key' => 'account_pin', 'label' => 'Account PIN', 'type' => 'password', 'required' => true, 'placeholder' => 'Ex: 331421', 'help' => 'The PIN paired with your account number.'],
            ['key' => 'account_entity', 'label' => 'Aramex Station', 'type' => 'select', 'required' => true, 'options' => $this->entityOptions(), 'translatable_options' => false, 'help' => 'The station your account is registered at in the operating country selected above.'],
            ['key' => 'default_product_type', 'label' => 'Delivery Service', 'type' => 'select', 'required' => false, 'options' => self::PRODUCT_TYPE_OPTIONS, 'help' => 'Aramex product booked when an order does not name one.'],
            ['key' => 'payment_type', 'label' => 'Who Pays Aramex', 'type' => 'select', 'required' => false, 'options' => self::PAYMENT_TYPE_OPTIONS, 'help' => 'Prepaid by Me bills your own Aramex account, Receiver Pays bills the customer on delivery, Third Party Pays bills the account named in your contract.'],
            ['key' => 'origin_company_name', 'label' => 'Business Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Riverside Traders Ltd.', 'help' => 'Shown as the sender on the shipping label.'],
            ['key' => 'origin_full_name', 'label' => 'Pickup Contact Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Alex Karim'],
            ['key' => 'origin_phone', 'label' => 'Pickup Contact Phone', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: +97142345678'],
            ['key' => 'origin_email', 'label' => 'Pickup Contact Email', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: pickup@example.com'],
            ['key' => 'origin_line1', 'label' => 'Pickup Street Address', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Office 402, Bay Square Building 3', 'help' => 'Where Aramex collects your parcels. At least 4 characters.'],
            ['key' => 'origin_city', 'label' => 'Pickup City', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Dubai'],
            ['key' => 'origin_state', 'label' => 'Pickup State or Province', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Dubai'],
            ['key' => 'origin_postal_code', 'label' => 'Pickup Post Code', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: 00000'],
        ];
    }

    public function deliveryTypes(): array
    {
        return array_column(self::PRODUCT_TYPE_OPTIONS, 'label', 'id');
    }

    public function locationLevels(): array
    {
        return [];
    }

    public function addressFields(): array
    {
        return [
            ['key' => 'country_code', 'label' => 'Country', 'type' => 'select', 'required' => true, 'options' => CountryList::options()],
            ['key' => 'postal_code', 'label' => 'Post Code', 'type' => 'text', 'required' => false],
            ['key' => 'city_name', 'label' => 'City', 'type' => 'text', 'required' => true],
            ['key' => 'state_province', 'label' => 'State/Province', 'type' => 'text', 'required' => false],
        ];
    }

    public function addressMode(): string
    {
        return 'postal';
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $payload = [
            'ClientInfo' => $this->clientInfo(),
            'Transaction' => ['Reference1' => $data->hostOrderReference],
            'Shipments' => [[
                'Reference1'       => $data->hostOrderReference,
                'ForeignHAWB'      => $this->foreignHawb($data->hostOrderReference),
                'Shipper'          => $this->shipper(),
                'Consignee'        => $this->consignee($data->recipient),
                'ShippingDateTime' => $this->toAramexDate(Carbon::now()),
                'Comments'         => $data->note ?? '',
                'Details'          => $this->details($data),
            ]],
        ];

        $body = $this->guard($this->request($this->baseUrl(), 'CreateShipments', $payload));
        $shipment = (array) ($body['Shipments'][0] ?? []);

        if (($shipment['HasErrors'] ?? false) === true) {
            throw new CourierException($this->notificationMessage((array) ($shipment['Notifications'] ?? [])) ?: 'Aramex shipment creation failed.');
        }

        $awb = (string) ($shipment['ID'] ?? '');

        if ($awb === '') {
            throw new CourierException('Aramex did not return a shipment number.');
        }

        return new ShipmentResult(
            consignmentId: $awb,
            status: ShipmentStatus::Pending,
            hostOrderReference: $data->hostOrderReference,
            trackingCode: $awb,
            codAmount: $data->codAmount,
            raw: $shipment,
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
        $events = $this->trackingEvents($consignmentId);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->statusFromEvents($events),
            trackingCode: $consignmentId,
            raw: $events,
        );
    }

    public function trackOrder(string $consignmentId): array
    {
        return array_map(fn (array $event): TrackingEvent => new TrackingEvent(
            status: $this->mapStatus((string) ($event['UpdateCode'] ?? ''), (string) ($event['UpdateDescription'] ?? '')),
            occurredAt: $this->fromAramexDate($event['UpdateDateTime'] ?? null),
            location: $event['UpdateLocation'] ?? null,
            description: $event['UpdateDescription'] ?? null,
            raw: $event,
        ), $this->trackingEvents($consignmentId));
    }

    private function trackingEvents(string $consignmentId): array
    {
        $body = $this->guard($this->request($this->trackingBaseUrl(), 'TrackShipments', [
            'ClientInfo'                 => $this->clientInfo(withSource: false),
            'Shipments'                  => [$consignmentId],
            'GetLastTrackingUpdateOnly'  => false,
        ]));

        foreach ((array) ($body['TrackingResults'] ?? []) as $result) {
            if ((string) ($result['Key'] ?? '') === $consignmentId) {
                return array_values((array) ($result['Value'] ?? []));
            }
        }

        return [];
    }

    private function shipper(): array
    {
        return [
            'AccountNumber' => (string) ($this->credentials['account_number'] ?? ''),
            'PartyAddress'  => array_filter([
                'Line1'               => (string) ($this->credentials['origin_line1'] ?? ''),
                'City'                => (string) ($this->credentials['origin_city'] ?? ''),
                'StateOrProvinceCode' => (string) ($this->credentials['origin_state'] ?? ''),
                'PostCode'            => (string) ($this->credentials['origin_postal_code'] ?? ''),
                'CountryCode'         => $this->country(),
            ], static fn ($value) => $value !== ''),
            'Contact'       => array_filter([
                'PersonName'   => (string) ($this->credentials['origin_full_name'] ?? ''),
                'CompanyName'  => (string) ($this->credentials['origin_company_name'] ?? ''),
                'PhoneNumber1' => (string) ($this->credentials['origin_phone'] ?? ''),
                'CellPhone'    => (string) ($this->credentials['origin_phone'] ?? ''),
                'EmailAddress' => (string) ($this->credentials['origin_email'] ?? ''),
            ], static fn ($value) => $value !== ''),
        ];
    }

    private function consignee(RecipientData $recipient): array
    {
        return [
            'PartyAddress' => array_filter([
                'Line1'               => $recipient->address,
                'City'                => (string) ($recipient->cityName ?? ''),
                'StateOrProvinceCode' => (string) ($recipient->stateProvince ?? ''),
                'PostCode'            => (string) ($recipient->postalCode ?? ''),
                'CountryCode'         => (string) ($recipient->countryCode ?? ''),
            ], static fn ($value) => $value !== ''),
            'Contact'      => array_filter([
                'PersonName'   => $recipient->name,
                'CompanyName'  => $recipient->name,
                'PhoneNumber1' => $recipient->phone,
                'CellPhone'    => $recipient->phone,
            ], static fn ($value) => $value !== ''),
        ];
    }

    private function details(OrderData $data): array
    {
        $details = array_filter([
            'ActualWeight'       => ['Value' => max(0.5, $data->weight), 'Unit' => self::WEIGHT_UNIT],
            'ProductGroup'       => $this->productGroup($data->recipient),
            'ProductType'        => $this->productType($data),
            'PaymentType'        => (string) ($this->credentials['payment_type'] ?? 'P'),
            'NumberOfPieces'     => max(1, $data->quantity ?? 1),
            'DescriptionOfGoods' => $data->itemDescription ?: self::DEFAULT_GOODS_DESCRIPTION,
            'GoodsOriginCountry' => $this->country(),
        ], static fn ($value) => $value !== null && $value !== '');

        $customsValue = (float) ($data->meta['value'] ?? $data->codAmount);

        if ($customsValue > 0) {
            $details['CustomsValueAmount'] = ['Value' => $customsValue, 'CurrencyCode' => $this->profile()['currency']];
        }

        if ($data->codAmount > 0) {
            $details['Services'] = 'CODS';
            $details['CashOnDeliveryAmount'] = ['Value' => $data->codAmount, 'CurrencyCode' => $this->profile()['currency']];
        }

        return $details;
    }

    private function profile(): array
    {
        return $this->countryProfiles()[$this->country()];
    }

    private function entityOptions(): array
    {
        return array_map(
            static fn (string $entity): array => ['id' => $entity, 'label' => $entity],
            $this->profile()['entities'],
        );
    }

    private function accountEntity(): string
    {
        $profile = $this->profile();
        $configured = strtoupper(trim((string) ($this->credentials['account_entity'] ?? '')));

        return in_array($configured, $profile['entities'], true) ? $configured : $profile['default_entity'];
    }

    private function productType(OrderData $data): string
    {
        $types = $this->deliveryTypes();
        $selected = (string) ($data->meta['delivery_type'] ?? '');
        $configured = strtoupper(trim((string) ($this->credentials['default_product_type'] ?? '')));

        return match (true) {
            isset($types[$selected])   => $selected,
            isset($types[$configured]) => $configured,
            default                    => self::DEFAULT_PRODUCT_TYPE,
        };
    }

    private function productGroup(RecipientData $recipient): string
    {
        return $this->country() === strtoupper((string) ($recipient->countryCode ?? '')) ? 'DOM' : 'EXP';
    }

    private function clientInfo(bool $withSource = true): array
    {
        $info = [
            'UserName'           => (string) ($this->credentials['username'] ?? ''),
            'Password'           => (string) ($this->credentials['password'] ?? ''),
            'Version'            => self::CLIENT_INFO_VERSION,
            'AccountNumber'      => (string) ($this->credentials['account_number'] ?? ''),
            'AccountPin'         => (string) ($this->credentials['account_pin'] ?? ''),
            'AccountEntity'      => $this->accountEntity(),
            'AccountCountryCode' => $this->country(),
        ];

        if ($withSource) {
            $info['Source'] = self::CLIENT_INFO_SOURCE;
        }

        return $info;
    }

    private function foreignHawb(string $hostOrderReference): string
    {
        $prefix = (string) ($this->credentials['account_number'] ?? '');

        return trim($prefix.'-'.$hostOrderReference, '-');
    }

    private function toAramexDate(Carbon $date): string
    {
        return '/Date('.($date->timestamp * 1000).')/';
    }

    private function fromAramexDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/\/Date\((\d+)/', $value, $matches) === 1) {
            return Carbon::createFromTimestampMs((int) $matches[1])->toDateTimeString();
        }

        return $value;
    }

    private function request(string $baseUrl, string $operation, array $payload): Response
    {
        return Http::baseUrl($baseUrl)
            ->timeout((int) config('courier.http.timeout', 30))
            ->retry((int) config('courier.http.retries', 0), 200)
            ->acceptJson()
            ->post('/'.$operation, $payload);
    }

    private function guard(Response $response): array
    {
        $body = (array) $response->json();

        if ($response->failed() || ($body['HasErrors'] ?? false) === true) {
            throw new CourierException($this->notificationMessage((array) ($body['Notifications'] ?? [])) ?: 'Aramex request failed.');
        }

        return $body;
    }

    private function notificationMessage(array $notifications): string
    {
        $messages = array_filter(array_map(
            static fn ($notification) => is_array($notification) ? (string) ($notification['Message'] ?? '') : '',
            $notifications,
        ), static fn ($message) => $message !== '');

        return implode('; ', array_unique($messages));
    }

    protected function mapStatus(string $code, string $description = ''): ShipmentStatus
    {
        $label = strtolower(trim($description));

        return match (true) {
            str_contains($label, 'delivered')                                   => ShipmentStatus::Delivered,
            str_contains($label, 'out for delivery')                            => ShipmentStatus::OutForDelivery,
            str_contains($label, 'picked up') || str_contains($label, 'pickup') => ShipmentStatus::PickedUp,
            str_contains($label, 'returned') || str_contains($label, 'rto')     => ShipmentStatus::Returned,
            str_contains($label, 'hold')                                        => ShipmentStatus::OnHold,
            str_contains($label, 'exception') || str_contains($label, 'failed') => ShipmentStatus::Failed,
            str_contains($label, 'facility') || str_contains($label, 'hub')     => ShipmentStatus::AtHub,
            str_contains($label, 'transit') || str_contains($label, 'received') => ShipmentStatus::InTransit,
            $label !== ''                                                       => ShipmentStatus::InTransit,
            default                                                             => ShipmentStatus::Unknown,
        };
    }

    private function statusFromEvents(array $events): ShipmentStatus
    {
        $latest = end($events);

        if (!is_array($latest)) {
            return ShipmentStatus::Unknown;
        }

        return $this->mapStatus((string) ($latest['UpdateCode'] ?? ''), (string) ($latest['UpdateDescription'] ?? ''));
    }
}
