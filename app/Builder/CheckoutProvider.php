<?php

namespace App\Builder;

use App\Http\Controllers\Customer\PaymentController;
use App\Models\Cart;
use App\Models\CartShipping;
use App\Models\OfflinePaymentMethod;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\ShippingMethod;
use App\Models\ShippingType;
use App\Models\Shop;
use App\Models\User;
use App\Utils\CartManager;
use App\Utils\Helpers;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\CheckoutProvider as CheckoutProviderContract;
use Modules\Builder\Contracts\CouponProvider;
use Modules\Builder\Contracts\PaymentMethodProvider;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\Storefront\CheckoutQuoteDTO;
use Modules\Builder\ValueObjects\Storefront\CheckoutSnapshotDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for storefront checkout.
 *
 * 6Valley is a shipping-based e-commerce marketplace, so the food-delivery axes
 * of the contract collapse: only 'delivery' is offered (no pickup/schedule), and
 * there are no DM tips, extra packaging, distance or schedule slots. Pricing is
 * derived from the host cart (per-category shipping + tax) and the CouponProvider.
 * placeOrder delegates to OrderManager::generateOrder — the same pipeline the
 * legacy site and mobile API use — for cash-on-delivery and offline payment.
 * Digital gateways hand off to the host payment-request pipeline
 * (PaymentController::getCustomerPaymentRequest → generate_link) and return a
 * redirect URL; the paid order is created by the digital_payment_success hook
 * once the gateway confirms payment. Wallet payment remains gated (config-off).
 */
class CheckoutProvider implements CheckoutProviderContract
{
    private const TIP_PRESETS = [];

    public function __construct(
        private StorefrontContext $context,
        private PaymentMethodProvider $paymentMethods,
        private CouponProvider $coupons,
    ) {
    }

    public function snapshot(?StorefrontScope $scope, ?int $customerId): CheckoutSnapshotDTO
    {
        $shop = $scope?->subTenantId ? Shop::find($scope->subTenantId) : null;

        $walletEnabled = (bool) config('builder.wallet_features_enabled', false) && (int) getWebConfig(name: 'wallet_status') === 1;
        $walletBalance = $customerId ? $this->money((float) (User::where('id', $customerId)->value('wallet_balance') ?? 0)) : 0.0;

        // A digital-only cart has nothing to deliver, so cash-on-delivery and
        // offline payment don't apply — the order must be paid online. Hide both
        // so only digital gateways remain selectable.
        $hasPhysical = $this->cartHasPhysical($this->scopeCartToVendor($scope));

        $freeDeliveryOver = $this->freeDeliveryThreshold($shop);

        return CheckoutSnapshotDTO::fromArray([
            'store' => [
                'id'               => (int) ($shop->id ?? 0),
                'name'             => $shop->name ?? getWebConfig(name: 'company_name'),
                'open'             => $this->storeOpen($shop),
                'minOrder'         => $this->money((float) (getWebConfig(name: 'minimum_order_amount') ?? 0)),
                'freeDeliveryOver' => $freeDeliveryOver === null ? null : $this->money($freeDeliveryOver),
            ],
            'deliveryTypes' => [
                'delivery'             => true,
                'pickup'               => false,
                'schedule'             => false,
                'scheduleSlotDuration' => 0,
            ],
            'paymentMethods' => [
                'cod'      => ['enabled' => $hasPhysical && (int) getWebConfig(name: 'cash_on_delivery') === 1, 'allowChangeAmount' => true],
                'offline'  => ['enabled' => $hasPhysical && (int) (getWebConfig(name: 'offline_payment')['status'] ?? 0) === 1, 'methods' => $this->offlineMethods()],
                'gateways' => $this->gatewayMethods(),
                'partial'  => ['enabled' => false, 'method' => null],
                'wallet'   => ['enabled' => $walletEnabled, 'balance' => $walletBalance],
            ],
            'features' => [
                'tipsEnabled'      => false,
                'additionalCharge' => ['enabled' => false, 'name' => '', 'amount' => 0.0],
                'extraPackaging'   => ['enabled' => false, 'fee' => 0.0],
                'taxIncluded'      => (int) getWebConfig(name: 'tax_included') === 1,
            ],
            'tipPresets'    => self::TIP_PRESETS,
            'mostTipped'    => null,
            'scheduleSlots' => [],
        ]);
    }

