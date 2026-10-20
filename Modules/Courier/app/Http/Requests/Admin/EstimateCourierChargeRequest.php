<?php

namespace Modules\Courier\app\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\Services\ProviderRegistry;

class EstimateCourierChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'      => ['required', 'string', $this->providerMustBeEnabled()],
            'store_id'      => ['nullable', 'string'],
            'weight'        => ['required', 'numeric', 'min:0'],
            'cod_amount'    => ['nullable', 'numeric', 'min:0'],
            'city_id'       => ['nullable', 'string'],
            'zone_id'       => ['nullable', 'string'],
            'area_id'       => ['nullable', 'string'],
            'country_code'  => ['nullable', 'string', 'max:2'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'city_name'     => ['nullable', 'string', 'max:100'],
            'latitude'      => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'     => ['nullable', 'numeric', 'between:-180,180'],
            'source_branch' => ['nullable', 'string', 'max:100'],
            'source_branch_id' => ['nullable', 'string', 'max:100'],
            'destination_branch' => ['nullable', 'string', 'max:100'],
            'destination_branch_id' => ['nullable', 'string', 'max:100'],
            'recipient_address' => ['nullable', 'string', 'max:220'],
            'delivery_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'provider'           => translate('Delivery_Partner'),
            'store_id'           => translate('Pickup_Store'),
            'weight'             => translate('Parcel_Weight'),
            'cod_amount'         => translate('Cash_to_Collect'),
            'city_id'            => $this->locationLabel(level: 'city', fallback: 'Delivery_City'),
            'zone_id'            => $this->locationLabel(level: 'zone', fallback: 'Delivery_Zone'),
            'area_id'            => $this->locationLabel(level: 'area', fallback: 'Delivery_Area'),
            'country_code'       => translate('Delivery_Country'),
            'postal_code'        => translate('Delivery_Postal_Code'),
            'city_name'          => translate('Delivery_City'),
            'latitude'           => translate('Delivery_Point_Latitude'),
            'longitude'          => translate('Delivery_Point_Longitude'),
            'source_branch'      => translate('Pickup_Branch'),
            'destination_branch' => translate('Delivery_Branch'),
            'recipient_address'  => translate('Delivery_Address'),
            'delivery_type'      => translate('Delivery_Service'),
        ];
    }

    private function locationLabel(string $level, string $fallback): string
    {
        $registry = app(ProviderRegistry::class);
        $provider = (string) $this->input('provider');
        $labels = $registry->has($provider) ? $registry->driver($provider)->locationLabels() : [];

        return translate($labels[$level] ?? $fallback);
    }

    public function selectedProvider(): string
    {
        return (string) $this->input('provider');
    }

    public function toQuoteData(): QuoteData
    {
        return new QuoteData(
            weight: (float) $this->input('weight'),
            codAmount: (float) ($this->input('cod_amount') ?? 0),
            toCityId: $this->input('city_id'),
            toZoneId: $this->input('zone_id'),
            toAreaId: $this->input('area_id'),
            deliveryType: $this->filled('delivery_type') ? (string) $this->input('delivery_type') : null,
            toCountryCode: $this->input('country_code'),
            toPostalCode: $this->input('postal_code'),
            toCityName: $this->input('city_name'),
            toLatitude: $this->input('latitude'),
            toLongitude: $this->input('longitude'),
            sourceBranchName: $this->input('source_branch'),
            sourceBranchId: $this->input('source_branch_id'),
            destinationBranchName: $this->input('destination_branch'),
            destinationBranchId: $this->input('destination_branch_id'),
            meta: array_filter([
                'store_id' => $this->filled('store_id') ? (string) $this->input('store_id') : null,
                'address'  => $this->filled('recipient_address') ? (string) $this->input('recipient_address') : null,
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
