<?php

namespace App\Builder;

use App\Models\Wishlist;
use Illuminate\Validation\ValidationException;
use Modules\Builder\Contracts\WishlistProvider as WishlistProviderContract;
use Modules\Builder\Services\StorefrontContext;

class WishlistProvider implements WishlistProviderContract
{
    public function __construct(private StorefrontContext $context)
    {
    }

    public function toggle(int $itemId): array
    {
        $customerId = $this->requireCustomerId();

        $existing = Wishlist::query()
            ->where('customer_id', $customerId)
            ->where('product_id', $itemId)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            Wishlist::create([
                'customer_id' => $customerId,
                'product_id'  => $itemId,
            ]);
            $inWishlist = true;
        }

        return [
            'inWishlist' => $inWishlist,
            'count'      => $this->countFor($customerId),
        ];
    }

    public function count(): int
    {
        $customerId = $this->context->getUserId();
        return $customerId ? $this->countFor($customerId) : 0;
    }

    public function has(int $itemId): bool
    {
        $customerId = $this->context->getUserId();
        if (!$customerId) {
            return false;
        }
        return Wishlist::query()
            ->where('customer_id', $customerId)
            ->where('product_id', $itemId)
            ->exists();
    }

    private function countFor(int $customerId): int
    {
        // Keep the wishlist badge consistent with the (shop-scoped) wishlist
        // page: on a vendor storefront count only this shop's saved items, so
        // other vendors' wishlisted products don't inflate the count. Ownership
        // is added_by + user_id (products.shop_id is not reliable — mirrors
        // App\Builder\ItemProvider::applyShopScope()).
        $query = Wishlist::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('product_id');

        if ($this->context->hasScope()) {
            $vendorId = $this->context->getVendorId();
            $query->whereHas('product', function ($product) use ($vendorId) {
                $vendorId
                    ? $product->where('added_by', 'seller')->where('user_id', $vendorId)
                    : $product->where('added_by', 'admin');
            });
        }

        return $query->count();
    }

    private function requireCustomerId(): int
    {
        $customerId = $this->context->getUserId();
        if (!$customerId) {
            throw ValidationException::withMessages([
                '_form' => translate('Sign_in_required') ?: 'Sign in required',
            ]);
        }
        return $customerId;
    }
}
