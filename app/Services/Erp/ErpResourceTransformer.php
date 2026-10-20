<?php

namespace App\Services\Erp;

use App\Models\RefundRequest;
use App\Models\Seller;
use App\Models\Shop;

/**
 * Shapes domain models into the exact payloads defined by the ERP integration
 * contract. The REST endpoints and the observer-driven webhooks both call these
 * methods, so the API responses and webhook `data` objects stay byte-for-byte
 * identical and only need to change in one place.
 */
class ErpResourceTransformer
{
    public static function vendor(Seller $seller): array
    {
        $shop = $seller->shop;

        return [
            'id' => (string)$seller->id,
            'name' => $shop->name ?? 'Unknown Vendor',
            'email' => $seller->email,
            'phone' => $seller->phone,
            'status' => $seller->status === 'approved' ? 1 : 0,
            'address' => $shop->address ?? null,
            'logo_full_url' => self::imageUrl($shop?->image_full_url),
            'cover_photo_full_url' => self::imageUrl($shop?->banner_full_url),
            'rating' => self::vendorRating($seller),
            'total_order' => self::vendorOrderCount($seller),
            'order_count' => self::vendorOrderCount($seller),
            'comission' => $seller->sales_commission_percentage,
            'zone_id' => null,
            'vendor' => [
                'f_name' => $seller->f_name,
                'l_name' => $seller->l_name,
                'email' => $seller->email,
                'phone' => $seller->phone,
            ],
            'module' => null,
            'zone' => null,
        ];
    }

    public static function refund(RefundRequest $refund): array
    {
        $order = $refund->order;
        $store = $order?->seller?->shop;
        $customer = $order?->customer;

        return [
            'id' => (string)$refund->id,
            'order_id' => $refund->order_id,
            'user_id' => $refund->customer_id,
            'refund_amount' => $refund->amount,
            'refund_status' => $refund->status,
            'refund_method' => $refund->payment_info,
            'customer_reason' => $refund->refund_reason,
            'customer_note' => null,
            'admin_note' => $refund->approved_note ?? $refund->rejected_note,
            'image_full_url' => self::imageUrl($refund->images_full_url[0] ?? null),
            'order' => $order ? [
                'order_amount' => $order->order_amount,
                'order_status' => $order->order_status,
                'store' => [
                    'name' => $store->name ?? null,
                ],
                'customer' => [
                    'id' => $customer->id ?? null,
                    'email' => $customer->email ?? null,
                    'f_name' => $customer->f_name ?? null,
                    'l_name' => $customer->l_name ?? null,
                    'phone' => $customer->phone ?? null,
                ],
            ] : null,
        ];
    }

    /**
     * The project's *_full_url accessors return a {key, path, status} structure;
     * `path` is the resolvable URL (or null when the file is missing).
     */
    private static function imageUrl(array|string|null $fullUrl): ?string
    {
        if (is_array($fullUrl)) {
            return $fullUrl['path'] ?? null;
        }
        return $fullUrl;
    }

    private static function vendorRating(Seller $seller): ?float
    {
        if (array_key_exists('reviews_avg_rating', $seller->getAttributes())) {
            return is_null($seller->reviews_avg_rating) ? null : round((float)$seller->reviews_avg_rating, 2);
        }

        $average = $seller->reviews()->withoutGlobalScope('active')->where('reviews.status', 1)->avg('rating');
        return is_null($average) ? null : round((float)$average, 2);
    }

    private static function vendorOrderCount(Seller $seller): int
    {
        if (array_key_exists('orders_count', $seller->getAttributes())) {
            return (int)$seller->orders_count;
        }
        return $seller->orders()->count();
    }

    public static function shop(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
        ];
    }
}