    public function quote(?StorefrontScope $scope, ?int $customerId, array $state): CheckoutQuoteDTO
    {
        $cartType = $this->scopeCartToVendor($scope);
        $carts = CartManager::getCartListQuery(type: $cartType);

        $itemPrice = 0.0;
        $itemDiscount = 0.0;
        foreach ($carts as $cart) {
            $itemPrice += (float) $cart['price'] * (int) $cart['quantity'];
            $itemDiscount += (float) $cart['discount'] * (int) $cart['quantity'];
        }

        $this->ensureOrderWiseShippingSelected($cartType);
        $deliveryFee = (float) CartManager::get_shipping_cost(type: $cartType);
        $subtotalAfterDiscount = max(0.0, $itemPrice - $itemDiscount);

        [$couponCode, $couponTitle, $couponDiscount, $couponError, $couponFreeDelivery] = $this->resolveCoupon($state, $customerId, $scope, $subtotalAfterDiscount);

        // A free-delivery coupon waives the shipping fee — mirror OrderManager,
        // which zeros the delivery charge at order generation. Zero it before tax
        // and the total so the quote display matches the amount actually charged.
        if ($couponFreeDelivery) {
            $deliveryFee = 0.0;
        }

        // Free-delivery-over-amount: when the cart reaches the admin/seller
        // threshold the shipping charge is waived (per cart group). Compute it
        // from the host's own engine so the storefront matches the legacy site,
        // and waive before tax so the shipping tax drops with the fee.
        $freeDelivery = $this->freeDeliveryState($cartType);
        $thresholdFreeDelivery = !$couponFreeDelivery && $freeDelivery['shippingSaved'] > 0;
        if ($thresholdFreeDelivery) {
            $deliveryFee = max(0.0, $deliveryFee - $freeDelivery['shippingSaved']);
        }

        // Tax honours the active TaxModule mode (order/product/category-wise);
        // the legacy per-row carts.tax only reflects the old product.tax field
        // and reads 0 for order/category-wise, so VAT never showed.
        [$tax, $taxIncluded] = $this->calculateTax($carts, $couponDiscount, $deliveryFee);

        // Everything above is computed in the system default currency; the total
        // is derived first, then each figure is converted for display so the
        // storefront honours the multi-currency setup (mirrors ItemPricing).
        $total = max(0.0, $subtotalAfterDiscount - $couponDiscount + $tax + $deliveryFee);

        // Customer-facing fulfilment prompts (minimum order amount / quantity).
        // Both reuse the host verifiers so the progress/notes match the gates
        // enforced at placeOrder; they carry no pricing, purely informational.
        $minOrder = $this->minOrderState($cartType);
        $minQty = $this->minQtyState();

        return CheckoutQuoteDTO::fromArray([
            'itemPrice'        => $this->money($itemPrice),
            'itemDiscount'     => $this->money($itemDiscount),
            'couponCode'       => $couponCode,
            'couponTitle'      => $couponTitle,
            'couponDiscount'   => $this->money($couponDiscount),
            'couponError'      => $couponError,
            'tax'              => $this->money($tax),
            'taxIncluded'      => $taxIncluded,
            'taxEnabled'       => $this->taxEnabled(),
            'deliveryFee'      => $this->money($deliveryFee),
            'deliveryFeeNote'  => null,
            'additionalCharge' => 0.0,
            'dmTip'            => 0.0,
            'extraPackaging'   => 0.0,
            'distance'         => null,
            'total'            => $this->money($total),
            'cashback'         => null,
            'freeDelivery'     => [
                'active'       => $deliveryFee <= 0,
                'reason'       => $couponFreeDelivery ? 'coupon' : ($thresholdFreeDelivery ? 'threshold' : null),
                // Progress fields let the storefront show an "add X more for free
                // delivery" prompt; enabled is the admin/seller feature flag.
                'enabled'      => $freeDelivery['status'],
                'threshold'    => $this->money($freeDelivery['threshold']),
                'amountNeeded' => $this->money($freeDelivery['amountNeeded']),
                'progress'     => $freeDelivery['progress'],
                'qualified'    => $thresholdFreeDelivery,
            ],
            // Minimum-order-amount gate: threshold/current/amountNeeded drive a
            // progress prompt; met flips the block off once the cart clears it.
            'minOrder'         => [
                'enabled'      => $minOrder['status'],
                'met'          => $minOrder['met'],
                'threshold'    => $this->money($minOrder['threshold']),
                'current'      => $this->money($minOrder['current']),
                'amountNeeded' => $this->money($minOrder['amountNeeded']),
                'progress'     => $minOrder['progress'],
            ],
            // Minimum-order-quantity gate: per-product, so there's no single
            // progress — just a met flag plus the first offending product note.
            'minQty'           => [
                'met'     => $minQty['met'],
                'message' => $minQty['message'],
            ],
        ]);
    }

    /**
     * Aggregate free-delivery-over-amount status across the cart's groups using
     * the host engine (OrderManager::getFreeDeliveryOrderAmountArray), which
     * honours the admin/seller responsibility split and per-group thresholds.
     * shippingSaved is the shipping actually waived (groups that reached 100%);
     * threshold/amountNeeded/progress drive the storefront progress prompt.
     *
     * @return array{status:bool,threshold:float,amountNeeded:float,progress:int,shippingSaved:float}
     */
    private function freeDeliveryState(?string $cartType): array
    {
        $empty = ['status' => false, 'threshold' => 0.0, 'amountNeeded' => 0.0, 'progress' => 0, 'shippingSaved' => 0.0];

        if ((int) (getWebConfig(name: 'free_delivery_status') ?? 0) !== 1) {
            return $empty;
        }

        $groupIds = CartManager::get_cart_group_ids(type: $cartType === 'checked' ? 'checked' : null);
        if ($groupIds === []) {
            return $empty;
        }

        $configured = false;
        $threshold = 0.0;
        $amountNeeded = 0.0;
        $shippingSaved = 0.0;
        $progress = 100;

        foreach ($groupIds as $groupId) {
            $group = OrderManager::getFreeDeliveryOrderAmountArray($groupId);
            if ((int) ($group['status'] ?? 0) !== 1) {
                continue;
            }

            $configured = true;
            $threshold = max($threshold, (float) ($group['amount'] ?? 0));
            $amountNeeded += max(0.0, (float) ($group['amount_need'] ?? 0));
            $shippingSaved += (float) ($group['shipping_cost_saved'] ?? 0);
            $progress = min($progress, (int) ($group['percentage'] ?? 0));
        }

        if (!$configured) {
            return $empty;
        }

        return [
            'status'        => true,
            'threshold'     => $threshold,
            'amountNeeded'  => $amountNeeded,
            'progress'      => $progress,
            'shippingSaved' => $shippingSaved,
        ];
    }

