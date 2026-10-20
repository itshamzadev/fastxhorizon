<?php

namespace App\Builder;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\OrderProvider as OrderProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\OrderDetailDTO;
use Modules\Builder\ValueObjects\Storefront\OrderSummaryDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for the storefront order surface.
 *
 * Maps App\Models\Order onto the module's order DTOs. 6Valley has no delivery
 * modules/zones or scheduling, so moduleType/scheduled are constant. Orders are
 * owned by `customer_id` (is_guest = 0); each order is per-seller, so on a
 * vendor storefront the scope restricts the history to that shop's orders
 * (customerOrdersBaseQuery), while the global marketplace shows all shops.
 * The single-order mapping lives in the public formatOrder() so OrderTracking
 * can reuse it after its own (id + phone) lookup.
 */
class OrderProvider implements OrderProviderContract
{
    private const STATUS_LABEL = [
        'pending'          => 'Pending',
        'failed'           => 'Pending',
        'confirmed'        => 'Confirmed',
        'processing'       => 'Processing',
        'out_for_delivery' => 'Out For Delivery',
        'delivered'        => 'Delivered',
        'canceled'         => 'Cancelled',
        'returned'         => 'Returned',
    ];

    private const STATUS_VARIANT = [
        'pending'          => 'info',
        'failed'           => 'info',
        'confirmed'        => 'warning',
        'processing'       => 'warning',
        'out_for_delivery' => 'warning',
        'delivered'        => 'success',
        'canceled'         => 'danger',
        'returned'         => 'danger',
    ];

    private const TIMELINE = ['pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered'];

    public function customerOrderListing(
        ?StorefrontScope $scope,
        ?int $customerId,
        string $bucket,
        array $statuses,
        int $perPage,
        int $page,
        string $pageName,
    ): PaginatedResult {
        if (!$customerId) {
            return PaginatedResult::fromPaginator(new LengthAwarePaginator([], 0, $perPage, $page, ['pageName' => $pageName]));
        }

        $predicate = $bucket === 'previous'
            ? fn (EloquentBuilder $q) => $q->whereIn('order_status', $statuses)
            : fn (EloquentBuilder $q) => $q->whereNotIn('order_status', $statuses);

        $paginator = $predicate($this->customerOrdersBaseQuery($customerId, $scope))
            ->withCount('details')
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, columns: ['id', 'order_status', 'order_type', 'order_amount', 'payment_status', 'created_at'], pageName: $pageName, page: $page);

        // Batch-resolve which orders on this page are fully reviewed so a
        // reviewable order flips to "not reviewable" (→ View Review) once done.
        $reviewedMap = $this->reviewedOrderMap($customerId, collect($paginator->items()));

        $paginator->through(fn (Order $order) => OrderSummaryDTO::fromArray([
            'id'            => $order->id,
            'status'        => $order->order_status,
            'statusLabel'   => $this->statusLabel($order->order_status),
            'statusVariant' => $this->statusVariant($order->order_status),
            'amount'        => $this->money($order->order_amount),
            'items'         => (int) $order->details_count,
            'paid'          => $order->payment_status === 'paid',
            'reorderable'   => $order->order_status === 'delivered',
            'reviewable'    => $order->order_status === 'delivered' && !($reviewedMap[$order->id] ?? false),
            'date'          => $order->created_at ? Carbon::parse($order->created_at)->format('h:iA, d M y') : null,
        ])->toArray());

