<?php

namespace App\Builder;

use App\Models\Order;
use App\Models\Review;
use App\Traits\FileManagerTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Builder\Contracts\ReviewProvider as ReviewProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for customer reviews against delivered orders.
 *
 * Mirrors the host's Web\ReviewController: reviews live in the `reviews` table
 * keyed by a composite string id (`order_id` + zero-padded sequence), item and
 * delivery-man reviews share the table (delivery_man_id distinguishes them),
 * attachments are stored as `[{file_name, storage}]`, and product/shop ratings
 * are computed live from the reviews — so there is no denorm chain to maintain.
 * Ownership is enforced here (host controller trusts the request), and the
 * side-effect is wrapped in a transaction.
 */
class ReviewProvider implements ReviewProviderContract
{
    use FileManagerTrait;

    public function reviewContext(?StorefrontScope $scope, int $orderId, int $customerId): ?array
    {
        $order = $this->loadOrder($orderId, $customerId);
        if (!$order) {
            return null;
        }

        // Full review rows keyed by product so a reviewed item can render its
        // submitted rating/comment/images read-only ("review done" + view it).
        $reviewRows = Review::query()
            ->where('customer_id', $customerId)
            ->where('order_id', $orderId)
            ->whereNotNull('product_id')
            ->get(['id', 'product_id', 'rating', 'comment', 'attachment'])
            ->keyBy('product_id');

        $itemsById = [];
        foreach ($order->details as $detail) {
            $productId = (int) ($detail->product_id ?? 0);
            if ($productId <= 0 || isset($itemsById[$productId])) {
                continue;
            }
            $itemsById[$productId] = $this->mapItemForReview($detail, $reviewRows[$productId] ?? null);
        }
        $items = array_values($itemsById);

        $deliveryMan = null;
        if ($order->delivery_man_id && $order->deliveryMan) {
            $dmReview = Review::query()
                ->where('customer_id', $customerId)
                ->where('order_id', $orderId)
                ->where('delivery_man_id', $order->delivery_man_id)
                ->first(['rating', 'comment', 'attachment']);
            $deliveryMan = $this->mapDeliveryManForReview($order->deliveryMan, $dmReview);
        }

        $allItemsReviewed = collect($items)->every(fn ($i) => $i['reviewed']);
        $dmDone = $deliveryMan === null || $deliveryMan['reviewed'];

        return [
            'orderId'     => (int) $order->id,
            'items'       => $items,
            'deliveryMan' => $deliveryMan,
            'allReviewed' => $allItemsReviewed && $dmDone,
        ];
    }

