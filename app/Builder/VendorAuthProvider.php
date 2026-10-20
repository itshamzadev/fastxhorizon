<?php

namespace App\Builder;

use Illuminate\Support\Facades\Auth;
use Modules\Builder\Contracts\VendorAuthProvider as VendorAuthProviderContract;

/**
 * 6Valley host adapter for VendorAuthProvider.
 *
 * The vendor panel authenticates sellers through the `seller` guard, so the
 * BuilderSetup editor resolves the logged-in seller for its profile dropdown.
 * The seller's `image_full_url` accessor returns a storage-link array, so it is
 * normalised to a string URL via getStorageImages() and only surfaced when the
 * seller actually has an avatar (the UI falls back to initials otherwise).
 */
class VendorAuthProvider implements VendorAuthProviderContract
{
    public function current(): ?array
    {
        $seller = Auth::guard('seller')->user();

        if (!$seller) {
            return null;
        }

        $name = \trim(($seller->f_name ?? '') . ' ' . ($seller->l_name ?? ''));

        return [
            'name'      => $name !== '' ? $name : ($seller->email ?? 'Vendor'),
            'email'     => $seller->email ?? null,
            'image_url' => $this->safeImageUrl($seller),
        ];
    }

    public function logoutUrl(): string
    {
        // The vendor panel exposes the seller logout under the
        // `vendor.auth.logout` route. Fall back to a stable path so the
        // dropdown link still navigates if a future host renames the route.
        try {
            return \route('vendor.auth.logout');
        } catch (\Throwable) {
            return \url('/vendor/auth/login');
        }
    }

    private function safeImageUrl($seller): ?string
    {
        if (empty($seller->image)) {
            return null;
        }

        try {
            return getStorageImages(path: $seller->image_full_url, type: 'backend-profile');
        } catch (\Throwable) {
            return null;
        }
    }
}
