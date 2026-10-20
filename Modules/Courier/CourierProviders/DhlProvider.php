<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Support\Carbon;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Support\CountryList;

class DhlProvider extends CourierProvider implements
    CreatesOrders,
    ProvidesOrderInfo,
    TracksOrders,
    EstimatesDeliveryCharge
{
    private const UNIT_OF_MEASUREMENT = 'metric';
    private const PLANNED_SHIP_OFFSET_DAYS = 1;

    private const DEFAULT_PROFILE = [
        'currency' => 'USD',
        'domestic_product_code' => 'N',
        'international_product_code' => 'P',
    ];

    public function getName(): string
    {
        return 'dhl';
    }

    protected function displayName(): string
    {
        return 'DHL Express';
    }

    public function countryProfiles(): array
    {
        return [
            'BD' => ['currency' => 'BDT'], 'IN' => ['currency' => 'INR'], 'PK' => ['currency' => 'PKR'],
            'NP' => ['currency' => 'NPR'], 'LK' => ['currency' => 'LKR'], 'MY' => ['currency' => 'MYR'],
            'SG' => ['currency' => 'SGD'], 'TH' => ['currency' => 'THB'], 'ID' => ['currency' => 'IDR'],
            'PH' => ['currency' => 'PHP'], 'VN' => ['currency' => 'VND'], 'CN' => ['currency' => 'CNY'],
            'HK' => ['currency' => 'HKD'], 'JP' => ['currency' => 'JPY'], 'KR' => ['currency' => 'KRW'],
            'AE' => ['currency' => 'AED'], 'SA' => ['currency' => 'SAR'], 'QA' => ['currency' => 'QAR'],
            'KW' => ['currency' => 'KWD'], 'BH' => ['currency' => 'BHD'], 'OM' => ['currency' => 'OMR'],
            'JO' => ['currency' => 'JOD'], 'EG' => ['currency' => 'EGP'], 'TR' => ['currency' => 'TRY'],
            'GB' => ['currency' => 'GBP'], 'AU' => ['currency' => 'AUD'], 'CA' => ['currency' => 'CAD'],
            'NG' => ['currency' => 'NGN'], 'ZA' => ['currency' => 'ZAR'], 'KE' => ['currency' => 'KES'],
            'BR' => ['currency' => 'BRL'], 'MX' => ['currency' => 'MXN'],
        ];
    }

    public function supportedCountries(): array
    {
        return array_keys(CountryList::all());
    }

    public function legacyCountryCredential(): ?string
    {
        return 'origin_country_code';
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => 'https://express.api.dhl.com/mydhlapi/test',
            self::ENVIRONMENT_LIVE    => 'https://express.api.dhl.com/mydhlapi',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_username', 'label' => 'MyDHL API Username', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: apiUser12345', 'help' => 'From your MyDHL API portal account.'],
            ['key' => 'api_password', 'label' => 'MyDHL API Password', 'type' => 'password', 'required' => true, 'help' => 'The password paired with the MyDHL API username, from your MyDHL API portal account.'],
            ['key' => 'account_number', 'label' => 'DHL Account Number', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 123456789', 'help' => 'The account DHL bills your shipments to.'],
            ['key' => 'origin_company_name', 'label' => 'Business Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Riverside Traders Ltd.', 'help' => 'Shown as the sender on the shipping label.'],
            ['key' => 'origin_full_name', 'label' => 'Pickup Contact Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Alex Karim'],
            ['key' => 'origin_phone', 'label' => 'Pickup Contact Phone', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: +8801700000000'],
            ['key' => 'origin_email', 'label' => 'Pickup Contact Email', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: pickup@example.com'],
            ['key' => 'origin_address_line1', 'label' => 'Pickup Street Address', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 1/B/3 Farmgate, Tejgaon', 'help' => 'Where DHL collects your parcels.'],
            ['key' => 'origin_city_name', 'label' => 'Pickup City', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Dhaka'],
            ['key' => 'origin_postal_code', 'label' => 'Pickup Postal Code', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 1215'],
            ['key' => 'default_package_length', 'label' => 'Usual Parcel Length (cm)', 'type' => 'number', 'required' => true, 'placeholder' => 'Ex: 30', 'help' => 'Used to price a quote when the order carries no measurements.'],
            ['key' => 'default_package_width', 'label' => 'Usual Parcel Width (cm)', 'type' => 'number', 'required' => true, 'placeholder' => 'Ex: 20'],
            ['key' => 'default_package_height', 'label' => 'Usual Parcel Height (cm)', 'type' => 'number', 'required' => true, 'placeholder' => 'Ex: 15'],
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
            ['key' => 'postal_code', 'label' => 'Postal Code', 'type' => 'text', 'required' => true],
            ['key' => 'city_name', 'label' => 'City', 'type' => 'text', 'required' => true],
            ['key' => 'state_province', 'label' => 'State/Province', 'type' => 'text', 'required' => false],
        ];
    }

    public function addressMode(): string
    {
        return 'postal';
    }

    protected function defaultHeaders(): array
    {
        $token = base64_encode(($this->credentials['api_username'] ?? '').':'.($this->credentials['api_password'] ?? ''));

        return [
            'Authorization' => 'Basic '.$token,
            'Content-Type'  => 'application/json',
        ];
    }

    public function createOrder(OrderData $data): ShipmentResult
    {
        $payload = [
            'plannedShippingDateAndTime' => $this->plannedShippingDate(),
            'pickup'                     => ['isRequested' => false],
            'productCode'                => $this->productCode($data->recipient),
            'accounts'                   => [['typeCode' => 'shipper', 'number' => $this->credentials['account_number'] ?? '']],
            'customerDetails'            => [
                'shipperDetails'  => $this->shipperDetails(),
                'receiverDetails' => $this->receiverDetails($data->recipient),
            ],
            'content' => [
                'packages'          => [$this->package($data->weight)],
                'isCustomsDeclarable' => $this->isCustomsDeclarable($data->recipient),
                'declaredValue'       => max(1, $data->codAmount),
                'declaredValueCurrency' => $this->declaredValueCurrency(),
                'description'         => $data->itemDescription ?? ('Order '.$data->hostOrderReference),
                'incoterm'            => 'DAP',
                'unitOfMeasurement'   => $this->unitOfMeasurement(),
            ],
        ];

        $response = $this->http()->post('/shipments', $payload);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return new ShipmentResult(
            consignmentId: (string) $response->json('shipmentTrackingNumber', ''),
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
        $shipment = $this->trackingShipment($consignmentId);
        $events = (array) ($shipment['events'] ?? []);

        return new ShipmentResult(
            consignmentId: $consignmentId,
            status: $this->statusFromEvents($events, (string) ($shipment['status'] ?? '')),
            raw: $shipment,
        );
    }

    public function trackOrder(string $consignmentId): array
    {
        $shipment = $this->trackingShipment($consignmentId);
        $events = (array) ($shipment['events'] ?? []);

        return array_map(fn (array $event): TrackingEvent => new TrackingEvent(
            status: $this->mapStatus((string) ($event['typeCode'] ?? '')),
            occurredAt: trim(($event['date'] ?? '').' '.($event['time'] ?? '')),
            location: $event['serviceArea'][0]['description'] ?? null,
            description: $event['description'] ?? null,
            raw: $event,
        ), $events);
    }

    public function getDeliveryCharges(QuoteData $data): QuoteResult
    {
        $response = $this->http()->get('/rates', array_filter([
            'accountNumber'          => $this->credentials['account_number'] ?? null,
            'originCountryCode'      => $this->originCountry(),
            'originPostalCode'       => $this->credentials['origin_postal_code'] ?? null,
            'originCityName'         => $this->credentials['origin_city_name'] ?? null,
            'destinationCountryCode' => $data->toCountryCode,
            'destinationPostalCode'  => $data->toPostalCode,
            'destinationCityName'    => $data->toCityName,
            'weight'                 => max(0.1, $data->weight),
            'length'                 => $this->credentials['default_package_length'] ?? null,
            'width'                  => $this->credentials['default_package_width'] ?? null,
            'height'                 => $this->credentials['default_package_height'] ?? null,
            'plannedShippingDate'    => Carbon::now()->addDays($this->offsetDays())->format('Y-m-d'),
            'isCustomsDeclarable'    => $this->isInternational($data->toCountryCode) ? 'true' : 'false',
            'unitOfMeasurement'      => $this->unitOfMeasurement(),
        ], static fn ($value) => $value !== null && $value !== ''));

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $price = (array) ($response->json('products.0.totalPrice.0') ?? []);

        return new QuoteResult(
            totalCost: (float) ($price['price'] ?? 0),
            currency: (string) ($price['priceCurrency'] ?? $this->profile()['currency']),
            raw: (array) $response->json(),
        );
    }

    private function trackingShipment(string $consignmentId): array
    {
        $response = $this->http()->get("/shipments/{$consignmentId}/tracking");

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        return (array) ($response->json('shipments.0') ?? []);
    }

    private function shipperDetails(): array
    {
        return [
            'postalAddress' => array_filter([
                'countryCode'  => (string) $this->originCountry(),
                'postalCode'   => $this->credentials['origin_postal_code'] ?? '',
                'cityName'     => $this->credentials['origin_city_name'] ?? '',
                'addressLine1' => $this->credentials['origin_address_line1'] ?? '',
            ], static fn ($value) => $value !== ''),
            'contactInformation' => array_filter([
                'companyName' => $this->credentials['origin_company_name'] ?? '',
                'fullName'    => $this->credentials['origin_full_name'] ?? '',
                'phone'       => $this->credentials['origin_phone'] ?? '',
                'email'       => $this->credentials['origin_email'] ?? '',
            ], static fn ($value) => $value !== ''),
        ];
    }

    private function receiverDetails(RecipientData $recipient): array
    {
        return [
            'postalAddress' => array_filter([
                'countryCode'  => $recipient->countryCode ?? '',
                'postalCode'   => $recipient->postalCode ?? '',
                'cityName'     => $recipient->cityName ?? '',
                'provinceCode' => $recipient->stateProvince ?? '',
                'addressLine1' => $recipient->address,
            ], static fn ($value) => $value !== ''),
            'contactInformation' => array_filter([
                'companyName' => $recipient->name,
                'fullName'    => $recipient->name,
                'phone'       => $recipient->phone,
            ], static fn ($value) => $value !== ''),
        ];
    }

    private function package(float $weightKg): array
    {
        return [
            'weight'     => max(0.1, $weightKg),
            'dimensions' => [
                'length' => (float) ($this->credentials['default_package_length'] ?? 10),
                'width'  => (float) ($this->credentials['default_package_width'] ?? 10),
                'height' => (float) ($this->credentials['default_package_height'] ?? 10),
            ],
        ];
    }

    private function profile(): array
    {
        return [...self::DEFAULT_PROFILE, ...($this->countryProfiles()[$this->country()] ?? [])];
    }

    private function originCountry(): ?string
    {
        return $this->hasSelectedCountry() ? $this->country() : null;
    }

    private function isInternational(?string $destinationCountryCode): bool
    {
        return $this->originCountry() !== strtoupper((string) $destinationCountryCode);
    }

    private function isCustomsDeclarable(RecipientData $recipient): bool
    {
        return $this->isInternational($recipient->countryCode);
    }

    private function productCode(RecipientData $recipient): string
    {
        $profile = $this->profile();

        return $this->isInternational($recipient->countryCode)
            ? $profile['international_product_code']
            : $profile['domestic_product_code'];
    }

    private function declaredValueCurrency(): string
    {
        return $this->profile()['currency'];
    }

    private function unitOfMeasurement(): string
    {
        return self::UNIT_OF_MEASUREMENT;
    }

    private function offsetDays(): int
    {
        return self::PLANNED_SHIP_OFFSET_DAYS;
    }

    private function plannedShippingDate(): string
    {
        $date = Carbon::now()->addDays($this->offsetDays());

        return $date->format('Y-m-d\T10:00:00').'GMT'.$date->format('P');
    }

    private function statusFromEvents(array $events, string $shipmentStatus): ShipmentStatus
    {
        $latest = $events[0]['typeCode'] ?? null;

        if ($latest !== null) {
            $mapped = $this->mapStatus((string) $latest);
            if ($mapped !== ShipmentStatus::Unknown) {
                return $mapped;
            }
        }

        return $this->mapStatus($shipmentStatus);
    }

    protected function mapStatus(string $raw): ShipmentStatus
    {
        return match (strtolower(trim($raw))) {
            'pu', 'picked up'                                       => ShipmentStatus::PickedUp,
            'af', 'ar', 'pl', 'df', 'dd', 'processed', 'transit'    => ShipmentStatus::InTransit,
            'wc', 'ow', 'with delivery courier'                     => ShipmentStatus::OutForDelivery,
            'ok', 'delivered'                                       => ShipmentStatus::Delivered,
            'oh', 'hp', 'on hold'                                   => ShipmentStatus::OnHold,
            'rt', 'returned'                                        => ShipmentStatus::Returned,
            'sd', 'cd', 'failure', 'exception'                      => ShipmentStatus::Failed,
            default                                                 => ShipmentStatus::Unknown,
        };
    }
}