    public function submitItemReview(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        int $itemId,
        int $rating,
        ?string $comment,
        array $imageFiles = [],
    ): array {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => translate('Rating_must_be_between_1_and_5') ?: 'Rating must be between 1 and 5.'];
        }

        $order = $this->loadOrder($orderId, $customerId);
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }

        $alreadyReviewed = Review::query()
            ->where('product_id', $itemId)
            ->where('customer_id', $customerId)
            ->where('order_id', $orderId)
            ->exists();
        if ($alreadyReviewed) {
            return ['success' => false, 'error' => translate('You_have_already_reviewed_this_item') ?: 'You have already reviewed this item.'];
        }

        $orderProductIds = $order->details->pluck('product_id')->map(fn ($v) => (int) $v)->all();
        if (!in_array($itemId, $orderProductIds, true)) {
            return ['success' => false, 'error' => translate('This_item_is_not_part_of_the_order') ?: 'This item is not part of the order.'];
        }

        $attachments = $this->uploadAttachments($imageFiles, 'item review');

        try {
            $reviewId = DB::transaction(function () use ($order, $customerId, $itemId, $orderId, $rating, $comment, $attachments) {
                $reviewId = $this->generateReviewId($orderId);

                Review::create([
                    'id' => $reviewId,
                    'customer_id' => $customerId,
                    'product_id' => $itemId,
                    'order_id' => $orderId,
                    'comment' => $comment !== null && $comment !== '' ? $comment : null,
                    'rating' => $rating,
                    'attachment' => $attachments,
                ]);

                return $reviewId;
            });
        } catch (\Throwable $exception) {
            Log::warning('Storefront item review submit failed', ['order_id' => $orderId, 'product_id' => $itemId, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => translate('Could_not_submit_the_review') ?: 'Could not submit the review.'];
        }

        return ['success' => true, 'message' => translate('successfully_added_review') ?: 'Thanks for the review!', 'reviewId' => (string) $reviewId];
    }

    public function submitDeliveryManReview(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        int $rating,
        string $comment,
        array $imageFiles = [],
    ): array {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => translate('Rating_must_be_between_1_and_5') ?: 'Rating must be between 1 and 5.'];
        }
        $comment = trim($comment);
        if ($comment === '') {
            return ['success' => false, 'error' => translate('Please_share_your_opinion_before_submitting') ?: 'Please share your opinion before submitting.'];
        }

        $order = $this->loadOrder($orderId, $customerId);
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }
        if (!$order->delivery_man_id) {
            return ['success' => false, 'error' => translate('No_delivery_partner_is_assigned_to_this_order') ?: 'No delivery partner is assigned to this order.'];
        }

        $deliveryManId = (int) $order->delivery_man_id;

        $alreadyReviewed = Review::query()
            ->where('delivery_man_id', $deliveryManId)
            ->where('customer_id', $customerId)
            ->where('order_id', $orderId)
            ->exists();
        if ($alreadyReviewed) {
            return ['success' => false, 'error' => translate('You_have_already_reviewed_this_delivery_partner') ?: 'You have already reviewed this delivery partner.'];
        }

        $attachments = $this->uploadAttachments($imageFiles, 'dm review');

        try {
            DB::transaction(function () use ($order, $customerId, $deliveryManId, $orderId, $rating, $comment, $attachments) {
                Review::create([
                    'id' => $this->generateReviewId($orderId),
                    'customer_id' => $customerId,
                    'delivery_man_id' => $deliveryManId,
                    'order_id' => $orderId,
                    'comment' => $comment,
                    'rating' => $rating,
                    'attachment' => $attachments,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::warning('Storefront DM review submit failed', ['order_id' => $orderId, 'delivery_man_id' => $deliveryManId, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => translate('Could_not_submit_the_review') ?: 'Could not submit the review.'];
        }

        return ['success' => true, 'message' => translate('successfully_added_review') ?: 'Thanks for the review!'];
    }

    private function loadOrder(int $orderId, int $customerId): ?Order
    {
        return Order::query()
            ->with(['details.product', 'deliveryMan'])
            ->where('id', $orderId)
            ->where('customer_id', $customerId)
            ->where('is_guest', 0)
            ->where('order_status', 'delivered')
            ->first();
    }

    /**
     * Composite review id: order id followed by a zero-padded per-order
     * sequence — identical to the host's Web\ReviewController scheme so
     * storefront and legacy-site reviews interleave without collision.
     */
    private function generateReviewId(int $orderId): string
    {
        $existing = Review::query()->where('order_id', $orderId)->count();
        $sequence = $existing < 10 ? '0' . ($existing + 1) : (string) ($existing + 1);

        return $orderId . $sequence;
    }

    private function mapItemForReview($detail, ?Review $review): array
    {
        $productId = (int) ($detail->product_id ?? 0);
        $snapshot = is_string($detail->product_details ?? null)
            ? (json_decode($detail->product_details, true) ?: [])
            : (is_array($detail->product_details ?? null) ? $detail->product_details : []);

        $name = $detail->product->name ?? ($snapshot['name'] ?? translate('Product'));

        $image = null;
        try {
            $image = $detail->product
                ? getStorageImages(path: $detail->product->thumbnail_full_url, type: 'product')
                : null;
        } catch (\Throwable) {
            $image = null;
        }

        $reviewed = $review !== null;

        return [
            'id'        => $productId,
            'name'      => (string) $name,
            'image'     => $image,
            'qty'       => (int) ($detail->qty ?? 0),
            'unitPrice' => (float) webCurrencyConverterOnlyDigit(amount: (float) ($detail->price ?? 0)),
            'reviewed'  => $reviewed,
            'reviewId'  => $reviewed ? (string) $review->id : null,
            'rating'    => $reviewed ? (int) $review->rating : 0,
            'comment'   => $reviewed ? (string) ($review->comment ?? '') : '',
            'images'    => $reviewed ? $this->reviewImageUrls($review) : [],
        ];
    }

    private function mapDeliveryManForReview($deliveryMan, ?Review $review): array
    {
        $average = (float) Review::query()->where('delivery_man_id', $deliveryMan->id)->avg('rating');
        $count = (int) Review::query()->where('delivery_man_id', $deliveryMan->id)->count();

        $name = trim(((string) ($deliveryMan->f_name ?? '')) . ' ' . ((string) ($deliveryMan->l_name ?? '')));
        if ($name === '') {
            $name = translate('Delivery_Partner') ?: 'Delivery Partner';
        }

        $reviewed = $review !== null;

        return [
            'id'          => (int) $deliveryMan->id,
            'name'        => $name,
            'image'       => $this->safeImage($deliveryMan),
            'avgRating'   => round($average, 1),
            'ratingCount' => $count,
            'reviewed'    => $reviewed,
            'rating'      => $reviewed ? (int) $review->rating : 0,
            'comment'     => $reviewed ? (string) ($review->comment ?? '') : '',
            'images'      => $reviewed ? $this->reviewImageUrls($review) : [],
        ];
    }

    /**
     * Resolve a review's stored attachments to display URLs — same shape the
     * product-detail review list uses (Review::attachment_full_url).
     *
     * @return string[]
     */
    private function reviewImageUrls(Review $review): array
    {
        return collect($review->attachment_full_url ?? [])
            ->map(fn ($image) => getStorageImages(path: $image, type: 'backend-basic'))
            ->filter()
            ->values()
            ->all();
    }

    private function safeImage($model): ?string
    {
        if (empty($model->image)) {
            return null;
        }
        try {
            return getStorageImages(path: $model->image_full_url, type: 'backend-profile');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Upload each attachment via the host FileManagerTrait, storing the same
     * `[{file_name, storage}]` shape the host review flow persists.
     *
     * @return array<int,array{file_name:string,storage:string}>
     */
    private function uploadAttachments(array $imageFiles, string $logContext): array
    {
        $storage = getWebConfig(name: 'storage_connection_type') ?? 'public';
        $paths = [];
        foreach ($imageFiles as $file) {
            if (!$file) {
                continue;
            }
            try {
                $paths[] = [
                    'file_name' => $this->upload(dir: 'review/', format: 'webp', image: $file),
                    'storage' => $storage,
                ];
            } catch (\Throwable $exception) {
                Log::warning("{$logContext} attachment upload failed", ['error' => $exception->getMessage()]);
            }
        }
        return $paths;
    }
}
