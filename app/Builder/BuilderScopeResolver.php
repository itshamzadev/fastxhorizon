<?php

namespace App\Builder;

use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Modules\Builder\Contracts\BuilderScopeResolver as BuilderScopeResolverContract;
use Modules\Builder\ValueObjects\StorefrontScope;

class BuilderScopeResolver implements BuilderScopeResolverContract
{
    public function resolveFromAuth(): ?StorefrontScope
    {
        if (!Auth::guard('seller')->check()) {
            return null;
        }

        $sellerId = Auth::guard('seller')->id();
        $shop     = $this->loadShop($sellerId);

        // 6Valley is a single-catalogue e-commerce marketplace: no delivery
        // modules or zones, so moduleId/regionId stay null.
        return new StorefrontScope(
            tenantId: $sellerId,
            subTenantId: $shop?->id,
            logoUrl: $this->safeLogoUrl($shop),
            displayName: $shop?->name ?? $shop?->slug,
            coverImageUrl: $this->safeCoverUrl($shop),
        );
    }

    private function loadShop(int $sellerId): ?Shop
    {
        return Shop::query()
            ->select(['id', 'seller_id', 'name', 'slug', 'image', 'image_storage_type', 'banner', 'banner_storage_type'])
            ->where('seller_id', $sellerId)
            ->first();
    }

    private function safeLogoUrl(?Shop $shop): ?string
    {
        if (!$shop) {
            return null;
        }

        // image_full_url resolves via storageLink(), which returns
        // ['key','path','status'] — the shop logo URL lives in 'path'
        // (null when the file is missing).
        $image = $shop->image_full_url;

        return is_array($image) ? ($image['path'] ?? null) : $image;
    }

    private function safeCoverUrl(?Shop $shop): ?string
    {
        if (!$shop) {
            return null;
        }

        // Shop cover = banner_full_url, same storageLink() array shape as the
        // logo. Surfaced as the storefront's og:image.
        $banner = $shop->banner_full_url;

        return is_array($banner) ? ($banner['path'] ?? null) : $banner;
    }
}
