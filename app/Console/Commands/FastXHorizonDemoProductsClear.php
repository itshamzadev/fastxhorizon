<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FastXHorizonDemoProductsClear extends Command
{
    protected $signature = 'fastxhorizon:demo-products-clear';
    protected $description = 'Remove only the Fast X Horizon local demo catalog data';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Fast X Horizon demo catalog cleanup is restricted to APP_ENV=local or testing.');
        }

        DB::transaction(function (): void {
            $productIds = DB::table('products')->where('slug', 'like', 'fxh-demo-%')->pluck('id')->all();
            $dealIds = DB::table('flash_deals')->where('slug', 'fxh-demo-%')->pluck('id')->all();

            if ($dealIds) {
                DB::table('flash_deal_products')->whereIn('flash_deal_id', $dealIds)->delete();
                DB::table('translations')->where('translationable_type', 'App\\Models\\FlashDeal')->whereIn('translationable_id', $dealIds)->delete();
                DB::table('flash_deals')->whereIn('id', $dealIds)->delete();
            }

            if ($productIds) {
                foreach (['product_tag', 'product_seos', 'product_stocks', 'product_views', 'product_compares', 'wishlists', 'carts', 'restock_products', 'stock_clearance_products', 'digital_product_variations', 'digital_product_authors', 'digital_product_publishing_houses'] as $table) {
                    if (DB::getSchemaBuilder()->hasTable($table) && in_array('product_id', DB::getSchemaBuilder()->getColumnListing($table), true)) {
                        DB::table($table)->whereIn('product_id', $productIds)->delete();
                    }
                }
                DB::table('flash_deal_products')->whereIn('product_id', $productIds)->delete();
                DB::table('reviews')->whereIn('product_id', $productIds)->where('comment', 'like', '[FXH-DEMO]%')->delete();
                DB::table('translations')->where('translationable_type', 'App\\Models\\Product')->whereIn('translationable_id', $productIds)->delete();
                DB::table('taxables')->where('taxable_type', 'App\\Models\\Product')->whereIn('taxable_id', $productIds)->delete();
                DB::table('products')->whereIn('id', $productIds)->delete();
            }

            $demoCategoryIds = DB::table('categories')->where('slug', 'like', 'fxh-demo-category-%')->pluck('id')->all();
            foreach ($demoCategoryIds as $id) {
                if (! DB::table('products')->where('category_id', $id)->exists()) {
                    DB::table('categories')->where('id', $id)->delete();
                }
            }
            DB::table('translations')->where('translationable_type', 'App\\Models\\Category')->whereIn('translationable_id', $demoCategoryIds)->delete();

            $demoBrandIds = DB::table('brands')->where('slug', 'like', 'fxh-demo-brand-%')->pluck('id')->all();
            foreach ($demoBrandIds as $id) {
                if (! DB::table('products')->where('brand_id', $id)->exists()) {
                    DB::table('brands')->where('id', $id)->delete();
                }
            }

            $demoShopIds = DB::table('shops')->where('slug', 'like', 'fxh-demo-shop-%')->pluck('id')->all();
            if ($demoShopIds) {
                DB::table('shops')->whereIn('id', $demoShopIds)->delete();
            }
            $demoSellerIds = DB::table('sellers')->where('email', 'like', 'fxh-demo-seller-%')->pluck('id')->all();
            if ($demoSellerIds) {
                DB::table('sellers')->whereIn('id', $demoSellerIds)->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))->from('products')->whereColumn('products.user_id', 'sellers.id');
                })->delete();
            }

            $demoCustomerIds = DB::table('users')->where('email', 'like', 'fxh-demo-customer-%')->pluck('id')->all();
            foreach ($demoCustomerIds as $id) {
                $hasReferences = DB::table('orders')->where('customer_id', $id)->exists()
                    || DB::table('carts')->where('customer_id', $id)->exists()
                    || DB::table('wishlists')->where('customer_id', $id)->exists()
                    || DB::table('reviews')->where('customer_id', $id)->exists();
                if (! $hasReferences) {
                    DB::table('users')->where('id', $id)->delete();
                }
            }
        });

        foreach (['products', 'categories', 'brands', 'sellers', 'shops', 'reviews', 'flash_deals'] as $type) {
            if (function_exists('cacheRemoveByType')) {
                cacheRemoveByType(type: $type);
            }
        }

        $this->info('Only Fast X Horizon demo catalog rows were removed. Existing branding, shop, settings, and non-demo records were preserved.');
        return self::SUCCESS;
    }
}