    /**
     * Minimum-order-amount progress across the cart's groups, mirroring
     * OrderManager::verifyCartListMinimumOrderAmount (per-group thresholds honouring
     * the admin/seller responsibility split). Aggregated like freeDeliveryState so
     * the storefront can show an "add X more to place your order" prompt: threshold
     * is the largest per-group minimum, amountNeeded the summed shortfall, progress
     * the least-complete group, and met is true only when every group clears its
     * minimum. All amounts are in the system default currency (converted by caller).
     *
     * @return array{status:bool,threshold:float,current:float,amountNeeded:float,progress:int,met:bool}
     */
    private function minOrderState(?string $cartType): array
    {
        $empty = ['status' => false, 'threshold' => 0.0, 'current' => 0.0, 'amountNeeded' => 0.0, 'progress' => 100, 'met' => true];

        if ((int) (getWebConfig(name: 'minimum_order_amount_status') ?? 0) !== 1) {
            return $empty;
        }

        $groupIds = CartManager::get_cart_group_ids(type: $cartType === 'checked' ? 'checked' : null);
        if ($groupIds === []) {
            return $empty;
        }

        $inhouseMin = (float) (getWebConfig(name: 'minimum_order_amount') ?? 0);
        $bySeller = (int) (getWebConfig(name: 'minimum_order_amount_by_seller') ?? 0) === 1;

        $configured = false;
        $threshold = 0.0;
        $current = 0.0;
        $amountNeeded = 0.0;
        $progress = 100;

        foreach ($groupIds as $groupId) {
            $firstItem = Cart::with('seller')
                ->where('cart_group_id', $groupId)
                ->where('is_checked', 1)
                ->first();
            if (!$firstItem) {
                continue;
            }

            $groupMin = $firstItem->seller_is === 'admin'
                ? $inhouseMin
                : ($bySeller ? (float) ($firstItem->seller->minimum_order_amount ?? 0) : 0.0);
            if ($groupMin <= 0) {
                continue;
            }

            $shipping = (float) CartManager::get_shipping_cost(groupId: $groupId, type: 'checked');
            $groupAmount = max(0.0, (float) CartManager::cart_grand_total(cartGroupId: $groupId, type: 'checked') - $shipping);

            $configured = true;
            $threshold = max($threshold, $groupMin);
            $current += $groupAmount;
            $amountNeeded += max(0.0, $groupMin - $groupAmount);
            $progress = min($progress, (int) min(100, floor($groupAmount / $groupMin * 100)));
        }

        if (!$configured) {
            return $empty;
        }

        return [
            'status'       => true,
            'threshold'    => $threshold,
            'current'      => $current,
            'amountNeeded' => $amountNeeded,
            'progress'     => $progress,
            'met'          => $amountNeeded <= 0,
        ];
    }

    /**
     * Minimum-order-quantity gate, mirroring OrderManager::validateStockAvailability
     * (per-product minimum_order_qty). Returns met + the first offending product's
     * message so the storefront can warn the customer before they hit the
     * place-order block.
     *
     * @return array{met:bool,message:?string}
     */
    private function minQtyState(): array
    {
        $result = OrderManager::validateStockAvailability(request());
        $met = (int) ($result['status'] ?? 1) === 1;

        return [
            'met'     => $met,
            'message' => $met ? null : ($result['message'] ?? null),
        ];
    }

    /**
     * Enforce the two per-checkout business rules and return the first blocking
     * message (null when the cart passes). Delegates to the host verifiers so the
     * storefront applies the exact same thresholds as the legacy site/API:
     *   - minimum order amount  → OrderManager::verifyCartListMinimumOrderAmount
     *   - minimum order quantity → OrderManager::validateStockAvailability
     */
    private function orderRuleBlocker(int $shopperId, int $isGuestFlag): ?string
    {
        $request = request();
        if ($isGuestFlag === 1) {
            $request->merge(['guest_id' => $shopperId, 'is_guest' => 1]);
        }

        $minimumAmount = OrderManager::verifyCartListMinimumOrderAmount($request);
        if ((int) ($minimumAmount['status'] ?? 1) === 0) {
            $messages = array_filter($minimumAmount['messages'] ?? []);
            return $messages !== []
                ? implode(' ', $messages)
                : (translate('Please_complete_minimum_Order_Amount') ?: 'Minimum order amount is not met.');
        }

        $minimumQuantity = OrderManager::validateStockAvailability($request);
        if ((int) ($minimumQuantity['status'] ?? 1) === 0) {
            return $minimumQuantity['message'] ?? (translate('Minimum_order_quantity') ?: 'Minimum order quantity is not met.');
        }

        return null;
    }

    public function placeOrder(?StorefrontScope $scope, ?int $customerId, array $state): array
    {
        // Resolve the shopper from the request context: a guest carries a
        // guest_users id (session `guest_id`) instead of a customer id, with
        // is_guest = 1 on every cart/address/order row. Guest checkout is only
        // permitted when the admin has enabled the `guest_checkout` setting.
        $isGuest     = $this->context->isGuest();
        $shopperId   = $this->context->getShopperId();
        $isGuestFlag = $this->context->shopperIsGuestFlag();

        if ($isGuest && (int) getWebConfig(name: 'guest_checkout') !== 1) {
            return $this->fail('auth', translate('Sign_in_required') ?: 'Please sign in to place an order.');
        }
        if (!$shopperId) {
            return $this->fail('auth', translate('Sign_in_required') ?: 'Please sign in to place an order.');
        }

        // Pin the guest identity to the exact guest_users id that owns the cart
        // (verified below). Every host helper — cart lookup, OrderManager, the
        // digital-payment pipeline — reads session('guest_id')/request('guest_id')
        // first, so aligning both here makes COD, offline and digital resolve the
        // same guest deterministically instead of depending on session timing
        // (which breaks on custom-domain sessions and the deferred gateway hook).
        if ($isGuestFlag === 1) {
            session(['guest_id' => $shopperId]);
            request()->merge(['is_guest' => 1, 'guest_id' => $shopperId]);
        }

        // Serialize placement per shopper: a double-click, a client retry, or a
        // second tab must not create duplicate orders or race the cart's shared
        // is_checked flag. Degrades to unserialized if the cache store has no
        // lock support (so a mis-set CACHE_DRIVER can't 500 every checkout).
        try {
            $lock = Cache::lock('storefront:place-order:' . $isGuestFlag . '-' . $shopperId, 20);
            $acquired = $lock->get();
        } catch (\Throwable) {
            $lock = null;
            $acquired = true;
        }
        if (!$acquired) {
            return $this->fail('order', translate('order_placement_in_progress') ?: 'Your order is already being placed — please wait a moment.');
        }

        try {
            return $this->runPlaceOrder($scope, $shopperId, $isGuestFlag, $state);
        } finally {
            $lock?->release();
        }
    }

