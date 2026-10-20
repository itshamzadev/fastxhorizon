<?php

namespace App\Builder;

use App\Models\ShippingAddress;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Modules\Builder\Contracts\LocationProvider as LocationProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\Storefront\AddressDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for storefront location + saved addresses.
 *
 * 6Valley is not zone-based and ships no map polygons (capabilities.location is
 * off), so this collapses to the customer's shipping-address book plus a
 * session-held "selected address" — there is no point-in-zone validation and
 * storefrontZonePolygon() is always null. Addresses map onto the module's
 * AddressDTO; coordinates default to 0 and zoneId to 0 because they don't exist
 * in the 6Valley schema.
 */
class LocationProvider implements LocationProviderContract
{
    private const SESSION_KEY = 'builder.selected_location';

    public function __construct(private StorefrontContext $context)
    {
    }

    public function current(): ?array
    {
        $stored = Session::get(self::SESSION_KEY);
        if ($stored) {
            return $stored;
        }

        return $this->autoResolveFromSavedAddresses();
    }

    public function setCurrent(float $lat, float $lng, string $address, ?int $addressId = null): array
    {
        if ($addressId) {
            $row = $this->ownedAddress($addressId);
            $payload = $this->savedSelectionPayload($row);
        } else {
            $payload = [
                'id'      => null,
                'lat'     => $lat,
                'lng'     => $lng,
                'address' => $address,
                'label'   => null,
                'source'  => 'map',
            ];
        }

        Session::put(self::SESSION_KEY, $payload);
        return $payload;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function savedAddresses(): array
    {
        $customerId = $this->context->getUserId();
        if (!$customerId) {
            return [];
        }

        return ShippingAddress::query()
            ->where('customer_id', $customerId)
            ->where('is_guest', 0)
            ->latest()
            ->get()
            ->map(fn (ShippingAddress $address) => $this->formatAddress($address))
            ->all();
    }

    public function addAddress(array $payload): array
    {
        $customerId = $this->requireAuthenticatedUser();

        $address = new ShippingAddress();
        $address->customer_id = $customerId;
        $address->is_guest = 0;
        $this->fill($address, $payload);
        $address->save();

        return $this->formatAddress($address);
    }

    public function updateAddress(int $addressId, array $payload): array
    {
        $address = $this->ownedAddress($addressId);
        $this->fill($address, $payload, $address);
        $address->save();

        return $this->formatAddress($address);
    }

    public function deleteAddress(int $addressId): void
    {
        $this->ownedAddress($addressId)->delete();

        $stored = Session::get(self::SESSION_KEY);
        if ($stored && (int) ($stored['id'] ?? 0) === $addressId) {
            $this->clear();
        }
    }

    public function storefrontZonePolygon(?StorefrontScope $scope = null): ?array
    {
        // 6Valley is not zone-based.
        return null;
    }

    private function fill(ShippingAddress $address, array $payload, ?ShippingAddress $current = null): void
    {
        $address->address_type = $payload['address_type'] ?? $current?->address_type ?? 'Other';
        $address->contact_person_name = $payload['contact_person_name'] ?? $current?->contact_person_name;
        $address->phone = $payload['contact_person_number'] ?? $payload['phone'] ?? $current?->phone;
        $address->email = $payload['contact_person_email'] ?? $payload['email'] ?? $current?->email;
        $address->address = $payload['address'] ?? $current?->address ?? '';
        $address->city = $payload['city'] ?? $current?->city;
        $address->zip = $payload['zip'] ?? $current?->zip;
        $address->state = $payload['state'] ?? $current?->state;
        $address->country = $payload['country'] ?? $current?->country;
        $address->latitude = $payload['latitude'] ?? $current?->latitude;
        $address->longitude = $payload['longitude'] ?? $current?->longitude;
    }

    private function requireAuthenticatedUser(): int
    {
        $customerId = $this->context->getUserId();
        if (!$customerId) {
            throw ValidationException::withMessages(['auth' => translate('unauthenticated') ?: 'Sign in required']);
        }
        return $customerId;
    }

    private function ownedAddress(int $addressId): ShippingAddress
    {
        $customerId = $this->requireAuthenticatedUser();

        $address = ShippingAddress::query()
            ->where('id', $addressId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$address) {
            throw ValidationException::withMessages(['address_id' => translate('not_found') ?: 'Address not found']);
        }
        return $address;
    }

    private function autoResolveFromSavedAddresses(): ?array
    {
        $customerId = $this->context->getUserId();
        if (!$customerId) {
            return null;
        }

        $address = ShippingAddress::query()
            ->where('customer_id', $customerId)
            ->where('is_guest', 0)
            ->latest()
            ->first();

        if (!$address) {
            return null;
        }

        $payload = $this->savedSelectionPayload($address);
        Session::put(self::SESSION_KEY, $payload);
        return $payload;
    }

    private function savedSelectionPayload(ShippingAddress $row): array
    {
        return [
            'id'                    => (int) $row->id,
            'lat'                   => (float) ($row->latitude ?? 0),
            'lng'                   => (float) ($row->longitude ?? 0),
            'address'               => (string) $row->address,
            'label'                 => $row->address_type ?? 'Other',
            'address_type'          => $row->address_type,
            'contact_person_name'   => $row->contact_person_name,
            'contact_person_number' => $row->phone,
            'source'                => 'saved',
        ];
    }

    private function formatAddress(ShippingAddress $address): array
    {
        return AddressDTO::fromArray([
            'id'                    => (int) $address->id,
            'address_type'          => $address->address_type,
            'address'               => (string) $address->address,
            'latitude'              => (float) ($address->latitude ?? 0),
            'longitude'             => (float) ($address->longitude ?? 0),
            'contact_person_name'   => $address->contact_person_name,
            'contact_person_number' => $address->phone,
            'floor'                 => null,
            'road'                  => null,
            'house'                 => null,
            'zone_id'               => 0,
        ])->toArray();
    }
}
