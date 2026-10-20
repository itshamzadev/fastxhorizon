<?php

namespace App\Builder;

use App\Traits\StorageTrait;
use Modules\Builder\Contracts\MediaUrlResolver as MediaUrlResolverContract;

class MediaUrlResolver implements MediaUrlResolverContract
{
    public function defaultLogoUrl(): ?string
    {
        try {
            return getStorageImages(path: getWebConfig(name: 'company_web_logo'), type: 'logo');
        } catch (\Throwable) {
            return null;
        }
    }

    public function url(string $folder, string $filename, string $disk = 'public', ?string $type = null): string
    {
        $storage = new class {
            use StorageTrait;
        };

        return getStorageImages(path: $storage->storageLink($folder, $filename, $disk), type: $type);
    }

    public function assetUrl(string $path): string
    {
        // 6Valley's Laravel app can be served either from the project root or
        // from the nested public/ folder; dynamicAsset() strips the public/
        // prefix when DOMAIN_POINTED_DIRECTORY is 'public', so bundled assets
        // resolve correctly under both deployment layouts.
        return dynamicAsset(path: 'public/' . \ltrim($path, '/'));
    }

    public function storageBaseUrl(): string
    {
        // dynamicStorage() rewrites storage/app/public → storage under the
        // public docroot, matching how model file accessors emit media URLs.
        return dynamicStorage(path: 'storage/app/public');
    }
}