        return PaginatedResult::fromPaginator($paginator);
    }

    public function customerOrdersCount(?StorefrontScope $scope, ?int $customerId): int
    {
        return $customerId ? $this->customerOrdersBaseQuery($customerId, $scope)->count() : 0;
    }

    public function latestUnpaidDigitalOrder(?StorefrontScope $scope, ?int $customerId): ?array
    {
        if (!$customerId) {
            return null;
        }

        $order = $this->customerOrdersBaseQuery($customerId, $scope)
            ->where('payment_status', 'unpaid')
            ->where('order_status', 'failed')
            ->orderByDesc('created_at')
            ->first(['id', 'order_amount', 'paid_amount']);

        if (!$order) {
            return null;
        }

        return [
            'orderId'   => (int) $order->id,
            'dueAmount' => (float) ($order->order_amount - ($order->paid_amount ?? 0)),
        ];
    }

    public function paymentReturnInfo(int $orderId): ?array
    {
        $order = Order::query()
            ->select(['id', 'is_guest', 'customer_id', 'shipping_address_data', 'payment_status'])
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return null;
        }

        $stored = $this->decode($order->shipping_address_data);

        return [
            'isGuest'       => (int) $order->is_guest === 1,
            'storedPhone'   => $stored['phone'] ?? $stored['contact_person_number'] ?? null,
            'paymentStatus' => $order->payment_status !== null ? (string) $order->payment_status : null,
        ];
    }

    public function customerOrderDetails(?StorefrontScope $scope, ?int $customerId, int $orderId): ?array
    {
        if (!$customerId) {
            return null;
        }

        $order = $this->customerOrdersBaseQuery($customerId, $scope)
            ->with(['details.product', 'details.productAllStatus', 'deliveryMan', 'seller.shop'])
            ->where('id', $orderId)
            ->first();

        return $order ? $this->formatOrder($order) : null;
    }

    /**
     * Storefront DTO for a single order — shared by the profile order-details
     * page and the order-tracking page.
     */
    public function formatOrder(Order $order): array
    {
        $rawStatus = (string) $order->order_status;
        $paid = $order->payment_status === 'paid';

        return OrderDetailDTO::fromArray([
            'id'            => (string) $order->id,
            'date'          => $order->created_at ? Carbon::parse($order->created_at)->format('h:iA, d M y') : null,
            'scheduled'     => false,
            'scheduleAt'    => null,
            'status'        => $this->statusLabel($rawStatus),
            'statusRaw'     => $rawStatus,
            'statusVariant' => $this->statusVariant($rawStatus),
            'paid'          => $paid,
            // Mirror OrderActionsProvider::cancel exactly: only a signed-in
            // customer's still-pending cash-on-delivery order can be cancelled.
            // Once confirmed (or for prepaid orders) the server rejects it, so
            // the button must not show.
            'cancellable'   => (int) $order->is_guest !== 1
                && $rawStatus === 'pending'
                && $order->payment_method === 'cash_on_delivery',
            'refundable'    => $rawStatus === 'delivered' && $paid && (bool) config('builder.wallet_features_enabled', true),
            'reorderable'   => $rawStatus === 'delivered',
            'reviewable'    => $rawStatus === 'delivered' && !$this->allItemsReviewed($order),
            'paymentMethod' => $this->paymentLabel($order->payment_method),
            'paymentIcon'   => null,
            'orderType'     => (string) ($order->order_type ?? 'delivery'),
            'moduleType'    => null,
            'items'         => $this->mapItems($order),
            'pricing'       => $this->mapPricing($order),
            'delivery'      => $this->mapDelivery($order),
            'seller'        => $this->mapSeller($order),
            'deliveryMan'   => $this->mapDeliveryMan($order),
            'tracking'      => $this->mapTracking($rawStatus),
            'cancellationNote' => $rawStatus === 'canceled' && !empty($order->cause) ? (string) $order->cause : null,
            'verificationCode' => $this->resolveVerificationCode($order, $rawStatus),
        ])->toArray();
    }

    /**
     * Order-delivery verification code shown to the customer, mirroring the
     * legacy account-order-details head: only when the `order_verification`
     * business setting is on, the order is a normal (non-POS) order, and it
     * hasn't been delivered yet (once delivered the code is spent). Returns
     * null in every other case so the frontend drops the block.
     */
    private function resolveVerificationCode(Order $order, string $rawStatus): ?string
    {
        if ((int) getWebConfig(name: 'order_verification') !== 1) {
            return null;
        }
        if (($order->order_type ?? 'default_type') !== 'default_type' || $rawStatus === 'delivered') {
            return null;
        }

        $code = $this->cleanScalar($order->verification_code);

        return $code !== null && $code !== '' ? (string) $code : null;
    }

    private function customerOrdersBaseQuery(int $customerId, ?StorefrontScope $scope = null): EloquentBuilder
    {
        // Each 6Valley order is per-seller, so on a vendor storefront restrict
        // the customer's history to this shop's orders — otherwise orders placed
        // with other vendors would list here. On the global marketplace (no
        // scope) the full cross-shop history is shown as before.
        $vendorId = $scope?->tenantId;

        // POS orders are placed in-store by the seller, not through the
        // storefront, so they must never surface in the customer's profile
        // order history — nor be reachable by deep-linking ?orderId=. This is
        // the single choke point (listing, count, and details all run through
        // it), so excluding POS here drops them from the list and makes a
        // direct-link lookup return null (the deep-link guard 404s it).
        return Order::query()
            ->where('customer_id', $customerId)
            ->where('is_guest', 0)
            ->where('order_type', '!=', 'POS')
            ->when($vendorId, fn (EloquentBuilder $query) => $query
                ->where('seller_id', $vendorId)
                ->where('seller_is', 'seller'));
    }

    /**
     * True when every distinct product in the order has a review by the order's
     * customer — the "fully reviewed" state that flips reviewable off so the UI
     * offers "View Review" instead of "Give Review".
     */
    private function allItemsReviewed(Order $order): bool
    {
        $productIds = $order->details
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($productIds->isEmpty()) {
            return false;
        }

        $reviewedCount = Review::query()
            ->where('order_id', $order->id)
            ->where('customer_id', $order->customer_id)
            ->whereIn('product_id', $productIds->all())
            ->distinct()
            ->count('product_id');

        return $reviewedCount >= $productIds->count();
    }

    /**
     * Batch "fully reviewed" resolution for a page of orders (avoids N+1 in the
     * listing): order_id => bool. Compares distinct reviewed products against
     * distinct ordered products per order.
     *
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     * @return array<int, bool>
     */
    private function reviewedOrderMap(int $customerId, $orders): array
    {
        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($orderIds === []) {
            return [];
        }

        $productCounts = DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('product_id')
            ->select('order_id', DB::raw('COUNT(DISTINCT product_id) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $reviewedCounts = Review::query()
            ->whereIn('order_id', $orderIds)
            ->where('customer_id', $customerId)
            ->whereNotNull('product_id')
            ->select('order_id', DB::raw('COUNT(DISTINCT product_id) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $map = [];
        foreach ($orderIds as $id) {
            $total = (int) ($productCounts[$id] ?? 0);
            $reviewed = (int) ($reviewedCounts[$id] ?? 0);
            $map[$id] = $total > 0 && $reviewed >= $total;
        }

        return $map;
    }

    /**
     * Convert a stored (system-default-currency) amount to the customer's active
     * web currency, digits only — the DTO carries the symbol separately. Mirrors
     * the storefront product pricing (App\Builder\Support\ItemPricing) so the
     * order list and details honour the multi-currency setup.
     */
    private function money(float|int|null $amount): float
    {
        return (float) webCurrencyConverterOnlyDigit(amount: (float) ($amount ?? 0));
    }

    private function mapItems(Order $order): array
    {
        $paid = $order->payment_status === 'paid';
        $items = [];
        foreach ($order->details as $detail) {
            $snapshot = $this->decode($detail->product_details);
            $unitPrice = (float) ($detail->price ?? 0);
            $discount = (float) ($detail->discount ?? 0);
            $qty = (int) ($detail->qty ?? 0);

            $name = $detail->product->name ?? ($snapshot['name'] ?? translate('Product'));
            $image = $this->resolveItemImage($detail, $snapshot);
            [$digital, $downloadUrl, $digitalPending] = $this->resolveDigitalDownload($detail, $paid);

            $items[] = [
                'id'             => (int) $detail->id,
                'name'           => (string) $name,
                'variant'        => (string) ($this->cleanScalar($detail->variant) ?? ''),
                'addons'         => '',
                'unitPrice'      => $this->money($unitPrice),
                'qty'            => $qty,
                'total'          => $this->money(max(0.0, ($unitPrice - $discount) * $qty)),
                'image'          => $image,
                'digital'        => $digital,
                'downloadUrl'    => $downloadUrl,
                'digitalPending' => $digitalPending,
            ];
        }
        return $items;
    }

    /**
     * Digital-download state for an order line, mirroring the legacy
     * account-order-summary blade: the file is downloadable only once the
     * order is paid. A `ready_product` file is always available; a
     * `ready_after_sell` file depends on the seller having uploaded the
     * per-order file yet (until then the button shows a pending state).
     *
     * @return array{0: bool, 1: ?string, 2: bool}  [isDigital, downloadUrl, pendingUpload]
     */
    private function resolveDigitalDownload(OrderDetail $detail, bool $paid): array
    {
        // OrderDetail::product() is scoped to status = 1, so a since-deactivated
        // digital product would resolve to null and drop the download button.
        // Fall back to productAllStatus, and to the order-line snapshot's
        // product_type, so a paid digital line stays downloadable regardless.
        $product = $detail->product ?? $detail->productAllStatus;
        $snapshot = $this->decode($detail->product_details);
        $productType = $product->product_type ?? ($snapshot['product_type'] ?? null);

        if ($productType !== 'digital') {
            return [false, null, false];
        }

        $digitalType = $product->digital_product_type ?? ($snapshot['digital_product_type'] ?? null);

        $downloadUrl = null;
        $pendingUpload = false;
        if ($paid) {
            if ($digitalType === 'ready_product') {
                $downloadUrl = route('storefront.order.digital_download', ['id' => $detail->id]);
            } elseif ($digitalType === 'ready_after_sell') {
                if (!empty($detail->digital_file_after_sell)) {
                    $downloadUrl = route('storefront.order.digital_download', ['id' => $detail->id]);
                } else {
                    $pendingUpload = true;
                }
            }
        }

        return [true, $downloadUrl, $pendingUpload];
    }

    /**
     * Order-line thumbnail. OrderDetail::product() is scoped to status = 1, so a
     * since-deactivated product resolves to null and its image disappears (this
     * is why many COD orders showed no item image). Resolve in order of
     * reliability: live product (any status) → order-time snapshot thumbnail
     * (covers a deleted product row). Falls back to null only when nothing at
     * all is available, leaving the frontend's own placeholder to render.
     */
    private function resolveItemImage(OrderDetail $detail, array $snapshot): ?string
    {
        $product = $detail->product ?? $detail->productAllStatus;

        // Live product thumbnail — only when the file actually exists.
        if ($product) {
            $url = $this->existingThumbnailUrl($product->thumbnail_full_url ?? null);
            if ($url) {
                return $url;
            }
        }

        // Order-time snapshot thumbnail (covers a deleted product row).
        $thumbnail = $snapshot['thumbnail'] ?? null;
        if (is_string($thumbnail) && $thumbnail !== '') {
            try {
                $snapshotProduct = new Product();
                $snapshotProduct->thumbnail = $thumbnail;
                $snapshotProduct->thumbnail_storage_type = $snapshot['thumbnail_storage_type'] ?? 'public';

                $url = $this->existingThumbnailUrl($snapshotProduct->thumbnail_full_url ?? null);
                if ($url) {
                    return $url;
                }
            } catch (\Throwable) {
            }
        }

        // No real image available (deleted product, missing file) — return null
        // so the storefront renders a fallback icon instead of a broken/blank
        // thumbnail or a generic placeholder image.
        return null;
    }

    /**
     * The URL from a thumbnail_full_url (storageLink) result ONLY when the file
     * exists. storageLink returns ['key','path','status'] with status 404 when
     * the file is missing — in that case there is no real image, so return null
     * rather than a placeholder path.
     */
    private function existingThumbnailUrl($thumbnailFullUrl): ?string
    {
        if (is_array($thumbnailFullUrl)) {
            return ((int) ($thumbnailFullUrl['status'] ?? 0) === 200 && !empty($thumbnailFullUrl['path']))
                ? (string) $thumbnailFullUrl['path']
                : null;
        }

        return is_string($thumbnailFullUrl) && $thumbnailFullUrl !== '' ? $thumbnailFullUrl : null;
    }

    private function mapPricing(Order $order): array
    {
        $subtotal = 0.0;
        $productDiscount = 0.0;
        foreach ($order->details as $detail) {
            $subtotal += (float) $detail->price * (int) $detail->qty;
            $productDiscount += (float) $detail->discount * (int) $detail->qty;
        }

        // A free-delivery coupon waives the shipping fee. 6Valley still stores the
        // full shipping_cost and folds the waived amount into discount_amount
        // (discount_type = 'coupon_discount'), so the raw columns would show the
        // fee AND an equal coupon discount. Mirror the storefront checkout, which
        // shows delivery as free: zero the delivery charge and drop the shipping
        // portion from the coupon line (order total is unchanged).
        $shippingCost   = (float) ($order->shipping_cost ?? 0);
        $couponDiscount = (float) ($order->discount_amount ?? 0);

        // Free-delivery coupon: the coupon line bundles the waived shipping.
        // Split it back out so the coupon shows only its cart discount and the
        // delivery row reads free.
        $couponFreeDelivery = $order->coupon_code
            && $order->discount_type === 'coupon_discount'
            && optional($order->coupon)->coupon_type === 'free_delivery';
        if ($couponFreeDelivery) {
            $couponDiscount = max(0.0, $couponDiscount - $shippingCost);
            $shippingCost   = 0.0;
        }

        // Free-delivery-over-amount: the order reached the admin/seller threshold
        // so shipping was waived. OrderManager already excludes shipping_cost from
        // order_amount in this case (is_shipping_free = 1), while the column still
        // holds the gross fee — zero the line here too, otherwise the breakdown
        // shows a delivery charge that isn't in the total.
        $isShippingFree = (bool) $order->is_shipping_free;
        if ($isShippingFree) {
            $shippingCost = 0.0;
        }

        $freeDelivery = $couponFreeDelivery || $isShippingFree;

        // Keys mirror the ProfileOrderDetails DTO contract exactly (itemPrice,
        // addonsPrice, vatTax, deliveryCharge, additionalCharge, …) — the
        // frontend calls .toFixed() on them unguarded, so every one must be a
        // number. Columns 6Valley doesn't have (dm_tips, extra_packaging_amount,
        // additional_charge) resolve to 0 via the null-coalesce.
        return [
            'currency'         => \App\Utils\currency_symbol() ?: '$',
            'itemPrice'        => $this->money($subtotal),
            'addonsPrice'      => 0.0,
            // Subtotal is the net of items after the product-level discount
            // (itemPrice - discount) — mirrors OrderManager::getOrderTotalPriceSummary.
            // Without this it duplicated itemPrice and both rows read the same value.
            'subtotal'         => $this->money(max(0.0, $subtotal - $productDiscount)),
            'productDiscount'  => $this->money($productDiscount),
            // 6Valley stores the coupon discount in orders.discount_amount
            // (discount_type = 'coupon_discount'); extra_discount is a separate
            // discount (free-shipping/referral). The two were swapped here, so
            // the coupon showed under "Discount" and "Coupon discount" read 0.
            // "Discount" now shows the product-level discount (sum of the line
            // discounts), and "Coupon discount" the actual coupon amount.
            'discount'         => $this->money($productDiscount),
            'couponDiscount'   => $this->money($couponDiscount),
            'couponCode'       => $order->coupon_code ?: null,
            'vatTax'           => $this->money($order->total_tax_amount ?? 0),
            'dmTips'           => $this->money($order->dm_tips ?? 0),
            'deliveryCharge'   => $this->money($shippingCost),
            // True when shipping was waived (free-delivery coupon OR the
            // over-amount threshold) — drives the storefront's "Free Delivery"
            // badge and a free/struck-through delivery row.
            'freeDelivery'     => $freeDelivery,
            'additionalCharge' => $this->money($order->additional_charge ?? 0),
            'extraPackaging'   => $this->money($order->extra_packaging_amount ?? 0),
            'total'            => $this->money($order->order_amount ?? 0),
            // COD-only "please bring change for" hint. Unlike every other
            // amount here (stored in the system-default currency and converted
            // on read), bring_change_amount is persisted in the customer's
            // order-time display currency — see CheckoutProvider::paymentData,
            // which stores the raw entered value plus bring_change_amount_currency.
            // So it must be rendered RAW; running it through money() would
            // re-apply the currency rate and inflate it (500 BDT → 42000).
            'bringChange'      => $order->payment_method === 'cash_on_delivery'
                ? (float) ($order->bring_change_amount ?? 0) : 0.0,
        ];
    }

    /**
     * Keys mirror the ProfileOrderDetails `delivery` contract (label, address,
     * floor, house, road, name, phone, email). Returns null when there's no
     * meaningful delivery data (e.g. pickup or an address never captured) so
     * the frontend drops the whole card instead of rendering an empty one.
     */
    private function mapDelivery(Order $order): ?array
    {
        $address = $this->decode($order->shipping_address_data);

        $line  = $this->cleanScalar($address['address'] ?? null);
        $name  = $this->cleanScalar($address['contact_person_name'] ?? null);
        $phone = $this->cleanScalar($address['phone'] ?? $address['contact_person_number'] ?? null);

        if (!$line && !$name && !$phone) {
            return null;
        }

        return [
            'label'   => $this->cleanScalar($address['address_type'] ?? null) ?? translate('Delivery'),
            'address' => $line,
            'floor'   => $this->cleanScalar($address['floor'] ?? null),
            'house'   => $this->cleanScalar($address['house'] ?? null),
            'road'    => $this->cleanScalar($address['road'] ?? null),
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $this->cleanScalar($address['email'] ?? null),
            'note'    => $order->order_note ?: null,
        ];
    }

    private function mapSeller(Order $order): ?array
    {
        if (($order->seller_is ?? null) === 'seller') {
            $shop = $order->seller?->shop ?? Shop::where('seller_id', $order->seller_id)->first();
            if (!$shop) {
                return null;
            }
            return [
                'name'  => $shop->name,
                'image' => getStorageImages(path: $shop->image_full_url, type: 'shop'),
                ...$this->shopRating(addedBy: 'seller', sellerId: (int) $order->seller_id),
            ];
        }

        return [
            'name'  => getWebConfig(name: 'company_name'),
            'image' => getStorageImages(path: getWebConfig(name: 'company_web_logo'), type: 'logo'),
            ...$this->shopRating(addedBy: 'admin', sellerId: null),
        ];
    }

    /**
     * Store rating for the order's seller — the Seller Info card shows it next to
     * the shop name. Reviews carry no shop_id, so (mirroring the web product page)
     * the rating aggregates approved reviews across the shop's products: the
     * vendor's own products for a seller order, all in-house products otherwise.
     *
     * @return array{rating: float, reviews: int}
     */
    private function shopRating(string $addedBy, ?int $sellerId): array
    {
        $productIds = Product::query()
            ->where('added_by', $addedBy)
            ->when($addedBy === 'seller' && $sellerId, fn ($query) => $query->where('user_id', $sellerId))
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return ['rating' => 0.0, 'reviews' => 0];
        }

        $reviews = Review::active()->whereIn('product_id', $productIds->all());
        $count = (clone $reviews)->count();

        return [
            'rating'  => $count ? round((float) $reviews->avg('rating'), 1) : 0.0,
            'reviews' => $count,
        ];
    }

    private function mapDeliveryMan(Order $order): ?array
    {
        // Third-party delivery has no platform delivery-man row (delivery_man_id
        // is null); the service name + tracking id are stored on the order itself.
        // Without this branch the card was hidden entirely for such orders.
        if ($order->delivery_type === 'third_party_delivery') {
            return $this->mapThirdPartyDelivery($order);
        }

        $deliveryMan = $order->deliveryMan;
        if (!$deliveryMan) {
            return null;
        }

        $average = (float) Review::query()->where('delivery_man_id', $deliveryMan->id)->avg('rating');
        $count = (int) Review::query()->where('delivery_man_id', $deliveryMan->id)->count();
        $name = trim(((string) ($deliveryMan->f_name ?? '')) . ' ' . ((string) ($deliveryMan->l_name ?? '')));

        // Contact affordances are host-gated (see config('builder.capabilities.features')).
        // The storefront shows the chat icon only when id > 0 and the call icon only
        // when phone is present, so withholding them here hides the icons without
        // touching the module.
        $chatEnabled = (bool) config('builder.capabilities.features.deliveryManChat', true);
        $callEnabled = (bool) config('builder.capabilities.features.deliveryManCall', true);

        return [
            'id'          => $chatEnabled ? (int) $deliveryMan->id : 0,
            'name'        => $name !== '' ? $name : translate('Delivery_Partner'),
            'image'       => !empty($deliveryMan->image) ? getStorageImages(path: $deliveryMan->image_full_url, type: 'backend-profile') : null,
            'phone'       => $callEnabled ? ($deliveryMan->phone ?? null) : null,
            'avgRating'   => round($average, 1),
            'ratingCount' => $count,
            'trackingId'  => null,
            'thirdParty'  => false,
        ];
    }

    private function mapThirdPartyDelivery(Order $order): ?array
    {
        $serviceName = trim((string) ($order->delivery_service_name ?? ''));
        $trackingId = trim((string) ($order->third_party_delivery_tracking_id ?? ''));

        if ($serviceName === '' && $trackingId === '') {
            return null;
        }

        return [
            // No chat/call for a third-party service — id 0 and null phone hide
            // both action icons in the storefront card.
            'id'          => 0,
            'name'        => $serviceName !== '' ? $serviceName : translate('Delivery_Partner'),
            'image'       => null,
            'phone'       => null,
            'avgRating'   => 0,
            'ratingCount' => 0,
            'trackingId'  => $trackingId !== '' ? $trackingId : null,
            'thirdParty'  => true,
        ];
    }

    private function mapTracking(string $rawStatus): ?array
    {
        if (in_array($rawStatus, ['canceled', 'returned', 'failed'], true)) {
            return null;
        }

        $activeIndex = array_search($rawStatus, self::TIMELINE, true);
        $steps = [];
        foreach (self::TIMELINE as $index => $status) {
            $steps[] = [
                'key'   => $status,
                'label' => $this->statusLabel($status),
                'done'  => $activeIndex !== false && $index <= $activeIndex,
            ];
        }

        return [
            'activeStatus' => $rawStatus,
            'steps'        => $steps,
            'mapCoords'    => null,
        ];
    }

    private function statusLabel(string $status): string
    {
        return translate(self::STATUS_LABEL[$status] ?? ucwords(str_replace('_', ' ', $status)));
    }

    private function statusVariant(string $status): string
    {
        return self::STATUS_VARIANT[$status] ?? 'info';
    }

    private function paymentLabel(?string $method): ?string
    {
        if (!$method) {
            return null;
        }
        return ucwords(str_replace('_', ' ', $method));
    }

    private function cleanScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value, " \t\n\r\0\x0B\"'");
        return ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) ? null : $trimmed;
    }

    private function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
