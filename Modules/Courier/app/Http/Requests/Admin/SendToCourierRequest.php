<?php

namespace Modules\Courier\app\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\RecipientData;
use Modules\Courier\app\Services\ProviderRegistry;
use Modules\Courier\CourierProviders\CourierProvider;

class SendToCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'              => ['required', 'string', $this->providerMustBeEnabled()],
            'replace_existing'      => ['nullable', 'boolean'],
            'host_order_reference'  => ['required', 'string'],
            'store_id'              => ['nullable', 'string'],
            'recipient_name'        => [$this->presence('recipient_name'), 'string', 'max:100'],
            'recipient_phone'       => [$this->presence('recipient_phone'), 'string', 'max:20'],
            'recipient_address'     => [$this->presence('recipient_address'), 'string', 'max:220'],
            'city_id'               => [$this->presence('city_id'), 'string'],
            'zone_id'               => [$this->presence('zone_id'), 'string'],
            'area_id'               => [$this->presence('area_id'), 'string'],
            'country_code'          => [$this->presence('country_code'), 'string', 'max:2'],
            'postal_code'           => [$this->presence('postal_code'), 'string', 'max:20'],
            'city_name'             => [$this->presence('city_name'), 'string', 'max:100'],
            'state_province'        => ['nullable', 'string', 'max:100'],
            'latitude'              => [$this->presence('latitude'), 'numeric', 'between:-90,90'],
            'longitude'             => [$this->presence('longitude'), 'numeric', 'between:-180,180'],
            'source_branch'         => [$this->presence('source_branch'), 'string', 'max:100'],
            'source_branch_id'      => ['nullable', 'string', 'max:100'],
            'destination_branch'    => [$this->presence('destination_branch'), 'string', 'max:100'],
            'destination_branch_id' => ['nullable', 'string', 'max:100'],
            'weight'                => [$this->presence('weight'), 'numeric', 'min:0'],
            'quantity'              => ['nullable', 'integer', 'min:1'],
            'cod_amount'            => ['required', 'numeric', 'min:0'],
            'order_value'           => ['nullable', 'numeric', 'min:0'],
            'item_description'      => ['nullable', 'string'],
            'delivery_type'         => ['nullable', 'string', 'max:50'],
            'note'                  => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'provider'             => translate('Delivery_Partner'),
            'host_order_reference' => translate('Order'),
            'store_id'             => translate('Pickup_Store'),
            'recipient_name'       => translate('Receivers_Name'),
            'recipient_phone'      => translate('Receivers_Phone'),
            'recipient_address'    => translate('Delivery_Address'),
            'city_id'              => $this->locationLabel(level: 'city', fallback: 'Delivery_City'),
            'zone_id'              => $this->locationLabel(level: 'zone', fallback: 'Delivery_Zone'),
            'area_id'              => $this->locationLabel(level: 'area', fallback: 'Delivery_Area'),
            'country_code'         => translate('Delivery_Country'),
            'postal_code'          => translate('Delivery_Postal_Code'),
            'city_name'            => translate('Delivery_City'),
            'state_province'       => translate('State_or_Province'),
            'latitude'             => translate('Delivery_Point_Latitude'),
            'longitude'            => translate('Delivery_Point_Longitude'),
            'source_branch'        => translate('Pickup_Branch'),
            'destination_branch'   => translate('Delivery_Branch'),
            'weight'               => translate('Parcel_Weight'),
            'quantity'             => translate('Number_of_Items'),
            'cod_amount'           => translate('Cash_to_Collect'),
            'order_value'          => translate('Order_Value'),
            'item_description'     => translate('Parcel_Contents'),
            'delivery_type'        => translate('Delivery_Service'),
            'note'                 => translate('Rider_Instructions'),
        ];
    }

    private function locationLabel(string $level, string $fallback): string
    {
        $labels = $this->driver()?->locationLabels() ?? [];

        return translate($labels[$level] ?? $fallback);
    }

    private function presence(string $field): string
    {
        return in_array($field, $this->requiredFields(), true) ? 'required' : 'nullable';
    }

    private function requiredFields(): array
    {
        return $this->driver()?->requiredFields() ?? [];
    }

    private function driver(): ?CourierProvider
    {
        $registry = app(ProviderRegistry::class);
        $provider = (string) $this->input('provider');

        return $registry->has($provider) ? $registry->driver($provider) : null;
    }

    public function selectedProvider(): string
    {
        return (string) $this->input('provider');
    }

    public function replacesExistingShipment(): bool
    {
        return $this->boolean('replace_existing');
    }

    public function toOrderData(): OrderData
    {
        return new OrderData(
            hostOrderReference: (string) $this->input('host_order_reference'),
            providerStoreId: $this->filled('store_id') ? (string) $this->input('store_id') : null,
            recipient: new RecipientData(
                name: (string) $this->input('recipient_name'),
                phone: (string) $this->input('recipient_phone'),
                address: (string) $this->input('recipient_address'),
                cityId: $this->input('city_id'),
                zoneId: $this->input('zone_id'),
                areaId: $this->input('area_id'),
                countryCode: $this->input('country_code'),
                postalCode: $this->input('postal_code'),
                cityName: $this->input('city_name'),
                stateProvince: $this->input('state_province'),
                latitude: $this->input('latitude'),
                longitude: $this->input('longitude'),
                sourceBranchName: $this->input('source_branch'),
                sourceBranchId: $this->input('source_branch_id'),
                destinationBranchName: $this->input('destination_branch'),
                destinationBranchId: $this->input('destination_branch_id'),
            ),
            codAmount: (float) $this->input('cod_amount'),
            weight: (float) $this->input('weight'),
            note: $this->input('note'),
            itemDescription: $this->input('item_description'),
            quantity: (int) ($this->input('quantity') ?? 1),
            meta: array_filter([
                'delivery_type' => $this->filled('delivery_type') ? (string) $this->input('delivery_type') : null,
                'order_value'   => $this->filled('order_value') ? (float) $this->input('order_value') : null,
            ], static fn ($value) => $value !== null),
        );
    }

    private function providerMustBeEnabled(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (!app(ProviderRegistry::class)->isProviderEnabled((string) $value)) {
                $fail(translate('the_selected_delivery_partner_is_not_enabled'));
            }
        };
    }
}
