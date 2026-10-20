<?php

namespace App\Builder;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Builder\Contracts\CouponProvider as CouponProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for storefront coupons.
 *
 * 6Valley has no zones or delivery modules, so coupon scoping collapses to the
 * seller axis. The storefront only surfaces a store's own vendor (self) coupon
 * — admin/platform-wide coupons (seller_id 0/NULL) are never offered or applied
 * here. The eligibility, usage-limit and discount math mirror the host's
 * OrderManager::getTotalCouponAmount so a coupon accepted here behaves
 * identically at checkout.
 */
class CouponProvider implements CouponProviderContract
{
    public function forCustomer(?int $customerId, ?int $storeId): array
    {
        if (!$storeId) {
            return [];
        }

        // No coupon suggestions for guests when guest coupons are disabled.
        if ($customerId === null && !$this->guestCouponAllowed()) {
            return [];
        }

        $sellerId = Shop::query()->where('id', $storeId)->value('seller_id');
        if ($sellerId === null) {
            return [];
        }

        $today = Carbon::today()->toDateString();

        return Coupon::query()
            ->where('status', 1)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('expire_date', '>=', $today)
            ->where(fn (Builder $q) => $this->applySellerScope($q, (int) $sellerId))
            ->where(fn (Builder $q) => $this->applyCustomerScope($q, $customerId))
            ->get()
            // Hide coupons the customer can't actually use (first-order already
            // placed, usage limit reached) so the list only offers coupons that
            // would pass validate() on apply.
            ->filter(fn (Coupon $coupon) => $this->isUsableByCustomer($coupon, $customerId))
            ->map(fn (Coupon $coupon) => $this->toDto($coupon))
            ->values()
            ->all();
    }

    public function validate(string $code, ?int $customerId, ?StorefrontScope $scope, float $cartTotal): array
    {
        // Guest coupon apply is gated by config — reject before any lookup.
        if ($customerId === null && !$this->guestCouponAllowed()) {
            return ['ok' => false, 'error' => translate('please_login_to_apply_a_coupon') ?: 'Please log in to apply a coupon.'];
        }

        $code = trim($code);
        $today = date('Y-m-d');

        $coupon = Coupon::query()
            ->where('code', $code)
            ->where('status', 1)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('expire_date', '>=', $today)
            ->first();

        if (!$coupon) {
            return ['ok' => false, 'error' => translate('invalid_coupon') ?: 'Coupon code not found.'];
        }

        // Customer restriction: 0 means "all customers".
        if ((int) $coupon->customer_id !== 0 && (int) $coupon->customer_id !== (int) $customerId) {
            return ['ok' => false, 'error' => translate('coupon_not_valid') ?: 'This coupon is not available for your account.'];
        }

        if ($coupon->coupon_type === 'first_order') {
            if ($customerId && Order::where('customer_id', $customerId)->count() > 0) {
                return ['ok' => false, 'error' => translate('sorry_this_coupon_is_not_valid_for_this_user') ?: 'This coupon is only valid on your first order.'];
            }
        } elseif ($customerId) {
            $used = Order::where(['customer_id' => $customerId, 'coupon_code' => $coupon->code])
                ->groupBy('order_group_id')
                ->get()
                ->count();
            if ((int) $coupon->limit <= $used) {
                return ['ok' => false, 'error' => translate('sorry_this_coupon_is_not_valid_for_this_user') ?: 'Coupon usage limit exceeded.'];
            }
        }

        // Seller scope: admin coupons apply everywhere; a vendor coupon only at
        // its own shop (scope tenantId is the shop's seller).
        $sellerId = $scope?->tenantId;
        if (!$this->couponMatchesSeller($coupon, $sellerId)) {
            return ['ok' => false, 'error' => translate('coupon_not_applicable_to_selected_vendors') ?: 'This coupon is not valid at this store.'];
        }

        if ((float) $coupon->min_purchase > 0 && $cartTotal < (float) $coupon->min_purchase) {
            return ['ok' => false, 'error' => translate('Minimum_purchase_amount_is') . ' ' . number_format((float) $coupon->min_purchase, 2)];
        }

        $isFreeDelivery = $coupon->coupon_type === 'free_delivery';
        $discount = $isFreeDelivery ? 0.0 : $this->discountAmount($coupon, $cartTotal);

        return [
            'ok'           => true,
            'code'         => (string) $coupon->code,
            'title'        => (string) $coupon->title,
            'discount'     => $discount,
            'freeDelivery' => $isFreeDelivery,
            'couponType'   => (string) $coupon->coupon_type,
        ];
    }

