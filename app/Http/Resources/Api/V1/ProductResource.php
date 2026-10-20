<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable public product contract for independently deployed storefronts.
 * Database columns are deliberately not exposed wholesale.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'slug' => (string) $this->slug,
            'name' => $this->name,
            'type' => (string) $this->product_type,
            'price' => (float) $this->unit_price,
            'discount' => (float) $this->discount,
            'discount_type' => $this->discount_type,
            'currency' => getWebConfig(name: 'currency_symbol') ?: 'PKR',
            'in_stock' => $this->isPurchasable(),
            'thumbnail_url' => $this->thumbnail_full_url,
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller?->id,
                'name' => trim(($this->seller?->f_name ?? '') . ' ' . ($this->seller?->l_name ?? '')),
                'shop' => $this->seller?->shop?->name,
                'shop_slug' => $this->seller?->shop?->slug,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
        ];
    }
}