    private function runPlaceOrder(?StorefrontScope $scope, int $shopperId, int $isGuestFlag, array $state): array
    {
        $cartType = $this->scopeCartToVendor($scope);

        $carts = Cart::where('customer_id', $shopperId)->where('is_guest', $isGuestFlag)
            ->when($cartType === 'checked', fn ($query) => $query->where('is_checked', 1))
            ->get();
        if ($carts->isEmpty()) {
            return $this->fail('cart', translate('Your_cart_is_empty') ?: 'Your cart is empty.');
        }

        // Persist the auto-selected order-wise shipping method before the order
        // pipeline reads it, so the delivery fee on the order matches the quote.
        $this->ensureOrderWiseShippingSelected($cartType);

        // Order-wise shipping with no method configured (by admin/seller per the
        // Shipping Responsibility setting) must not silently place a zero-fee
        // order — block it and tell the customer to contact the vendor.
        if ($this->unavailableShippingShops($cartType) !== []) {
            return $this->fail('order', translate('no_shipping_method_available_for_this_shop_please_contact_the_vendor') ?: 'This shop currently has no shipping method available. Please contact the vendor.');
        }

        // Business-rule gates (same as the legacy cart→checkout guards): a
        // per-shop minimum order amount and a per-product minimum order quantity
        // must be satisfied before the order can be generated. Both reuse the
        // host OrderManager verifiers so the storefront stays in sync.
        if ($blocker = $this->orderRuleBlocker($shopperId, $isGuestFlag)) {
            return $this->fail('order', $blocker);
        }

        $addressId = $this->resolveAddressId($shopperId, $isGuestFlag, $state);
        if (!$addressId) {
            return $this->fail('address', translate('Please_update_address_information') ?: 'Please add a delivery address.');
        }
        session(['address_id' => $addressId, 'billing_address_id' => $addressId]);

        $method = strtolower((string) ($state['paymentMethod'] ?? 'cash_on_delivery'));

        // Digital-only order (no physical item to deliver) can't be paid by
        // cash-on-delivery or offline payment — it must go through a digital
        // gateway. Mixed carts (physical + digital) keep COD; shipping there is
        // charged on the physical items only (CartManager::get_shipping_cost).
        if (!$carts->contains(fn ($cart) => $cart->product_type === 'physical')
            && in_array($method, ['cash_on_delivery', 'cod', 'offline_payment'], true)) {
            return $this->fail('payment', translate('digital_products_must_be_paid_online') ?: 'Digital products must be paid with a digital payment method.');
        }

        // Digital gateways hand off to the host payment-request pipeline; the
        // paid order is created by the gateway success hook (digital_payment_success),
        // so return a redirect instead of generating the order here.
        if ($this->isDigitalGateway($method)) {
            return $this->digitalPaymentRedirect($scope, $shopperId, $isGuestFlag, $state, $method);
        }

        $paymentData = $this->paymentData($method, $state);
        if (isset($paymentData['error'])) {
            return $paymentData['error'];
        }

        try {
            // One transaction wraps the whole generation so a mid-way throw
            // (deadlock, coupon/stock edge) rolls back cleanly instead of leaving
            // an orphaned order, double-decremented stock or a double wallet
            // credit. Re-asserting scopeCartToVendor INSIDE the transaction takes
            // exclusive row locks on the cart via its is_checked UPDATE, held to
            // commit — so a concurrent quote/add-to-cart can't flip is_checked
            // back to 0 before generateOrder reads the checked cart (the race that
            // produced empty, "sometimes" failed orders).
            $orderIds = DB::transaction(function () use ($scope, $addressId, $state, $paymentData, $shopperId, $isGuestFlag) {
                $this->scopeCartToVendor($scope);

                return OrderManager::generateOrder(data: array_merge([
                    'coupon_code'         => $state['couponCode'] ?? session('coupon_code'),
                    'address_id'          => $addressId,
                    'billing_address_id'  => $addressId,
                    'transaction_ref'     => '',
                    'is_guest'            => $isGuestFlag,
                    'guest_id'            => $isGuestFlag === 1 ? $shopperId : null,
                ], $paymentData));
            });
        } catch (\Throwable $exception) {
            \Log::warning('Storefront order placement failed', ['customer_id' => $shopperId, 'is_guest' => $isGuestFlag, 'error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            return $this->fail('order', translate('Could_not_place_the_order') ?: 'Could not place the order.');
        }

        $orderId = is_array($orderIds) ? (int) ($orderIds[0] ?? 0) : 0;
        if ($orderId <= 0) {
            return $this->fail('order', translate('Could_not_place_the_order') ?: 'Could not place the order.');
        }

        return [
            'success'         => true,
            'orderId'         => $orderId,
            'paymentRedirect' => null,
            'message'         => translate('Order_Placed_Successfully') ?: 'Order placed successfully.',
            'total'           => $this->money(CartManager::cart_grand_total(type: $cartType)),
        ];
    }

    /**
     * Total VAT/tax for the current cart, honouring the active tax mode
     * (order_wise / product_wise / category_wise) from the TaxModule — the same
     * primitives OrderManager uses when generating the order, so the checkout
     * quote matches the order that gets placed. Product tax is allocated the
     * coupon discount proportionally and physical lines additionally carry the
     * shipping tax, mirroring OrderManager::processOrderGenerateData. When tax
     * is price-included the amount is 0 and the UI shows the "incl." note.
     *
     * @return array{0: float, 1: bool}  [taxAmount, isIncluded]
     */
    /**
     * Whether VAT/tax is active for this store. False when the TaxModule add-on
     * is off or admin has no active default system-tax setup (getTaxSystemType
     * returns the auto-seeded is_active=0 placeholder in that case). The checkout
     * summary hides every tax row when this is false.
     */
    private function taxEnabled(): bool
    {
        $setup = OrderManager::getTaxSystemType()['SystemTaxVat'] ?? null;
        return getCheckAddonPublishedStatus('TaxModule') && $setup && (int) ($setup->is_active ?? 0) === 1;
    }

    private function calculateTax(iterable $carts, float $couponDiscount, float $deliveryFee): array
    {
        $taxConfig = OrderManager::getTaxSystemType();
        if (!empty($taxConfig['is_included'])) {
            return [0.0, true];
        }

        // Proportional bases: the coupon spreads across all discounted product
        // price; shipping tax spreads across the physical (shippable) lines only.
        $totalDiscountedPrice = 0.0;
        $applicableShippingAmount = 0.0;
        foreach ($carts as $cart) {
            if (!$cart->product) {
                continue;
            }
            $line = ((float) $cart['price'] - (float) $cart['discount']) * (int) $cart['quantity'];
            $totalDiscountedPrice += $line;
            if (($cart->product->product_type ?? 'physical') !== 'digital') {
                $applicableShippingAmount += $line;
            }
        }

        $tax = 0.0;
        foreach ($carts as $cart) {
            if (!$cart->product) {
                continue;
            }
            $line = ((float) $cart['price'] - (float) $cart['discount']) * (int) $cart['quantity'];
            $couponAllocation = $totalDiscountedPrice > 0 ? ($couponDiscount * $line) / $totalDiscountedPrice : 0.0;

            $tax += (float) CartManager::getAppliedTaxAmount(
                product: $cart->product,
                taxConfig: $taxConfig,
                totalDiscountedPrice: $line,
                appliedDiscountedAmount: $couponAllocation,
            );

            if (($cart->product->product_type ?? 'physical') !== 'digital') {
                $tax += (float) CartManager::getAppliedShippingTaxAmount(
                    taxConfig: $taxConfig,
                    totalShippingCost: $deliveryFee,
                    totalDiscountedPrice: $line,
                    applicableShippingAmount: $applicableShippingAmount,
                );
            }
        }

        return [max(0.0, $tax), false];
    }

    /**
     * On a vendor storefront the shared cart may hold items from other shops
     * (added on the marketplace or another vendor's storefront). Every cart read
     * used below — quote, shipping, grand total — and OrderManager::generateOrder
     * key off the `is_checked` flag, so mark only this vendor's rows checked and
     * the rest unchecked. Downstream reads then operate on this shop's items
     * alone and the placed order never spans other vendors.
     *
     * Returns the cart read `type` to pass to CartManager: 'checked' when scoped
     * to a vendor, 'all' on the global marketplace (no scope) where the whole
     * cart is checked out as before.
     */
    /**
     * 6Valley computes the delivery fee from its Shipping Method setting
     * (order-wise / category-wise / product-wise). Category/product-wise costs
     * are already baked onto each cart row by CartManager, but order-wise needs
     * a ShippingMethod chosen per cart group (stored in CartShipping) — the
     * legacy site has a picker for it, the storefront does not. So when this
     * host opts in (config `builder.order_wise_shipping_auto_select`), pick the
     * cheapest applicable order-wise method for any group that has none, so the
     * fee is charged instead of silently reading 0. 6amMart uses zone/distance
     * delivery, not this flow, so it keeps the flag false.
     */
    private function ensureOrderWiseShippingSelected(?string $cartType): void
    {
        if (!config('builder.order_wise_shipping_auto_select', false)) {
            return;
        }

        foreach ($this->orderWiseGroups($cartType) as $group) {
            if (CartShipping::where('cart_group_id', $group['id'])->exists()) {
                continue;
            }

            // Default to the cheapest applicable method; the customer can change
            // it via the checkout Shipping Method picker (selectShippingMethod).
            $method = $group['methods']->sortBy('cost')->first();
            if ($method) {
                $this->persistCartShipping((string) $group['id'], $method);
            }
        }
    }

    /**
     * Selectable order-wise shipping options per cart group, for the storefront
     * Shipping Method picker. Only order-wise groups with ≥1 method appear.
     */
    public function shippingMethods(?StorefrontScope $scope, ?int $customerId): array
    {
        if (!config('builder.order_wise_shipping_auto_select', false)) {
            return ['enabled' => false, 'groups' => []];
        }

        $cartType = $this->scopeCartToVendor($scope);
        $this->ensureOrderWiseShippingSelected($cartType);

        $groups = [];
        foreach ($this->orderWiseGroups($cartType) as $group) {
            if ($group['methods']->isEmpty()) {
                continue;
            }

            $selectedId = CartShipping::where('cart_group_id', $group['id'])->value('shipping_method_id');

            $groups[] = [
                'id'         => (string) $group['id'],
                'shop'       => $group['shop'],
                'selectedId' => $selectedId ? (int) $selectedId : null,
                'methods'    => $group['methods']->sortBy('cost')->values()->map(fn ($method) => [
                    'id'       => (int) $method->id,
                    'title'    => (string) $method->title,
                    'cost'     => (float) webCurrencyConverterOnlyDigit(amount: (float) $method->cost),
                    'duration' => $method->duration ? (string) $method->duration : null,
                ])->all(),
            ];
        }

        return ['enabled' => $groups !== [], 'groups' => $groups];
    }

    /**
     * Shops in the cart whose shipping type is order-wise but which have no
     * order-wise ShippingMethod configured — so no delivery fee can be resolved
     * and the order must be blocked. Returns their shop labels (empty = ok).
     * Gated by the same flag as the rest of the order-wise flow so zone/distance
     * hosts (6amMart) are unaffected.
     *
     * @return string[]
     */
    private function unavailableShippingShops(?string $cartType): array
    {
        if (!config('builder.order_wise_shipping_auto_select', false)) {
            return [];
        }

        $shops = [];
        foreach ($this->orderWiseGroups($cartType) as $group) {
            if ($group['methods']->isEmpty()) {
                $shops[] = $group['shop'];
            }
        }
        return $shops;
    }

    public function selectShippingMethod(?StorefrontScope $scope, ?int $customerId, string $cartGroupId, int $shippingMethodId): array
    {
        $cartType = $this->scopeCartToVendor($scope);

        $group = collect($this->orderWiseGroups($cartType))->firstWhere('id', $cartGroupId);
        if (!$group) {
            return ['success' => false, 'error' => translate('Shipping_Not_Available_for_this_Shop') ?: 'Shipping is not available for this group.'];
        }

        $method = $group['methods']->firstWhere('id', $shippingMethodId);
        if (!$method) {
            return ['success' => false, 'error' => translate('Selected_shipping_method_not_found') ?: 'Selected shipping method not found.'];
        }

        $this->persistCartShipping($cartGroupId, $method);

        return ['success' => true];
    }

    /**
     * Resolve the customer's physical cart into per-group order-wise shipping
     * context: the group id, its shop label, and the applicable ShippingMethod
     * list. Groups whose shipping type is category/product-wise are omitted
     * (their cost is already baked onto the cart rows by CartManager).
     *
     * @return array<int, array{id: mixed, shop: string, methods: \Illuminate\Support\Collection}>
     */
    private function orderWiseGroups(?string $cartType): array
    {
        $shippingMethodConfig = getWebConfig(name: 'shipping_method');
        $adminShipping = ShippingType::where('seller_id', 0)->first();

        $cartItems = Cart::where('product_type', 'physical')
            ->whereHas('product', fn ($query) => $query->active())
            ->whereIn('cart_group_id', CartManager::get_cart_group_ids(type: $cartType === 'checked' ? 'checked' : null))
            ->when($cartType === 'checked', fn ($query) => $query->where('is_checked', 1))
            ->get();

        $groups = [];
        foreach ($cartItems->groupBy('cart_group_id') as $cartGroupId => $groupItems) {
            $first = $groupItems->first();

            if ($shippingMethodConfig === 'inhouse_shipping' || $first?->seller_is === 'admin') {
                $shippingType = $adminShipping->shipping_type ?? 'order_wise';
                $methods = $shippingType === 'order_wise' ? Helpers::getShippingMethods(0, 'admin') : collect();
            } else {
                $sellerShipping = ShippingType::where('seller_id', $first?->seller_id)->first();
                $shippingType = $sellerShipping->shipping_type ?? 'order_wise';
                $methods = $shippingType === 'order_wise' ? Helpers::getShippingMethods($first?->seller_id, 'seller') : collect();
            }

            if ($shippingType !== 'order_wise') {
                continue;
            }

            $groups[] = [
                'id'      => $cartGroupId,
                'shop'    => $this->groupShopName($first),
                'methods' => $methods,
            ];
        }

        return $groups;
    }

    private function groupShopName($cartItem): string
    {
        if (($cartItem?->seller_is ?? null) === 'admin') {
            return getWebConfig(name: 'company_name') ?: 'In-House';
        }
        return Shop::where('seller_id', $cartItem?->seller_id)->value('name')
            ?? (translate('Shop') ?: 'Shop');
    }

    private function persistCartShipping(string $cartGroupId, $method): void
    {
        $cartShipping = CartShipping::firstOrNew(['cart_group_id' => $cartGroupId]);
        $cartShipping->shipping_method_id = $method->id;
        $cartShipping->shipping_cost = $method->cost;
        $cartShipping->save();
    }

    /**
     * Whether the current (scoped) cart contains at least one physical product.
     * Digital-only carts skip shipping and are restricted to online payment.
     */
    private function cartHasPhysical(?string $cartType): bool
    {
        $groupIds = CartManager::get_cart_group_ids(type: $cartType === 'checked' ? 'checked' : null);
        if ($groupIds === []) {
            return false;
        }

        return Cart::whereIn('cart_group_id', $groupIds)
            ->where('product_type', 'physical')
            ->whereHas('product', fn ($query) => $query->active())
            ->when($cartType === 'checked', fn ($query) => $query->where('is_checked', 1))
            ->exists();
    }

    private function scopeCartToVendor(?StorefrontScope $scope): string
    {
        $vendorId  = $scope?->tenantId;
        $shopperId = $this->context->getShopperId();

        if (!$vendorId || $shopperId === null) {
            return 'all';
        }

        $isGuest = $this->context->shopperIsGuestFlag();

        Cart::where('customer_id', $shopperId)->where('is_guest', $isGuest)
            ->update(['is_checked' => 0]);
        Cart::where('customer_id', $shopperId)->where('is_guest', $isGuest)
            ->where('seller_id', $vendorId)->where('seller_is', 'seller')
            ->update(['is_checked' => 1]);

        return 'checked';
    }

    /* ─── payment mapping ─────────────────────────────────── */

    private function paymentData(string $method, array $state): array
    {
        if (in_array($method, ['cash_on_delivery', 'cod'], true)) {
            return [
                'order_status'   => 'pending',
                'payment_method' => 'cash_on_delivery',
                'payment_status' => 'unpaid',
                'bring_change_amount' => $state['bringChange'] ?? 0,
                'bring_change_amount_currency' => session('currency_code'),
            ];
        }

        if ($method === 'offline_payment') {
            // Client sends { methodId, methodName, fields:{...}, customerNote };
            // flatten it into the same shape the legacy web checkout stores on
            // OfflinePayments.payment_info (method_id, method_name, then each
            // dynamic customer-input field) so admin/vendor render it correctly.
            $offline = (array) ($state['offlinePayment'] ?? []);
            $info = [];
            if (!empty($offline['methodId'])) {
                $info['method_id']   = $offline['methodId'];
                $info['method_name'] = $offline['methodName'] ?? '';
                foreach ((array) ($offline['fields'] ?? []) as $field => $value) {
                    $info[$field] = $value;
                }
            }

            return [
                'order_status'         => 'pending',
                'payment_method'       => 'offline_payment',
                'payment_status'       => 'unpaid',
                'payment_note'         => $offline['customerNote'] ?? null,
                'offline_payment_info' => $info,
            ];
        }

        // Digital gateways are handled earlier in placeOrder(); anything reaching
        // here (e.g. wallet) is not exposed to the storefront yet.
        return ['error' => $this->fail('payment', translate('This_payment_method_is_currently_unavailable') ?: 'This payment method is currently unavailable.')];
    }

    /**
     * True when the chosen method is an active digital payment gateway
     * (SSLCommerz, Stripe, …) rather than COD/offline/wallet.
     */
    private function isDigitalGateway(string $method): bool
    {
        if ((int) getWebConfig(name: 'digital_payment') !== 1) {
            return false;
        }

        foreach ($this->paymentMethods->digitalPaymentMethods() as $gateway) {
            if (strtolower((string) $gateway['key']) === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a hosted-gateway payment request for a digital method and return its
     * redirect URL as `paymentRedirect`. Reuses the pipeline the legacy web
     * checkout and mobile API use (PaymentController::getCustomerPaymentRequest →
     * generate_link). The order itself is generated by the digital_payment_success
     * hook once the gateway confirms payment — both the amount charged and that
     * deferred order read the checkout context (coupon, note) from the session.
     */
    private function digitalPaymentRedirect(?StorefrontScope $scope, int $customerId, int $isGuestFlag, array $state, string $method): array
    {
        $subtotal = 0.0;
        foreach (CartManager::getCartListQuery(type: $this->scopeCartToVendor($scope)) as $cart) {
            $subtotal += ((float) $cart['price'] - (float) $cart['discount']) * (int) $cart['quantity'];
        }

        [$couponCode, , $couponDiscount, $couponError] = $this->resolveCoupon($state, $customerId, $scope, max(0.0, $subtotal));
        if ($couponError) {
            // A coupon that no longer validates is dropped rather than blocking payment.
            $couponCode = null;
            $couponDiscount = 0.0;
        }

        session([
            'coupon_code'     => $couponCode,
            'coupon_discount' => $couponDiscount,
            'order_note'      => $state['instructions'] ?? null,
        ]);

        $request = request();
        $request->merge([
            'payment_method'         => $method,
            'payment_platform'       => 'web',
            'payment_request_from'   => 'web',
            'payment_mode'           => 'web',
            'is_guest'               => $isGuestFlag,
            'guest_id'               => $isGuestFlag ? $customerId : 0,
            'current_currency_code'  => session('currency_code'),
            // Public gateway-return endpoint. MUST NOT be a customer-gated route
            // (e.g. profile) — the gateway returns from the host domain with a
            // frequently-absent storefront session, and RequireCustomer would then
            // fire auth_required (login modal + "sign in" toast) instead of showing
            // the payment result. DigitalPaymentResultController reads the gateway's
            // success/fail flag and bounces to home/orders with the right flash.
            'external_redirect_link' => route('storefront.digital_payment_result'),
        ]);

        $redirect = app(PaymentController::class)->getCustomerPaymentRequest($request);

        if (!is_string($redirect) || trim($redirect) === '') {
            return $this->fail('payment', translate('This_payment_method_is_currently_unavailable') ?: 'This payment method is currently unavailable.');
        }

        return [
            'success'         => true,
            'orderId'         => 0,
            'paymentRedirect' => $this->paymentUrlOnHostDomain($redirect),
        ];
    }

    /**
     * The digital-payment gateway routes (`payment/*`) are registered only on
     * the host domain (config('app.host_domain')). The storefront runs on a
     * tenant sub-domain / custom domain where those routes fall through to the
     * Builder storefront and 404, so re-point the gateway redirect at the host
     * domain. The gateway drives everything off the DB `payment_id`, and the
     * post-payment redirect returns to the storefront via external_redirect_link.
     */
    private function paymentUrlOnHostDomain(string $url): string
    {
        $hostDomain = config('app.host_domain');
        if (empty($hostDomain)) {
            return $url;
        }

        $parts  = parse_url($url) ?: [];
        if (($parts['host'] ?? null) === $hostDomain) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? (request()->isSecure() ? 'https' : 'http');
        $path   = $parts['path'] ?? '';
        $query  = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $hostDomain . $path . $query;
    }

    /* ─── snapshot helpers ────────────────────────────────── */

    private function storeOpen(?Shop $shop): bool
    {
        if (!$shop) {
            return true;
        }
        try {
            return !(bool) ($shop->temporary_close ?? false) && !(bool) ($shop->vacation_status ?? false);
        } catch (\Throwable) {
            return true;
        }
    }

    private function freeDeliveryThreshold(?Shop $shop): ?float
    {
        $amount = getWebConfig(name: 'free_delivery_over_amount');
        return is_numeric($amount) ? (float) $amount : null;
    }

    private function offlineMethods(): array
    {
        if ((int) (getWebConfig(name: 'offline_payment')['status'] ?? 0) !== 1) {
            return [];
        }

        return OfflinePaymentMethod::where('status', 1)
            ->get(['id', 'method_name', 'method_fields', 'method_informations'])
            ->map(fn (OfflinePaymentMethod $method) => [
                'id'           => $method->id,
                'name'         => $method->method_name,
                'fields'       => $method->method_fields ?? [],
                'informations' => $method->method_informations ?? [],
            ])
            ->values()
            ->all();
    }

    private function gatewayMethods(): array
    {
        if ((int) getWebConfig(name: 'digital_payment') !== 1) {
            return [];
        }

        return array_map(fn (array $method) => [
            'key'      => $method['key'],
            'title'    => $method['label'],
            'imageUrl' => $method['image'],
        ], $this->paymentMethods->digitalPaymentMethods());
    }

    /* ─── quote/order helpers ─────────────────────────────── */

    private function resolveCoupon(array $state, ?int $customerId, ?StorefrontScope $scope, float $subtotal): array
    {
        $code = trim((string) ($state['couponCode'] ?? ''));
        if ($code === '') {
            return [null, null, 0.0, null, false];
        }

        $result = $this->coupons->validate($code, $customerId, $scope, $subtotal);
        if (!($result['ok'] ?? false)) {
            return [$code, null, 0.0, $result['error'] ?? (translate('invalid_coupon') ?: 'Invalid coupon.'), false];
        }

        return [$result['code'], $result['title'] ?? null, (float) ($result['discount'] ?? 0), null, (bool) ($result['freeDelivery'] ?? false)];
    }

    private function resolveAddressId(int $customerId, int $isGuestFlag, array $state): ?int
    {
        $addressId = $state['addressId'] ?? null;
        if ($addressId && ShippingAddress::where('id', $addressId)->where('customer_id', $customerId)->where('is_guest', $isGuestFlag)->exists()) {
            return (int) $addressId;
        }

        // A guest has no saved address book — create the delivery address from
        // the contact details captured inline at checkout (mirrors the legacy
        // SystemController guest-address insert; email is required for a guest).
        if ($isGuestFlag === 1) {
            return $this->createGuestAddress($customerId, $state);
        }

        // Fall back to the customer's most recent saved address.
        $latest = ShippingAddress::where('customer_id', $customerId)->where('is_guest', 0)->latest()->value('id');
        return $latest ? (int) $latest : null;
    }

    /**
     * Persist a one-off delivery address for a guest order. The storefront
     * collects name/phone/email/address inline (guest address-edit drawer); the
     * order links to this row and reads the guest's identity back from it, since
     * the order row itself carries no guest name/email (only customer_id =
     * guest_users.id + is_guest = 1).
     */
    private function createGuestAddress(int $guestId, array $state): ?int
    {
        $name    = trim((string) ($state['contactName'] ?? ''));
        $phone   = trim((string) ($state['contactPhone'] ?? ''));
        $email   = trim((string) ($state['contactEmail'] ?? ''));
        $address = trim((string) ($state['address'] ?? ''));

        if ($name === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $address === '') {
            return null;
        }

        return (int) ShippingAddress::insertGetId([
            'customer_id'         => $guestId,
            'is_guest'            => 1,
            'contact_person_name' => $name,
            'phone'               => $phone,
            'email'               => $email,
            'address_type'        => $state['addressType'] ?? 'Delivery',
            'address'             => $address,
            'latitude'            => $state['lat'] ?? null,
            'longitude'           => $state['lng'] ?? null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    private function fail(string $code, string $message): array
    {
        return ['success' => false, 'errors' => [['code' => $code, 'message' => $message]]];
    }

    /**
     * Convert a system-default-currency amount to the active web currency for
     * display (multi-currency), digits only — the storefront adds the symbol.
     * Order placement stays in the default currency (OrderManager recomputes).
     */
    private function money(float|int|null $amount): float
    {
        return (float) webCurrencyConverterOnlyDigit(amount: (float) ($amount ?? 0));
    }

    /**
     * Decode the digital-payment gateway return into {orderId, phone}. The
     * gateway appends base64(JSON) `order_ids`; the order's contact phone comes
     * from its stored shipping address. Lives here (not the storefront
     * controller) so the Builder module carries no host-model access.
     */
    public function resolveDigitalPaymentReturn(array $query): array
    {
        $raw = $query['order_ids'] ?? null;
        if (!$raw) {
            return ['orderId' => null, 'phone' => null];
        }

        // `+` in the base64 can arrive as a space after URL decoding — restore it.
        $decoded = json_decode(base64_decode(str_replace(' ', '+', (string) $raw)) ?: '[]', true);
        $orderId = is_array($decoded) ? (int) ($decoded[0] ?? 0) : 0;
        if ($orderId <= 0) {
            return ['orderId' => null, 'phone' => null];
        }

        $order = Order::select(['shipping_address_data'])->find($orderId);
        $address = $order?->shipping_address_data;
        $address = is_string($address) ? json_decode($address, true) : (array) $address;

        return ['orderId' => $orderId, 'phone' => $address['phone'] ?? $address['contact_person_number'] ?? null];
    }
}