    /**
     * Per-user eligibility for the suggestion list — mirrors the first-order
     * and usage-limit gates in validate() so a listed coupon never fails on
     * apply. Guests (null customer) have already cleared the guestCoupon gate;
     * these order-history checks only apply to signed-in customers.
     */
    private function isUsableByCustomer(Coupon $coupon, ?int $customerId): bool
    {
        if ($customerId === null) {
            return true;
        }

        if ($coupon->coupon_type === 'first_order') {
            return Order::where('customer_id', $customerId)->count() === 0;
        }

        $used = Order::where(['customer_id' => $customerId, 'coupon_code' => $coupon->code])
            ->groupBy('order_group_id')
            ->get()
            ->count();

        return $used < (int) $coupon->limit;
    }

    private function applySellerScope(Builder $q, int $sellerId): void
    {
        // Storefront lists only the store's own vendor (self) coupon; admin /
        // platform-wide coupons (seller_id NULL/0, added_by 'admin') are excluded.
        $q->where('added_by', 'seller')
            ->where('seller_id', $sellerId);
    }

    private function applyCustomerScope(Builder $q, ?int $customerId): void
    {
        $q->where('customer_id', 0);
        if ($customerId !== null) {
            $q->orWhere('customer_id', $customerId);
        }
    }

    private function guestCouponAllowed(): bool
    {
        return (bool) config('builder.capabilities.checkout.guestCoupon', true);
    }

    private function couponMatchesSeller(Coupon $coupon, ?int $sellerId): bool
    {
        // Storefront only honours a store's own vendor (self) coupon; admin /
        // platform-wide coupons are not applicable here.
        if ($sellerId === null) {
            return false;
        }
        return $coupon->added_by === 'seller' && (int) $coupon->seller_id === (int) $sellerId;
    }

    private function discountAmount(Coupon $coupon, float $cartTotal): float
    {
        $discount = (float) $coupon->discount;

        // discount_type is stored as 'percentage' (admin form value); match with a
        // prefix so both 'percent' and 'percentage' resolve to a percentage discount,
        // consistent with the display side and OrderManager. A strict 'percent'
        // check silently fell through to the flat branch, applying e.g. a "10% off"
        // coupon as a flat 10 discount.
        if (\str_starts_with((string) $coupon->discount_type, 'percent')) {
            $amount = ($cartTotal * $discount) / 100;
            $maxDiscount = (float) $coupon->max_discount;
            if ($maxDiscount > 0 && $amount > $maxDiscount) {
                $amount = $maxDiscount;
            }
            return $amount;
        }

        return min($discount, $cartTotal);
    }

    private function toDto(Coupon $coupon): array
    {
        $discountType = $coupon->discount_type;
        // DB stores 'percentage' | 'amount'; a percentage is a rate (never
        // currency-converted), everything else is a money amount.
        $isPercent = \str_starts_with((string) $discountType, 'percent');

        // Convert money amounts from the USD base to the shopper's active
        // currency — the same helper the product cards use — so switching
        // currency updates coupon figures too. Percentage discount stays as-is.
        $discount    = $isPercent ? (float) $coupon->discount : (float) webCurrencyConverterOnlyDigit((float) $coupon->discount);
        $minPurchase = (float) webCurrencyConverterOnlyDigit((float) $coupon->min_purchase);
        $maxDiscount = (float) webCurrencyConverterOnlyDigit((float) $coupon->max_discount);
        $symbol = \App\Utils\currency_symbol() ?: '$';

        // A free-delivery coupon carries no monetary discount, so the generic
        // "$0 Off" label is wrong — surface the waived-shipping benefit instead.
        $benefit = match (true) {
            $coupon->coupon_type === 'free_delivery' => $this->typeLabel('free_delivery'),
            $isPercent => $this->trimNumber($discount) . '% ' . (translate('Off') ?: 'Off'),
            default    => $symbol . $this->trimNumber($discount) . ' ' . (translate('Off') ?: 'Off'),
        };

        $note = null;
        if ($minPurchase > 0) {
            $note = (translate('Min_purchase') ?: 'Min purchase') . ' ' . $symbol . $this->trimNumber($minPurchase);
        } elseif ($isPercent && $maxDiscount > 0) {
            $note = (translate('Max_discount') ?: 'Max discount') . ' ' . $symbol . $this->trimNumber($maxDiscount);
        }

        return [
            'id'            => (int) $coupon->id,
            'code'          => (string) $coupon->code,
            'title'         => (string) $coupon->title,
            'coupon_type'   => $coupon->coupon_type,
            'type_label'    => $this->typeLabel($coupon->coupon_type),
            'discount'      => $discount,
            'discount_type' => $discountType,
            'min_purchase'  => $minPurchase,
            'max_discount'  => $maxDiscount,
            'valid_from'    => $this->formatDate($coupon->start_date),
            'valid_to'      => $this->formatDate($coupon->expire_date),
            'store_name'    => null,
            'benefit'       => $benefit,
            'note'          => $note,
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'first_order'          => translate('First_Order') ?: 'First Order',
            'free_delivery'        => translate('Free_Delivery') ?: 'Free Delivery',
            'discount_on_purchase' => translate('Discount') ?: 'Discount',
            default                => translate('Special_Offer') ?: 'Special Offer',
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
