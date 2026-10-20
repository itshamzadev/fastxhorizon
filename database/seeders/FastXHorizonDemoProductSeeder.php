<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FastXHorizonDemoProductSeeder extends Seeder
{
    private const PRODUCT_PREFIX = 'fxh-demo-';
    private const SELLER_EMAIL_PREFIX = 'fxh-demo-seller-';
    private const CUSTOMER_EMAIL_PREFIX = 'fxh-demo-customer-';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Fast X Horizon demo catalog is restricted to APP_ENV=local or testing.');
        }

        $inHouseShop = DB::table('shops')
            ->where('author_type', 'admin')
            ->where('seller_id', 0)
            ->first();

        if (! $inHouseShop) {
            throw new RuntimeException('The existing Fast X Horizon in-house shop was not found; no demo data was written.');
        }

        $now = Carbon::now();
        $catalog = $this->catalog();
        $this->seedBrandLogos();
        $categoryIds = [];
        $subcategoryIds = [];
        $brandIds = [];
        $sellerShops = [];
        $customerIds = [];
        $productIds = [];
        $expectedSlugs = [];
        $saleCount = 0;
        $variantCount = 0;
        $outOfStockCount = 0;
        $galleryCount = 0;

        DB::transaction(function () use (
            $catalog,
            $inHouseShop,
            $now,
            &$categoryIds,
            &$subcategoryIds,
            &$brandIds,
            &$sellerShops,
            &$customerIds,
            &$productIds,
            &$expectedSlugs,
            &$saleCount,
            &$variantCount,
            &$outOfStockCount,
            &$galleryCount
        ): void {
            foreach ($catalog as $category) {
                DB::table('categories')->updateOrInsert(
                    ['slug' => $category['slug']],
                    [
                        'name' => $category['name'],
                        'icon' => 'fxh-demo-category-' . ($category['image_key'] ?? $category['key']) . '.webp',
                        'icon_storage_type' => 'public',
                        'parent_id' => 0,
                        'position' => 0,
                        // Keep the demo homepage bounded: the installed 6Valley
                        // home-category cache preloads every product for each
                        // category marked for the homepage.
                        'home_status' => in_array($category['key'], ['electronics', 'fashion', 'home-living', 'digital'], true) ? 1 : 0,
                        'auction_home_status' => 0,
                        'priority' => $category['priority'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $categoryIds[$category['key']] = DB::table('categories')->where('slug', $category['slug'])->value('id');
            }

            $categoryIds = array_merge($categoryIds, $this->seedAdditionalCategories($now));

            $subcategoryIds = $this->seedSubcategories($categoryIds, $now);
            $this->seedAttributes($now);

            foreach ($this->brands() as $brand) {
                DB::table('brands')->updateOrInsert(
                    ['slug' => $brand['slug']],
                    [
                        'name' => $brand['name'],
                        'image' => 'fxh-demo-brands/' . $brand['key'] . '.svg',
                        'image_storage_type' => 'public',
                        'image_alt_text' => $brand['name'] . ' demo brand',
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $brandIds[$brand['key']] = DB::table('brands')->where('slug', $brand['slug'])->value('id');
            }

            $this->removeObsoleteDemoShops($inHouseShop->id);

            foreach ($this->demoSellers($now) as $seller) {
                DB::table('sellers')->updateOrInsert(
                    ['email' => $seller['email']],
                    [
                        'f_name' => $seller['f_name'],
                        'l_name' => $seller['l_name'],
                        'phone' => $seller['phone'],
                        'image' => 'fxh-brand.svg',
                        'email' => $seller['email'],
                        'password' => Hash::make('fxh-demo-only'),
                        'status' => 'approved',
                        'sales_commission_percentage' => 10,
                        'gst' => '0',
                        'pos_status' => 1,
                        'minimum_order_amount' => 0,
                        'free_delivery_status' => 0,
                        'free_delivery_over_amount' => 0,
                        'stock_limit' => 0,
                        'app_language' => 'en',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $sellerId = DB::table('sellers')->where('email', $seller['email'])->value('id');
                $shopData = [
                    'seller_id' => $sellerId,
                    'author_type' => 'vendor',
                    'name' => $seller['shop_name'],
                    'description' => $seller['description'],
                    'slug' => $seller['shop_slug'],
                    'address' => 'Fast X Horizon Demo Market',
                    'contact' => $seller['phone'],
                    'image' => $seller['logo'],
                    'image_storage_type' => 'public',
                    'banner' => $seller['banner'],
                    'banner_storage_type' => 'public',
                    'vacation_status' => 0,
                    'temporary_close' => 0,
                    'updated_at' => $now,
                ];
                $existingShopId = DB::table('shops')
                    ->where('seller_id', $sellerId)
                    ->where('author_type', 'vendor')
                    ->value('id');
                if ($existingShopId) {
                    DB::table('shops')->where('id', $existingShopId)->update($shopData);
                } else {
                    $shopData['created_at'] = $now;
                    $existingShopId = DB::table('shops')->insertGetId($shopData);
                }
                $sellerShops[] = [
                    'seller_id' => $sellerId,
                    'shop_id' => $existingShopId,
                ];
            }

            foreach ($this->demoCustomers($now) as $customer) {
                DB::table('users')->updateOrInsert(
                    ['email' => $customer['email']],
                    [
                        'name' => $customer['f_name'] . ' ' . $customer['l_name'],
                        'f_name' => $customer['f_name'],
                        'l_name' => $customer['l_name'],
                        'phone' => $customer['phone'],
                        'image' => 'def.png',
                        'email' => $customer['email'],
                        'password' => Hash::make('fxh-demo-only'),
                        'is_active' => 1,
                        'is_email_verified' => 1,
                        'is_phone_verified' => 1,
                        'app_language' => 'en',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $customerIds[] = DB::table('users')->where('email', $customer['email'])->value('id');
            }

            $index = 0;
            foreach ($catalog as $category) {
                foreach ($category['items'] as $itemIndex => $item) {
                    $index++;
                    $slug = Str::slug(self::PRODUCT_PREFIX . str_pad((string)$index, 3, '0', STR_PAD_LEFT) . '-' . $item['name']);
                    $expectedSlugs[] = $slug;
                    $isDigital = ($item['product_type'] ?? ($category['key'] === 'digital' ? 'digital' : 'physical')) === 'digital';
                    $isVariant = !$isDigital && $index > 60;
                    $discount = $index <= 30 ? 0 : ($index <= 80 ? 5 + (($index * 3) % 21) : 25 + (($index * 7) % 21));
                    $isOutOfStock = !$isDigital && $index % 25 === 0;
                    $stock = $isOutOfStock ? 0 : (($index % 7 === 0) ? 3 + ($index % 5) : 20 + (($index * 13) % 81));
                    $choice = $this->choiceFor($category['key'], $item);
                    $variations = [];

                    if ($isVariant) {
                        $variantStep = max(150, (int)round($item['price'] * 0.04));
                        $remaining = $stock;
                        foreach ($choice['options'] as $optionIndex => $option) {
                            $quantity = $isOutOfStock ? 0 : ($optionIndex === count($choice['options']) - 1 ? $remaining : max(0, (int)floor($stock / count($choice['options']))));
                            $remaining -= $quantity;
                            $variations[] = [
                                'type' => str_replace(' ', '', $option),
                                'price' => $item['price'] + ($optionIndex * $variantStep),
                                'sku' => 'FXH' . str_pad((string)$index, 3, '0', STR_PAD_LEFT) . str_pad((string)($optionIndex + 1), 2, '0', STR_PAD_LEFT),
                                'qty' => $quantity,
                            ];
                        }
                        $variantCount++;
                        $stock = array_sum(array_column($variations, 'qty'));
                    }

                    $isInHouse = $index <= 10 || $category['key'] === 'automotive' || ($item['in_house'] ?? false);
                    $seller = $isInHouse ? null : $sellerShops[$item['seller_index'] ?? $this->sellerIndexFor($category, $item)];
                    $subCategoryId = $this->subcategoryFor($category['key'], $item, $subcategoryIds);
                    // Every catalog item receives its own deterministic local
                    // primary image. This keeps the demo visually varied and
                    // makes repeated seeding stable.
                    $imageVariant = $itemIndex + 1;
                    $primaryImage = $this->demoImageFor($category, $item, $imageVariant);
                    $images = [['image_name' => $primaryImage, 'storage' => 'public']];
                    if ($index % 5 === 0) {
                        $alternateVariant = $imageVariant === 1 ? 2 : 1;
                        $images[] = ['image_name' => $this->demoImageFor($category, $item, $alternateVariant), 'storage' => 'public'];
                        $galleryCount++;
                    }

                    if ($discount > 0) {
                        $saleCount++;
                    }
                    if ($isOutOfStock) {
                        $outOfStockCount++;
                    }

                    $data = [
                        'added_by' => $isInHouse ? 'admin' : 'seller',
                        'user_id' => $isInHouse ? null : $seller['seller_id'],
                        'shop_id' => $isInHouse ? $inHouseShop->id : $seller['shop_id'],
                        'name' => $item['name'],
                        'slug' => $slug,
                        'product_type' => $isDigital ? 'digital' : 'physical',
                        'category_ids' => json_encode(array_values(array_filter(array_merge([
                            ['id' => $categoryIds[$category['key']], 'position' => 1],
                            $subCategoryId ? ['id' => $subCategoryId, 'position' => 2] : null,
                        ], $this->additionalCategoryLinks($category, $item, $categoryIds))))),
                        'category_id' => $categoryIds[$category['key']],
                        'sub_category_id' => $subCategoryId,
                        'sub_sub_category_id' => null,
                        'brand_id' => $brandIds[$item['brand']],
                        'unit' => 'pc',
                        'min_qty' => 1,
                        'refundable' => $isDigital ? 0 : 1,
                        'digital_product_type' => $isDigital ? 'ready_product' : null,
                        'digital_file_ready' => $isDigital ? 'fxh-demo-digital-sample.pdf' : null,
                        'digital_file_ready_storage_type' => 'public',
                        'images' => json_encode($images),
                        'color_image' => json_encode([]),
                        'thumbnail' => $primaryImage,
                        'thumbnail_storage_type' => 'public',
                        'preview_file' => null,
                        'preview_file_storage_type' => 'public',
                        'featured' => $index % 5 === 0 ? 1 : 0,
                        'flash_deal' => $index >= 11 && $index <= 28 ? 1 : 0,
                        'video_provider' => null,
                        'video_url' => null,
                        'colors' => json_encode([]),
                        'variant_product' => $isVariant ? 1 : 0,
                        'attributes' => json_encode($isVariant ? [0] : []),
                        'choice_options' => json_encode($isVariant ? [[
                            'name' => 'choice_0',
                            'title' => $choice['title'],
                            'options' => $choice['options'],
                        ]] : []),
                        'variation' => json_encode($variations),
                        'digital_product_file_types' => json_encode($isDigital ? ['document'] : []),
                        'digital_product_extensions' => json_encode($isDigital ? ['document' => ['pdf']] : []),
                        'published' => 1,
                        'unit_price' => $item['price'],
                        'purchase_price' => round($item['price'] * 0.65, 2),
                        'tax' => 0,
                        'tax_type' => 'percent',
                        'tax_model' => 'exclude',
                        'discount' => $discount,
                        'discount_type' => 'percent',
                        'current_stock' => $stock,
                        'minimum_order_qty' => 1,
                        'details' => $item['details'],
                        'free_shipping' => $isDigital ? 0 : ($index % 4 === 0 ? 1 : 0),
                        'attachment' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'status' => 1,
                        'featured_status' => 1,
                        'meta_title' => $item['name'] . ' | Fast X Horizon',
                        'meta_description' => $item['short_description'],
                        'meta_image' => null,
                        'request_status' => 1,
                        'denied_note' => null,
                        'shipping_cost' => 0,
                        'multiply_qty' => 0,
                        'temp_shipping_cost' => 0,
                        'is_shipping_cost_updated' => 0,
                        'code' => 'FXHDEM' . str_pad((string)$index, 4, '0', STR_PAD_LEFT),
                    ];

                    DB::table('products')->updateOrInsert(['slug' => $slug], $data);
                    $productId = DB::table('products')->where('slug', $slug)->value('id');
                    $productIds[$index] = $productId;

                    DB::table('product_seos')->updateOrInsert(
                        ['product_id' => $productId],
                        [
                            'title' => $item['name'] . ' | Fast X Horizon',
                            'description' => $item['short_description'],
                            'index' => 'index',
                            'no_follow' => '0',
                            'no_image_index' => '0',
                            'no_archive' => '0',
                            'no_snippet' => '0',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }

            $staleIds = DB::table('products')
                ->where('slug', 'like', self::PRODUCT_PREFIX . '%')
                ->whereNotIn('slug', $expectedSlugs)
                ->pluck('id')
                ->all();
            $this->deleteProductRelations($staleIds);

            $flashDealId = DB::table('flash_deals')->updateOrInsert(
                ['slug' => 'fxh-demo-flash-deal'],
                [
                    'title' => 'Fast X Horizon Demo Flash Deal',
                    'start_date' => $now->copy()->subDay()->toDateString(),
                    'end_date' => $now->copy()->addDays(30)->toDateString(),
                    'status' => 1,
                    'featured' => 1,
                    'background_color' => '#1769E0',
                    'text_color' => '#FFFFFF',
                    'banner' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deal_type' => 'flash_deal',
                ]
            );
            $flashDealId = DB::table('flash_deals')->where('slug', 'fxh-demo-flash-deal')->value('id');
            foreach (range(11, 28) as $index) {
                DB::table('flash_deal_products')->updateOrInsert(
                    ['flash_deal_id' => $flashDealId, 'product_id' => $productIds[$index]],
                    [
                        'discount' => 15 + ($index % 6),
                        'discount_type' => 'percent',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $reviewProductIds = array_values(array_filter($productIds, fn ($id, $index) => $index <= 60, ARRAY_FILTER_USE_BOTH));
            DB::table('reviews')
                ->whereIn('product_id', $reviewProductIds)
                ->whereIn('customer_id', $customerIds)
                ->delete();

            foreach ($reviewProductIds as $reviewIndex => $productId) {
                $rating = [5, 4, 4, 3, 5, 4][$reviewIndex % 6];
                $this->insertDemoReview($productId, $customerIds[$reviewIndex % count($customerIds)], $rating, $now);
                if (($reviewIndex + 1) % 5 === 0) {
                    $this->insertDemoReview($productId, $customerIds[($reviewIndex + 1) % count($customerIds)], max(3, $rating - 1), $now);
                }
            }

            $this->removeObsoleteDemoSellers();
        });

        $this->clearCatalogCaches();
        $this->command?->info('Fast X Horizon demo catalog seeded: ' . count($productIds) . ' products.');
        $this->command?->line('Categories: 14 top-level + 46 subcategories | Brands: ' . count($brandIds) . ' | Demo sellers: 6 | Review customers: 3');
        $this->command?->line('Simple products: ' . (count($productIds) - $variantCount) . ' | Variant products: ' . $variantCount);
        $this->command?->line('Sale products: ' . $saleCount . ' | No-discount products: ' . (count($productIds) - $saleCount));
        $this->command?->line('Out-of-stock products: ' . $outOfStockCount . ' | Gallery products: ' . $galleryCount . ' | Flash-deal products: 18');
    }

    private function seedBrandLogos(): void
    {
        foreach ($this->brands() as $brand) {
            $initials = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $brand['name']), 0, 2));
            $color = '#' . substr(sha1($brand['key']), 0, 6);
            $name = htmlspecialchars($brand['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 160">'
                . '<rect width="320" height="160" rx="28" fill="' . $color . '"/>'
                . '<circle cx="76" cy="80" r="38" fill="rgba(255,255,255,.2)"/>'
                . '<text x="76" y="91" text-anchor="middle" font-family="Arial,sans-serif" font-size="28" font-weight="700" fill="#fff">' . $initials . '</text>'
                . '<text x="132" y="88" font-family="Arial,sans-serif" font-size="22" font-weight="700" fill="#fff">' . $name . '</text>'
                . '</svg>';
            Storage::disk('public')->put('brand/fxh-demo-brands/' . $brand['key'] . '.svg', $svg);
        }
    }

    private function removeObsoleteDemoShops(int $inHouseShopId): void
    {
        $currentEmails = collect($this->demoSellers(Carbon::now()))
            ->pluck('email')
            ->all();
        $obsoleteSellerIds = DB::table('sellers')
            ->where('email', 'like', self::SELLER_EMAIL_PREFIX . '%')
            ->whereNotIn('email', $currentEmails)
            ->pluck('id')
            ->all();

        if (empty($obsoleteSellerIds)) {
            return;
        }

        $obsoleteShopIds = DB::table('shops')
            ->whereIn('seller_id', $obsoleteSellerIds)
            ->pluck('id')
            ->all();
        if (! empty($obsoleteShopIds)) {
            DB::table('products')
                ->whereIn('shop_id', $obsoleteShopIds)
                ->where('slug', 'like', self::PRODUCT_PREFIX . '%')
                ->update([
                    'shop_id' => $inHouseShopId,
                    'user_id' => null,
                    'added_by' => 'admin',
                ]);
            DB::table('shops')->whereIn('id', $obsoleteShopIds)->delete();
        }
    }

    private function removeObsoleteDemoSellers(): void
    {
        $currentEmails = collect($this->demoSellers(Carbon::now()))
            ->pluck('email')
            ->all();
        $obsoleteSellerIds = DB::table('sellers')
            ->where('email', 'like', self::SELLER_EMAIL_PREFIX . '%')
            ->whereNotIn('email', $currentEmails)
            ->pluck('id');

        foreach ($obsoleteSellerIds as $sellerId) {
            $hasShop = DB::table('shops')->where('seller_id', $sellerId)->exists();
            $hasProduct = DB::table('products')->where('user_id', $sellerId)->exists();
            $hasOrder = DB::table('orders')->where('seller_id', $sellerId)->exists();
            if (! $hasShop && ! $hasProduct && ! $hasOrder) {
                DB::table('sellers')->where('id', $sellerId)->delete();
            }
        }
    }

    private function catalog(): array
    {
        $templates = [
            'electronics' => 'Designed for reliable everyday connectivity with practical controls, durable materials, and a clean setup for work, travel, or home use.',
            'fashion' => 'Made for comfortable everyday wear with an easy-care finish, versatile styling, and a practical fit for casual routines.',
            'home-living' => 'A useful home upgrade with a clean look, dependable construction, and simple features that fit naturally into daily living spaces.',
            'beauty' => 'A straightforward personal-care option with a comfortable texture, convenient packaging, and an easy routine-friendly format.',
            'grocery' => 'A pantry-friendly everyday essential selected for convenient storage, simple preparation, and dependable household use.',
            'accessories' => 'A practical accessory with a compact design, useful materials, and an easy fit for commuting, travel, or everyday carry.',
            'sports' => 'Built for regular activity with comfortable handling, durable construction, and a practical form for home or outdoor training.',
            'toys' => 'A screen-light play option with engaging hands-on features, sturdy materials, and age-appropriate everyday use.',
            'automotive' => 'A practical vehicle accessory made for convenient installation, routine care, and dependable use on everyday journeys.',
            'essentials' => 'A versatile daily-use item with a compact, thoughtful design and durable materials for home, work, or travel.',
            'digital' => 'An instant digital resource supplied for practical creative, business, learning, or productivity use.',
        ];

        $definitions = [
            ['key' => 'electronics', 'name' => 'Electronics', 'image_key' => 'electronics', 'priority' => 1, 'template' => $templates['electronics'], 'items' => [
                ['name' => 'Wireless ANC Headphones Pro', 'price' => 8990, 'brand' => 'novagear'], ['name' => 'Smart Fitness Watch S2', 'price' => 7490, 'brand' => 'aeropulse'], ['name' => 'Portable Bluetooth Speaker Mini', 'price' => 3990, 'brand' => 'novagear'], ['name' => 'USB-C 65W Fast Charger', 'price' => 2490, 'brand' => 'aeropulse'], ['name' => 'Mechanical RGB Keyboard', 'price' => 6990, 'brand' => 'novagear'], ['name' => '1080p USB Streaming Camera', 'price' => 5490, 'brand' => 'aeropulse'], ['name' => 'Compact Power Bank 20000mAh', 'price' => 4990, 'brand' => 'novagear'], ['name' => 'Dual-Band Wi-Fi Router AX1800', 'price' => 18990, 'brand' => 'aeropulse'], ['name' => 'Ergonomic Wireless Mouse', 'price' => 2290, 'brand' => 'novagear'], ['name' => 'Smart LED Desk Light', 'price' => 3290, 'brand' => 'aeropulse'],
            ]],
            ['key' => 'fashion', 'name' => 'Fashion', 'image_key' => 'fashion', 'priority' => 2, 'template' => $templates['fashion'], 'items' => [
                ['name' => 'Everyday Cotton Hoodie', 'price' => 3490, 'brand' => 'urbanthread'], ['name' => 'Classic Running Sneakers', 'price' => 6990, 'brand' => 'velofit'], ['name' => 'Relaxed Linen Blend Shirt', 'price' => 3290, 'brand' => 'urbanthread'], ['name' => 'High-Rise Straight Leg Jeans', 'price' => 5990, 'brand' => 'urbanthread'], ['name' => 'Soft Knit Cardigan', 'price' => 4290, 'brand' => 'urbanthread'], ['name' => 'Lightweight Puffer Vest', 'price' => 7990, 'brand' => 'urbanthread'], ['name' => 'Pleated Midi Skirt', 'price' => 3890, 'brand' => 'urbanthread'], ['name' => 'Canvas Slip-On Shoes', 'price' => 2990, 'brand' => 'velofit'], ['name' => 'Textured Polo T-Shirt', 'price' => 2490, 'brand' => 'urbanthread'], ['name' => 'Water-Resistant Day Jacket', 'price' => 8990, 'brand' => 'urbanthread'],
            ]],
            ['key' => 'home-living', 'name' => 'Home & Living', 'image_key' => 'home-living', 'priority' => 3, 'template' => $templates['home-living'], 'items' => [
                ['name' => 'Modern Bedside Lamp', 'price' => 2990, 'brand' => 'homenest'], ['name' => 'Non-Stick Cookware Set', 'price' => 15990, 'brand' => 'homenest'], ['name' => 'Bamboo Storage Basket', 'price' => 1890, 'brand' => 'horizon-essentials'], ['name' => 'Minimal Wall Clock', 'price' => 2490, 'brand' => 'homenest'], ['name' => 'Memory Foam Seat Cushion', 'price' => 2190, 'brand' => 'homenest'], ['name' => 'Ceramic Serving Bowl Set', 'price' => 3490, 'brand' => 'homenest'], ['name' => 'Foldable Laundry Hamper', 'price' => 2290, 'brand' => 'horizon-essentials'], ['name' => 'Cushion Cover Pair', 'price' => 1590, 'brand' => 'homenest'], ['name' => 'Adjustable Curtain Rod', 'price' => 1790, 'brand' => 'homenest'], ['name' => 'Compact Shoe Organizer', 'price' => 2790, 'brand' => 'horizon-essentials'],
            ]],
            ['key' => 'beauty', 'name' => 'Beauty & Personal Care', 'image_key' => 'beauty', 'priority' => 4, 'template' => $templates['beauty'], 'items' => [
                ['name' => 'Hydrating Face Serum', 'price' => 2290, 'brand' => 'glowvia'], ['name' => 'Vitamin C Face Wash', 'price' => 1290, 'brand' => 'glowvia'], ['name' => 'Daily Moisture Cream', 'price' => 1890, 'brand' => 'glowvia'], ['name' => 'Gentle Cleansing Balm', 'price' => 2490, 'brand' => 'glowvia'], ['name' => 'Aloe Cooling Gel', 'price' => 990, 'brand' => 'glowvia'], ['name' => 'Silk Finish Lip Tint', 'price' => 1390, 'brand' => 'glowvia'], ['name' => 'Scalp Care Shampoo', 'price' => 1690, 'brand' => 'purecart'], ['name' => 'Nourishing Hand Lotion', 'price' => 890, 'brand' => 'purecart'], ['name' => 'Reusable Makeup Brush Set', 'price' => 1990, 'brand' => 'glowvia'], ['name' => 'Mineral Sun Shield SPF', 'price' => 2490, 'brand' => 'purecart'],
            ]],
            ['key' => 'grocery', 'name' => 'Grocery & Essentials', 'image_key' => 'grocery', 'priority' => 5, 'template' => $templates['grocery'], 'items' => [
                ['name' => 'Wholegrain Oat Breakfast Pack', 'price' => 799, 'brand' => 'purecart'], ['name' => 'Roasted Almond Snack Pack', 'price' => 1299, 'brand' => 'purecart'], ['name' => 'Herbal Green Tea Box', 'price' => 699, 'brand' => 'purecart'], ['name' => 'Classic Pasta Sauce Jar', 'price' => 899, 'brand' => 'purecart'], ['name' => 'Pure Honey Glass Jar', 'price' => 1599, 'brand' => 'purecart'], ['name' => 'Basmati Rice Family Pack', 'price' => 2499, 'brand' => 'purecart'], ['name' => 'Crunchy Granola Mix', 'price' => 1199, 'brand' => 'purecart'], ['name' => 'Cold-Pressed Cooking Oil', 'price' => 1799, 'brand' => 'purecart'], ['name' => 'Dried Fruit Trail Mix', 'price' => 1399, 'brand' => 'purecart'], ['name' => 'Kitchen Spice Starter Set', 'price' => 999, 'brand' => 'horizon-essentials'],
            ]],
            ['key' => 'accessories', 'name' => 'Accessories', 'image_key' => 'accessories', 'priority' => 6, 'template' => $templates['accessories'], 'items' => [
                ['name' => 'Minimal Leather Wallet', 'price' => 2490, 'brand' => 'urbanthread'], ['name' => 'Insulated Steel Bottle', 'price' => 2190, 'brand' => 'horizon-essentials'], ['name' => 'Everyday Canvas Tote', 'price' => 1690, 'brand' => 'urbanthread'], ['name' => 'Polarized Travel Sunglasses', 'price' => 2990, 'brand' => 'urbanthread'], ['name' => 'Slim Card Holder', 'price' => 1490, 'brand' => 'urbanthread'], ['name' => 'Compact Travel Organizer', 'price' => 2290, 'brand' => 'horizon-essentials'], ['name' => 'Braided Casual Belt', 'price' => 1790, 'brand' => 'urbanthread'], ['name' => 'Classic Analog Watch', 'price' => 6990, 'brand' => 'aeropulse'], ['name' => 'Soft Touch Key Ring', 'price' => 590, 'brand' => 'horizon-essentials'], ['name' => 'Foldable Rain Umbrella', 'price' => 1290, 'brand' => 'horizon-essentials'],
            ]],
            ['key' => 'sports', 'name' => 'Sports & Outdoors', 'image_key' => 'sports', 'priority' => 7, 'template' => $templates['sports'], 'items' => [
                ['name' => 'Resistance Band Set', 'price' => 1890, 'brand' => 'velofit'], ['name' => 'Adjustable Dumbbell Pair', 'price' => 17990, 'brand' => 'velofit'], ['name' => 'Lightweight Yoga Mat', 'price' => 2490, 'brand' => 'velofit'], ['name' => 'Breathable Training Gloves', 'price' => 1790, 'brand' => 'velofit'], ['name' => 'Quick-Dry Sports Towel', 'price' => 990, 'brand' => 'velofit'], ['name' => 'Agility Ladder Kit', 'price' => 2290, 'brand' => 'velofit'], ['name' => 'Hydration Running Belt', 'price' => 1690, 'brand' => 'velofit'], ['name' => 'Outdoor Camping Lantern', 'price' => 3490, 'brand' => 'velofit'], ['name' => 'Foam Roller Recovery Bar', 'price' => 2990, 'brand' => 'velofit'], ['name' => 'Compact Picnic Cooler', 'price' => 4290, 'brand' => 'homenest'],
            ]],
            ['key' => 'toys', 'name' => 'Toys & Kids', 'image_key' => 'toys', 'priority' => 8, 'template' => $templates['toys'], 'items' => [
                ['name' => 'Remote Control Racing Car', 'price' => 2990, 'brand' => 'kidora'], ['name' => 'Magnetic Building Tiles Set', 'price' => 4990, 'brand' => 'kidora'], ['name' => 'Wooden Puzzle Blocks', 'price' => 1990, 'brand' => 'kidora'], ['name' => 'Creative Drawing Tablet', 'price' => 3490, 'brand' => 'kidora'], ['name' => 'Soft Plush Dino Toy', 'price' => 1590, 'brand' => 'kidora'], ['name' => 'Kids Science Explorer Kit', 'price' => 2790, 'brand' => 'kidora'], ['name' => 'Mini Tabletop Air Hockey', 'price' => 4490, 'brand' => 'kidora'], ['name' => 'Colorful Art Supply Box', 'price' => 2290, 'brand' => 'kidora'], ['name' => 'Beginner Board Game Set', 'price' => 2490, 'brand' => 'kidora'], ['name' => 'Pretend Play Doctor Kit', 'price' => 1790, 'brand' => 'kidora'],
            ]],
            ['key' => 'automotive', 'name' => 'Automotive', 'image_key' => 'automotive', 'priority' => 9, 'template' => $templates['automotive'], 'items' => [
                ['name' => 'All-Weather Floor Mat Set', 'price' => 7990, 'brand' => 'motoredge'], ['name' => 'Portable Tyre Inflator', 'price' => 6490, 'brand' => 'motoredge'], ['name' => 'Magnetic Phone Mount', 'price' => 1890, 'brand' => 'motoredge'], ['name' => 'Microfiber Car Care Kit', 'price' => 2290, 'brand' => 'motoredge'], ['name' => 'LED Safety Warning Triangle', 'price' => 1590, 'brand' => 'motoredge'], ['name' => 'Seat Back Organizer', 'price' => 2490, 'brand' => 'motoredge'], ['name' => 'Compact Jump Starter', 'price' => 24990, 'brand' => 'motoredge'], ['name' => 'Windshield Sun Shade', 'price' => 1790, 'brand' => 'motoredge'], ['name' => 'Multi-Tool Roadside Kit', 'price' => 2990, 'brand' => 'motoredge'], ['name' => 'Universal Roof Cargo Strap', 'price' => 2190, 'brand' => 'motoredge'],
            ]],
            ['key' => 'essentials', 'name' => 'Everyday Essentials', 'image_key' => 'essentials', 'priority' => 10, 'template' => $templates['essentials'], 'items' => [
                ['name' => 'Horizon Everyday Backpack', 'price' => 4990, 'brand' => 'horizon-essentials'], ['name' => 'Rechargeable Reading Light', 'price' => 1290, 'brand' => 'aeropulse'], ['name' => 'Stainless Travel Mug', 'price' => 2190, 'brand' => 'horizon-essentials'], ['name' => 'Digital Kitchen Scale', 'price' => 1990, 'brand' => 'homenest'], ['name' => 'Foldable Storage Crate', 'price' => 1590, 'brand' => 'homenest'], ['name' => 'USB Desk Fan', 'price' => 2490, 'brand' => 'aeropulse'], ['name' => 'Reusable Lunch Container', 'price' => 1790, 'brand' => 'horizon-essentials'], ['name' => 'Multi-Purpose Cleaning Cloths', 'price' => 799, 'brand' => 'purecart'], ['name' => 'Desk Cable Management Kit', 'price' => 1290, 'brand' => 'aeropulse'], ['name' => 'Compact First Aid Organizer', 'price' => 1890, 'brand' => 'horizon-essentials'],
            ]],
            ['key' => 'digital', 'name' => 'Digital Products', 'image_key' => 'electronics', 'priority' => 14, 'template' => 'Instant digital resources for work, learning, design, and growing a modern online business.', 'items' => [
                ['name' => 'Premium Resume Template Pack', 'price' => 1490, 'brand' => 'modecraft', 'image_file' => 'resume-template.svg', 'seller_index' => 2],
                ['name' => 'Social Media Canva Bundle', 'price' => 2290, 'brand' => 'veyra', 'image_file' => 'social-media-bundle.svg', 'seller_index' => 2],
                ['name' => 'Business Invoice Template Set', 'price' => 1190, 'brand' => 'horizon-essentials', 'image_file' => 'invoice-templates.svg', 'in_house' => true],
                ['name' => 'React Admin Dashboard UI Kit', 'price' => 4990, 'brand' => 'nexora', 'image_file' => 'react-dashboard-kit.svg', 'seller_index' => 0],
                ['name' => 'Laravel Starter Project', 'price' => 5990, 'brand' => 'nexora', 'image_file' => 'laravel-starter.svg', 'in_house' => true],
                ['name' => 'E-commerce Product Photography Presets', 'price' => 1890, 'brand' => 'lumera', 'image_file' => 'photo-presets.svg', 'seller_index' => 2],
                ['name' => 'English Speaking Course for Beginners', 'price' => 2990, 'brand' => 'purevelle', 'image_file' => 'english-course.svg', 'seller_index' => 5],
                ['name' => 'Graphic Design Basics eBook', 'price' => 990, 'brand' => 'modecraft', 'image_file' => 'design-ebook.svg', 'seller_index' => 2],
                ['name' => 'SEO Audit Template Pack', 'price' => 1590, 'brand' => 'veyra', 'image_file' => 'seo-audit.svg', 'seller_index' => 2],
                ['name' => 'Digital Planner Bundle', 'price' => 1290, 'brand' => 'lumera', 'image_file' => 'planner-bundle.svg', 'seller_index' => 2],
                ['name' => 'Brand Identity Kit', 'price' => 3490, 'brand' => 'modecraft', 'image_file' => 'brand-kit.svg', 'seller_index' => 2],
                ['name' => 'Mobile App Wireframe Pack', 'price' => 2490, 'brand' => 'nexora', 'image_file' => 'wireframe-pack.svg', 'seller_index' => 0],
            ]],
        ];

        foreach ($definitions as &$category) {
            $category['slug'] = self::PRODUCT_PREFIX . 'category-' . $category['key'];
            if ($category['key'] === 'beauty') {
                $category['items'][0]['name'] = 'Maison Aurex Midnight Oud 100ml';
                $category['items'][0]['price'] = 8990;
                $category['items'][0]['brand'] = 'maison-aurex';
                $category['items'][1]['name'] = 'Scentara Citrus Bloom Eau de Parfum';
                $category['items'][1]['price'] = 6490;
                $category['items'][1]['brand'] = 'scentara';
            }
            foreach ($category['items'] as &$item) {
                $item['short_description'] = $item['name'] . ' is a thoughtfully selected everyday product with practical features and dependable value.';
                $item['details'] = $templates[$category['key']] . ' ' . $item['name'] . ' is designed for convenient daily use, with a clear specification profile to help shoppers compare options confidently.';
                if (($item['product_type'] ?? ($category['key'] === 'digital' ? 'digital' : 'physical')) === 'digital') {
                    $item['short_description'] = $item['name'] . ' is an instant digital download for practical creative, business, or learning needs.';
                    $item['details'] = 'Instant digital delivery with organized files and practical guidance. ' . $item['name'] . ' is designed for convenient use by creators, professionals, and growing online businesses.';
                }
            }
        }
        unset($category, $item);
        return $definitions;
    }

    private function seedAdditionalCategories(Carbon $now): array
    {
        $definitions = [
            ['key' => 'perfumes', 'name' => 'Perfumes & Fragrances', 'icon' => 'beauty', 'priority' => 11],
            ['key' => 'shoes', 'name' => 'Shoes & Footwear', 'icon' => 'fashion', 'priority' => 12],
            ['key' => 'bags', 'name' => 'Bags & Accessories', 'icon' => 'accessories', 'priority' => 13],
        ];
        $ids = [];
        foreach ($definitions as $category) {
            $slug = self::PRODUCT_PREFIX . 'category-' . $category['key'];
            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $category['name'],
                    'icon' => 'fxh-demo-category-' . $category['icon'] . '.webp',
                    'icon_storage_type' => 'public',
                    'parent_id' => 0,
                    'position' => 0,
                    'home_status' => 0,
                    'auction_home_status' => 0,
                    'priority' => $category['priority'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $ids[$category['key']] = DB::table('categories')->where('slug', $slug)->value('id');
        }
        return $ids;
    }

    private function additionalCategoryLinks(array $category, array $item, array $categoryIds): array
    {
        $name = Str::lower($item['name']);
        $links = [];
        if (Str::contains($name, ['perfume', 'fragrance', 'oud', 'eau de parfum']) && isset($categoryIds['perfumes'])) {
            $links[] = ['id' => $categoryIds['perfumes'], 'position' => 3];
        }
        if (Str::contains($name, ['sneaker', 'shoe', 'slip-on']) && isset($categoryIds['shoes'])) {
            $links[] = ['id' => $categoryIds['shoes'], 'position' => 3];
        }
        if (Str::contains($name, ['bag', 'backpack', 'tote']) && isset($categoryIds['bags'])) {
            $links[] = ['id' => $categoryIds['bags'], 'position' => 3];
        }
        return $links;
    }

    private function seedSubcategories(array $categoryIds, Carbon $now): array
    {
        $definitions = [
            'electronics' => ['Headphones', 'Smart Watches', 'Chargers', 'Power Banks', 'Computer Accessories'],
            'fashion' => ["Men's Clothing", "Women's Clothing", 'Hoodies', 'Shirts', 'T-Shirts', 'Jackets'],
            'home-living' => ['Home Decor', 'Bedding', 'Storage', 'Lighting'],
            'beauty' => ['Skincare', 'Hair Care', 'Grooming', 'Makeup Accessories', 'Fragrances'],
            'grocery' => ['Snacks', 'Beverages', 'Pantry Essentials', 'Household Essentials'],
            'accessories' => ['Bags', 'Wallets', 'Watches', 'Travel Accessories'],
            'sports' => ['Fitness', 'Outdoor Gear', 'Sports Accessories'],
            'toys' => ['Educational Toys', 'Action Toys', 'Building Toys', 'Kids Accessories'],
            'automotive' => ['Car Care', 'Interior Accessories', 'Roadside Essentials'],
            'essentials' => ['Travel Essentials', 'Desk Accessories', 'Storage Solutions'],
            'digital' => ['Templates', 'Courses', 'eBooks', 'UI Kits', 'Business Resources'],
        ];
        $ids = [];
        foreach ($definitions as $parentKey => $names) {
            if (! isset($categoryIds[$parentKey])) {
                continue;
            }
            foreach ($names as $position => $name) {
                $slug = self::PRODUCT_PREFIX . 'subcategory-' . Str::slug($parentKey . '-' . $name);
                DB::table('categories')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'icon' => 'fxh-demo-category-' . $parentKey . '.webp',
                        'icon_storage_type' => 'public',
                        'parent_id' => $categoryIds[$parentKey],
                        'position' => $position + 1,
                        'home_status' => 0,
                        'auction_home_status' => 0,
                        'priority' => $position + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $ids[$parentKey . ':' . Str::lower($name)] = DB::table('categories')->where('slug', $slug)->value('id');
            }
        }
        return $ids;
    }

    private function subcategoryFor(string $categoryKey, array $item, array $subcategoryIds): ?int
    {
        $name = Str::lower($item['name']);
        $subcategory = match ($categoryKey) {
            'electronics' => Str::contains($name, ['headphone', 'speaker']) ? 'headphones' : (Str::contains($name, ['watch']) ? 'smart watches' : (Str::contains($name, ['charger', 'power bank']) ? 'chargers' : 'computer accessories')),
            'fashion' => Str::contains($name, ['hoodie']) ? 'hoodies' : (Str::contains($name, ['shirt', 'polo', 't-shirt']) ? 'shirts' : (Str::contains($name, ['jacket', 'vest']) ? 'jackets' : "men's clothing")),
            'home-living' => Str::contains($name, ['lamp', 'clock', 'curtain']) ? 'lighting' : (Str::contains($name, ['cookware', 'bowl']) ? 'storage' : 'home decor'),
            'beauty' => Str::contains($name, ['perfume', 'fragrance', 'oud', 'eau de parfum']) ? 'fragrances' : (Str::contains($name, ['shampoo', 'scalp']) ? 'hair care' : (Str::contains($name, ['brush', 'lip']) ? 'makeup accessories' : 'skincare')),
            'grocery' => Str::contains($name, ['tea', 'pasta', 'honey', 'rice', 'oil', 'spice']) ? 'pantry essentials' : (Str::contains($name, ['snack', 'almond', 'granola', 'fruit']) ? 'snacks' : 'beverages'),
            'accessories' => Str::contains($name, ['watch']) ? 'watches' : (Str::contains($name, ['wallet', 'card holder', 'belt', 'key ring']) ? 'wallets' : (Str::contains($name, ['bag', 'tote']) ? 'bags' : 'travel accessories')),
            'sports' => Str::contains($name, ['camping', 'picnic']) ? 'outdoor gear' : 'fitness',
            'toys' => Str::contains($name, ['building', 'blocks']) ? 'building toys' : (Str::contains($name, ['car', 'air hockey', 'doctor']) ? 'action toys' : 'educational toys'),
            'automotive' => Str::contains($name, ['care', 'cloth']) ? 'car care' : 'interior accessories',
            'essentials' => Str::contains($name, ['backpack', 'mug', 'lunch']) ? 'travel essentials' : (Str::contains($name, ['desk', 'light', 'fan', 'cable']) ? 'desk accessories' : 'storage solutions'),
            'digital' => Str::contains($name, ['course']) ? 'courses' : (Str::contains($name, ['ebook']) ? 'ebooks' : (Str::contains($name, ['ui kit', 'wireframe']) ? 'ui kits' : (Str::contains($name, ['template', 'bundle', 'kit', 'preset']) ? 'templates' : 'business resources'))),
            default => null,
        };
        return $subcategory ? ($subcategoryIds[$categoryKey . ':' . $subcategory] ?? null) : null;
    }

    private function seedAttributes(Carbon $now): void
    {
        foreach ([
            'Color', 'Clothing Size', 'Shoe Size', 'Storage', 'RAM', 'Material',
            'Fragrance Size', 'Gender', 'Age Group', 'Capacity', 'Battery Life', 'Fit',
        ] as $name) {
            DB::table('attributes')->updateOrInsert(
                ['name' => $name],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function demoImageFor(array $category, array $item, int $variant): string
    {
        if (!empty($item['image_file'])) {
            return 'fxh-demo-marketplace/digital/' . $item['image_file'];
        }

        $name = Str::lower($item['name']);
        $assetCategory = $category['key'];
        $assetKey = $category['key'];

        if (Str::contains($name, ['sneaker', 'shoe'])) {
            $assetCategory = 'fashion';
            $assetKey = 'shoes';
        }

        if (Str::contains($name, ['watch'])) {
            $assetCategory = 'accessories';
            $assetKey = 'watch';
        }

        if (Str::contains($name, ['bag', 'backpack', 'tote'])) {
            $assetCategory = 'accessories';
            $assetKey = 'bag';
        }

        if (Str::contains($name, ['perfume', 'fragrance'])) {
            $assetCategory = 'beauty';
            $assetKey = 'perfume';
        }

        if (Str::contains($name, ['cookware', 'serving bowl', 'kitchen', 'cooking oil', 'kitchen spice', 'kitchen scale'])) {
            $assetCategory = 'home-living';
            $assetKey = 'kitchen';
        }

        return 'fxh-demo-marketplace/products/' . $assetCategory . '/' . $assetKey . '-' . str_pad((string)$variant, 2, '0', STR_PAD_LEFT) . '.webp';
    }

    private function sellerIndexFor(array $category, array $item): int
    {
        $name = Str::lower($item['name']);

        if ($category['key'] === 'electronics' || $category['key'] === 'automotive') {
            return 0; // NovaGear Store
        }

        if (in_array($category['key'], ['home-living', 'grocery'], true)) {
            return 1; // HomeNest Living
        }

        if ($category['key'] === 'fashion') {
            return Str::contains($name, ['sneaker', 'shoe']) ? 3 : 2;
        }

        if ($category['key'] === 'beauty') {
            return 4; // AuraScents
        }

        if ($category['key'] === 'toys' || $category['key'] === 'sports') {
            return 5; // PlaySphere
        }

        if (Str::contains($name, ['bag', 'backpack', 'tote'])) {
            return 2; // UrbanWeave
        }

        if ($category['key'] === 'essentials') {
            return Str::contains($name, ['backpack']) ? 2 : 1;
        }

        return 0;
    }

    private function brands(): array
    {
        return collect([
            ['key' => 'novagear', 'name' => 'NovaGear'], ['key' => 'nexora', 'name' => 'Nexora'],
            ['key' => 'aeropulse', 'name' => 'AeroPulse'], ['key' => 'voltedge', 'name' => 'VoltEdge'],
            ['key' => 'pulsetek', 'name' => 'PulseTek'], ['key' => 'urbanthread', 'name' => 'UrbanThread'],
            ['key' => 'northvale', 'name' => 'NorthVale'], ['key' => 'modecraft', 'name' => 'ModeCraft'],
            ['key' => 'veyra', 'name' => 'Veyra'], ['key' => 'homenest', 'name' => 'HomeNest'],
            ['key' => 'casanest', 'name' => 'CasaNest'], ['key' => 'homevale', 'name' => 'HomeVale'],
            ['key' => 'urbanliving', 'name' => 'UrbanLiving'], ['key' => 'glowvia', 'name' => 'Glowvia'],
            ['key' => 'lumera', 'name' => 'Lumera'], ['key' => 'purevelle', 'name' => 'PureVelle'],
            ['key' => 'dermaleaf', 'name' => 'DermaLeaf'], ['key' => 'purecart', 'name' => 'PureCart'],
            ['key' => 'maison-aurex', 'name' => 'Maison Aurex'], ['key' => 'veloura', 'name' => 'Veloura'],
            ['key' => 'scentara', 'name' => 'Scentara'], ['key' => 'noir-essence', 'name' => 'Noir Essence'],
            ['key' => 'velofit', 'name' => 'VeloFit'], ['key' => 'stridex', 'name' => 'StrideX'],
            ['key' => 'stepforge', 'name' => 'StepForge'], ['key' => 'aerowalk', 'name' => 'AeroWalk'],
            ['key' => 'trailnova', 'name' => 'TrailNova'], ['key' => 'kidora', 'name' => 'Kidora'],
            ['key' => 'playsphere', 'name' => 'PlaySphere'], ['key' => 'kidnova', 'name' => 'KidNova'],
            ['key' => 'wonderbrick', 'name' => 'WonderBrick'], ['key' => 'tinytrail', 'name' => 'TinyTrail'],
            ['key' => 'chronova', 'name' => 'Chronova'], ['key' => 'timevale', 'name' => 'TimeVale'],
            ['key' => 'verotime', 'name' => 'VeroTime'], ['key' => 'motoredge', 'name' => 'MotorEdge'],
            ['key' => 'dailynest', 'name' => 'DailyNest'], ['key' => 'freshvale', 'name' => 'FreshVale'],
            ['key' => 'pantrygo', 'name' => 'PantryGo'], ['key' => 'horizon-essentials', 'name' => 'Horizon Essentials'],
        ])->map(function (array $brand): array {
            $brand['slug'] = self::PRODUCT_PREFIX . 'brand-' . $brand['key'];
            return $brand;
        })->all();
    }

    private function demoSellers(Carbon $now): array
    {
        return [
            ['f_name' => 'NovaGear', 'l_name' => 'Demo', 'email' => self::SELLER_EMAIL_PREFIX . 'novagear@example.test', 'phone' => '03000001001', 'shop_name' => 'NovaGear Store', 'shop_slug' => self::PRODUCT_PREFIX . 'shop-novagear', 'description' => 'Smart accessories and everyday tech essentials selected for modern digital lifestyles.', 'logo' => 'fxh-demo-stores/novagear.svg', 'banner' => 'fxh-demo-stores/novagear-banner.svg'],
            ['f_name' => 'HomeNest', 'l_name' => 'Demo', 'email' => self::SELLER_EMAIL_PREFIX . 'homenest@example.test', 'phone' => '03000001003', 'shop_name' => 'HomeNest Living', 'shop_slug' => self::PRODUCT_PREFIX . 'shop-homenest', 'description' => 'Practical home, kitchen, and living essentials designed for comfortable everyday spaces.', 'logo' => 'fxh-demo-stores/homenest.svg', 'banner' => 'fxh-demo-stores/homenest-banner.svg'],
            ['f_name' => 'UrbanWeave', 'l_name' => 'Demo', 'email' => self::SELLER_EMAIL_PREFIX . 'urbanthread@example.test', 'phone' => '03000001002', 'shop_name' => 'UrbanWeave', 'shop_slug' => self::PRODUCT_PREFIX . 'shop-urbanweave', 'description' => 'Contemporary fashion essentials for modern everyday style.', 'logo' => 'fxh-demo-stores/urbanweave.svg', 'banner' => 'fxh-demo-stores/urbanweave-banner.svg'],
            ['f_name' => 'StepCraft', 'l_name' => 'Demo', 'email' => self::SELLER_EMAIL_PREFIX . 'stepcraft@example.test', 'phone' => '03000001005', 'shop_name' => 'StepCraft', 'shop_slug' => self::PRODUCT_PREFIX . 'shop-stepcraft', 'description' => 'Everyday footwear and sneakers focused on comfort, style, and versatility.', 'logo' => 'fxh-demo-stores/stepcraft.svg', 'banner' => 'fxh-demo-stores/stepcraft-banner.svg'],
            ['f_name' => 'AuraScents', 'l_name' => 'Demo', 'email' => self::SELLER_EMAIL_PREFIX . 'aurascents@example.test', 'phone' => '03000001004', 'shop_name' => 'AuraScents', 'shop_slug' => self::PRODUCT_PREFIX . 'shop-aurascents', 'description' => 'Fragrances, beauty, and personal-care essentials for everyday confidence.', 'logo' => 'fxh-demo-stores/aurascents.svg', 'banner' => 'fxh-demo-stores/aurascents-banner.svg'],
            ['f_name' => 'PlaySphere', 'l_name' => 'Demo', 'email' => self::SELLER_EMAIL_PREFIX . 'playsphere@example.test', 'phone' => '03000001006', 'shop_name' => 'PlaySphere', 'shop_slug' => self::PRODUCT_PREFIX . 'shop-playsphere', 'description' => 'Toys and lifestyle products made for fun, creativity, and family moments.', 'logo' => 'fxh-demo-stores/playsphere.svg', 'banner' => 'fxh-demo-stores/playsphere-banner.svg'],
        ];
    }

    private function demoCustomers(Carbon $now): array
    {
        return [
            ['f_name' => 'Amina', 'l_name' => 'Demo', 'email' => self::CUSTOMER_EMAIL_PREFIX . 'amina@example.test', 'phone' => '03000002001'],
            ['f_name' => 'Bilal', 'l_name' => 'Demo', 'email' => self::CUSTOMER_EMAIL_PREFIX . 'bilal@example.test', 'phone' => '03000002002'],
            ['f_name' => 'Hira', 'l_name' => 'Demo', 'email' => self::CUSTOMER_EMAIL_PREFIX . 'hira@example.test', 'phone' => '03000002003'],
        ];
    }

    private function choiceFor(string $category, ?array $item = null): array
    {
        $name = Str::lower($item['name'] ?? '');
        if ($category === 'fashion') {
            return ['title' => 'Size', 'options' => ['S', 'M', 'L', 'XL']];
        }
        if ($category === 'electronics') {
            return ['title' => Str::contains($name, ['watch', 'power bank']) ? 'Color' : 'Storage', 'options' => Str::contains($name, ['watch', 'power bank']) ? ['Black', 'Navy', 'Silver'] : ['64GB', '128GB', '256GB']];
        }
        if ($category === 'beauty') {
            return ['title' => Str::contains($name, ['serum', 'cream', 'lotion', 'gel', 'shampoo', 'wash']) ? 'Volume' : 'Size', 'options' => ['50ml', '100ml', '200ml']];
        }
        if ($category === 'accessories') {
            return ['title' => 'Color', 'options' => ['Black', 'Brown', 'Navy']];
        }
        return match ($category) {
            'sports' => ['title' => 'Size', 'options' => ['Small', 'Medium', 'Large']],
            'toys' => ['title' => 'Age Group', 'options' => ['3-5 years', '6-8 years', '9+ years']],
            'automotive' => ['title' => 'Pack', 'options' => ['Single', 'Pair']],
            default => ['title' => 'Capacity', 'options' => ['Small', 'Medium', 'Large']],
        };
    }

    private function insertDemoReview(int $productId, int $customerId, int $rating, Carbon $now): void
    {
        DB::table('reviews')->insert([
            'product_id' => $productId,
            'customer_id' => $customerId,
            'comment' => ['A useful product with clear details.', 'Good quality and practical for everyday use.', 'The product looks well presented and works as expected.'][$rating % 3],
            'attachment' => null,
            'rating' => $rating,
            'status' => 1,
            'is_saved' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function deleteProductRelations(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }
        foreach (['product_tag', 'product_seos', 'product_stocks', 'product_views', 'product_compares', 'wishlists', 'carts', 'restock_products', 'stock_clearance_products', 'digital_product_variations', 'digital_product_authors', 'digital_product_publishing_houses'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table) && in_array('product_id', DB::getSchemaBuilder()->getColumnListing($table), true)) {
                DB::table($table)->whereIn('product_id', $productIds)->delete();
            }
        }
        DB::table('flash_deal_products')->whereIn('product_id', $productIds)->delete();
        DB::table('reviews')->whereIn('product_id', $productIds)->delete();
        DB::table('translations')->where('translationable_type', 'App\\Models\\Product')->whereIn('translationable_id', $productIds)->delete();
        DB::table('taxables')->where('taxable_type', 'App\\Models\\Product')->whereIn('taxable_id', $productIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
    }

    private function clearCatalogCaches(): void
    {
        foreach (['products', 'categories', 'brands', 'sellers', 'shops', 'reviews', 'flash_deals'] as $type) {
            if (function_exists('cacheRemoveByType')) {
                cacheRemoveByType(type: $type);
            }
        }
    }
}
