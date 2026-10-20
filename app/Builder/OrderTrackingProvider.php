<?php

namespace App\Builder;

use App\Models\Order;
use Modules\Builder\Contracts\OrderTrackingProvider as OrderTrackingProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for storefront order tracking.
 *
 * Authenticated lookups match (customer_id, is_guest = 0); guest lookups match
 * the phone captured in the order's `shipping_address_data` with is_guest = 1.
 * The DTO mapping is delegated to OrderProvider::formatOrder so the tracking
 * page and the profile order-details page render through one transformer.
 */
class OrderTrackingProvider implements OrderTrackingProviderContract
{
    public function __construct(private OrderProvider $orderFormatter)
    {
    }

    public function track(
        ?StorefrontScope $scope,
        int $orderId,
        ?string $contactNumber,
        ?int $customerId,
    ): ?array {
        $normalizedPhone = $contactNumber ? trim($contactNumber) : null;

        // Each 6Valley order is per-seller. On a vendor storefront restrict the
        // lookup to this shop's orders so a customer can't track an order placed
        // with another vendor from here. Global marketplace (no scope) is unrestricted.
        $vendorId = $scope?->tenantId;

        $order = Order::query()
            ->with(['details.product', 'details.productAllStatus', 'deliveryMan', 'seller.shop'])
            ->where('id', $orderId)
            ->when($vendorId, fn ($q) => $q->where('seller_id', $vendorId)->where('seller_is', 'seller'))
            ->when(
                $customerId,
                fn ($q) => $q->where('customer_id', $customerId)->where('is_guest', 0),
                fn ($q) => $q->where('is_guest', 1)->where(function ($inner) use ($normalizedPhone) {
                    $inner->whereJsonContains('shipping_address_data->phone', $normalizedPhone)
                        ->orWhereJsonContains('shipping_address_data->contact_person_number', $normalizedPhone);
                }),
            )
            ->first();

        return $order ? $this->orderFormatter->formatOrder($order) : null;
    }
}
