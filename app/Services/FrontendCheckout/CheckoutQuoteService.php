<?php

namespace App\Services\FrontendCheckout;

use App\Models\Cart;
use App\Models\CartShipping;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\ShippingMethod;
use App\Models\OfflinePaymentMethod;
use App\Utils\CartManager;
use App\Utils\Helpers;
use App\Utils\OrderManager;
use App\Services\FastXHorizonCurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutQuoteService
{
    public function quote(Request $request): array
    {
        $customerId = (int) $request->user()->id;
        $addressId = $this->validateAddress($customerId, $request->input('address_id'), 'Shipping address is required.');
        $billingAddressId = $request->filled('billing_address_id')
            ? $this->validateAddress($customerId, $request->integer('billing_address_id'), 'Billing address is invalid.')
            : null;

        $carts = $this->cart($request);
        if ($carts->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $this->validateStock($carts);
        $this->applyShippingSelections($request, $carts);

        $couponCode = trim((string) $request->input('coupon_code', ''));
        $processed = OrderManager::processOrderGenerateData([
            'coupon_code' => $couponCode,
            'address_id' => $addressId,
            'billing_address_id' => $billingAddressId,
            'requestObj' => $request,
        ]);

        $minimum = OrderManager::verifyCartListMinimumOrderAmount($request);
        $items = [];
        $subtotal = $productDiscount = $couponDiscount = $shipping = $tax = $grandTotal = 0.0;

        foreach ($processed as $group) {
            $couponDiscount += (float) ($group['coupon_discount'] ?? 0);
            $shipping += (float) ($group['shipping_cost'] ?? 0);
            $tax += (float) ($group['total_tax_amount'] ?? 0);
            $grandTotal += (float) ($group['order_amount_with_tax'] ?? 0) - (float) ($group['refer_and_earn_discount'] ?? 0);

            foreach ($group['cart_list'] as $item) {
                $unitDiscount = (float) ($item->discount ?? 0);
                $subtotal += (float) $item->price * (int) $item->quantity;
                $productDiscount += $unitDiscount * (int) $item->quantity;
                $items[] = [
                    'cart_id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'name' => (string) ($item->product->name ?? $item->name),
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->price,
                    'unit_discount' => $unitDiscount,
                    'product_type' => (string) $item->product_type,
                    'seller_id' => (int) $item->seller_id,
                    'seller_is' => (string) $item->seller_is,
                ];
            }
        }

        $paymentMethods = [['code' => 'cash_on_delivery', 'label' => 'Cash on Delivery']];
        $offline = getWebConfig(name: 'offline_payment');
        if ((int) ($offline['status'] ?? 0) === 1) {
            $paymentMethods = array_merge($paymentMethods, OfflinePaymentMethod::where('status', 1)->get(['id', 'method_name'])->map(fn ($method) => [
                'code' => 'offline_payment',
                'id' => (int) $method->id,
                'label' => (string) $method->method_name,
            ])->all());
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'product_discount' => round($productDiscount, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'shipping_total' => round($shipping, 2),
            'tax_total' => round($tax, 2),
            'grand_total' => round($grandTotal, 2),
            'currency' => (string) app(FastXHorizonCurrencyService::class)->baseCode(),
            'shipping_options' => $this->shippingOptions($carts),
            'selected_shipping' => CartShipping::whereIn('cart_group_id', $carts->pluck('cart_group_id')->unique())->get(['cart_group_id', 'shipping_method_id', 'shipping_cost'])->values()->all(),
            'applied_coupon' => $couponCode !== '' ? ['code' => $couponCode, 'discount' => round($couponDiscount, 2)] : null,
            'payment_methods' => $paymentMethods,
            'inventory_status' => ['valid' => true],
            'warnings' => $minimum['status'] ? [] : ($minimum['messages'] ?: ['Minimum order amount requirement was not met.']),
            'can_place_order' => (bool) $minimum['status'],
        ];
    }

    public function cart(Request $request)
    {
        return Cart::query()->with(['product' => fn ($q) => $q->active()])
            ->where('customer_id', $request->user()->id)
            ->where('is_guest', 0)
            ->where('is_checked', 1)
            ->whereHas('product', fn ($q) => $q->active())
            ->lockForUpdate()
            ->get();
    }

    public function validateStock($carts): void
    {
        foreach ($carts as $cart) {
            $product = $cart->product;
            if (!$product) {
                throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
            }
            if ($product->product_type === 'digital') {
                continue;
            }
            $variations = json_decode((string) $product->variation, true) ?: [];
            if ($cart->variant && count($variations)) {
                $variant = collect($variations)->firstWhere('type', $cart->variant);
                if (!$variant || (int) ($variant['qty'] ?? 0) < (int) $cart->quantity) {
                    throw ValidationException::withMessages(['stock' => "Insufficient stock for {$product->name}."]);
                }
            } elseif ((int) $product->current_stock < (int) $cart->quantity) {
                throw ValidationException::withMessages(['stock' => "Insufficient stock for {$product->name}."]);
            }
        }
    }

    public function shippingOptions($carts): array
    {
        return $carts->groupBy('cart_group_id')->map(function ($group, $groupId) {
            $first = $group->first();
            $type = $first->seller_is === 'admin' ? 'admin' : 'seller';
            return [
                'cart_group_id' => (string) $groupId,
                'seller_id' => (int) $first->seller_id,
                'seller_is' => (string) $first->seller_is,
                'methods' => Helpers::getShippingMethods($first->seller_id, $type)->map(fn ($method) => [
                    'id' => (int) $method->id,
                    'title' => (string) $method->title,
                    'cost' => (float) $method->cost,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    public function applyShippingSelections(Request $request, $carts): void
    {
        $selections = $request->input('shipping', []);
        if (!is_array($selections) || !$selections) {
            return;
        }

        $groups = $carts->groupBy('cart_group_id');
        foreach ($selections as $selection) {
            $groupId = (string) ($selection['cart_group_id'] ?? '');
            $methodId = (int) ($selection['shipping_method_id'] ?? 0);
            $group = $groups->get($groupId);
            if (!$group || !$methodId) {
                throw ValidationException::withMessages(['shipping' => 'Invalid shipping selection.']);
            }
            $first = $group->first();
            $method = ShippingMethod::query()->where('id', $methodId)->where('status', 1)->first();
            $allowed = $method && (($first->seller_is === 'admin' && $method->creator_type === 'admin') || ($first->seller_is !== 'admin' && $method->creator_id == $first->seller_id && $method->creator_type === 'seller'));
            if (!$allowed) {
                throw ValidationException::withMessages(['shipping' => 'Selected shipping method is unavailable.']);
            }
            CartShipping::updateOrCreate(['cart_group_id' => $groupId], ['shipping_method_id' => $method->id, 'shipping_cost' => $method->cost]);
        }
    }

    private function validateAddress(int $customerId, ?int $addressId, string $message): int
    {
        if (!$addressId || !ShippingAddress::where(['id' => $addressId, 'customer_id' => $customerId, 'is_guest' => 0])->exists()) {
            throw ValidationException::withMessages(['address_id' => $message]);
        }
        return $addressId;
    }
}
