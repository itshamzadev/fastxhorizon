<?php

namespace App\Builder;

use App\Models\Seller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Modules\Builder\Contracts\StorefrontScopeResolver as StorefrontScopeResolverContract;
use Modules\Builder\Entities\TenantDomainConfig;
use Modules\Builder\ValueObjects\StorefrontScope;

class StorefrontScopeResolver implements StorefrontScopeResolverContract
{
    public function resolveFromRequest(Request $request): ?StorefrontScope
    {
        // The Builder storefront is a multi-vendor feature. In single-vendor
        // mode there are no vendor shops to serve, so it's disabled entirely.
        if (getWebConfig(name: 'business_mode') === 'single') {
            return null;
        }

        $shop = $this->resolveShop($request);

        if (!$shop) {
            return null;
        }

        // A deleted vendor (seller row gone) or an admin-suspended vendor must
        // not serve a live storefront.
        if (!$this->vendorCanServeStorefront($shop->seller_id)) {
            return null;
        }

        return new StorefrontScope(
            tenantId: $shop->seller_id,
            subTenantId: $shop->id,
            logoUrl: $this->safeLogoUrl($shop),
            displayName: $shop->name ?? $shop->slug,
            coverImageUrl: $this->safeCoverUrl($shop),
        );
    }

    private function vendorCanServeStorefront(?int $sellerId): bool
    {
        // The admin in-house shop has no seller owner — always allowed.
        if (!$sellerId) {
            return true;
        }

        $status = Seller::query()->where('id', $sellerId)->value('status');

        // Missing row = deleted vendor; 'suspended' = admin-suspended vendor.
        // Both disable the storefront; approved/pending/rejected keep prior
        // behaviour.
        return $status !== null && $status !== 'suspended';
    }

    private function resolveShop(Request $request): ?Shop
    {
        $host = $this->normalizeHost($request->getHost());

        if ($host) {
            $shop = $this->resolveShopByDomain($host);
            if ($shop) {
                return $shop;
            }
        }

        $shopIdentifier = $request->query('shop_id', $request->query('shop'));

        if (!$shopIdentifier) {
            return null;
        }

        return Shop::query()
            ->select(['id', 'seller_id', 'name', 'slug', 'image', 'image_storage_type', 'banner', 'banner_storage_type'])
            ->when(
                is_numeric($shopIdentifier),
                fn ($query) => $query->where('id', (int) $shopIdentifier),
                fn ($query) => $query->where('slug', $shopIdentifier)
            )
            ->first();
    }

    private function resolveShopByDomain(string $host): ?Shop
    {
        $domainConfig = TenantDomainConfig::query()
            ->select(['tenant_id', 'sub_tenant_id', 'website_visibility'])
            ->where(function ($query) use ($host) {
                $query->where('domain', $host);

                if (\str_starts_with($host, 'www.')) {
                    $query->orWhere('domain', \substr($host, 4));
                } else {
                    $query->orWhere('domain', 'www.' . $host);
                }
            })
            ->where(function ($query) {
                $query->where('is_connected', true)
                      ->orWhere('type', 'sub-domain');
            })
            ->first();

        if (!$domainConfig) {
            return null;
        }

        // Domain Settings → "Website Visibility" toggle. When the vendor turns
        // visibility off, refusing to resolve the scope here makes the storefront
        // 404; the vendor can still preview/edit in the BuilderSetup admin.
        if (!$domainConfig->website_visibility) {
            return null;
        }

        return Shop::query()
            ->select(['id', 'seller_id', 'name', 'slug', 'image', 'image_storage_type', 'banner', 'banner_storage_type'])
            ->where('id', $domainConfig->sub_tenant_id)
            ->where('seller_id', $domainConfig->tenant_id)
            ->first();
    }

    private function normalizeHost(?string $host): ?string
    {
        if (!$host) {
            return null;
        }

        return \strtolower(\trim($host));
    }

    private function safeLogoUrl(Shop $shop): ?string
    {
        // image_full_url resolves via storageLink(), which returns
        // ['key','path','status'] — the shop logo URL lives in 'path'
        // (null when the file is missing).
        $image = $shop->image_full_url;

        return is_array($image) ? ($image['path'] ?? null) : $image;
    }

    private function safeCoverUrl(Shop $shop): ?string
    {
        // Shop cover = banner_full_url, same storageLink() array shape as the
        // logo. Surfaced as the storefront's og:image.
        $banner = $shop->banner_full_url;

        return is_array($banner) ? ($banner['path'] ?? null) : $banner;
    }
}