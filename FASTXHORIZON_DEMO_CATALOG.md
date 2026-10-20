# Fast X Horizon Development Demo Catalog

## Scope and safety

This catalog is local/development data only. The seeder is guarded to run only
when `APP_ENV` is `local` or `testing`; it is not attached to the normal
`DatabaseSeeder` flow. No production configuration, branding settings, master
logo assets, technical IDs, existing products, existing sellers, orders, or
shops were reset or truncated.

## Seeder and cleanup

- Seeder: `database/seeders/FastXHorizonDemoProductSeeder.php`
- Seed command:

  ```text
  php artisan db:seed --class=Database\Seeders\FastXHorizonDemoProductSeeder
  ```

- Cleanup command: `php artisan fastxhorizon:demo-products-clear`
- Cleanup implementation: `app/Console/Commands/FastXHorizonDemoProductsClear.php`
- Demo product marker: slug prefix `fxh-demo-` and product codes `FXHDEM0001` …
  `FXHDEM0100`.
- Demo shops use `fxh-demo-shop-*`; demo sellers use
  `fxh-demo-seller-*@example.test`; demo review customers use
  `fxh-demo-customer-*@example.test`.

The seeder uses an idempotent update/insert strategy and removes only stale
rows carrying the demo marker. It was executed twice after finalization; the
second execution did not create duplicates.

## Final counts

| Data | Result |
| --- | ---: |
| Demo products | **100 exactly** |
| Categories | 10 created |
| Brands | 10 created |
| Existing in-house shop reused | Fast X Horizon, shop ID 1 |
| Demo seller shops | 4 created |
| Review customers | 3 development users |
| Simple products | 60 |
| Variant products | 40 |
| No-discount products | 30 |
| Discounted products | 70 |
| Out-of-stock products | 4 |
| Gallery products | 20 |
| Flash-deal products | 18 |
| Reviews | 72 |
| Product SEO rows | 100 |
| Product image references | 140 (100 primary + 40 gallery images) |

## Category distribution

Each category has 10 products:

- Electronics — 10
- Fashion — 10
- Home & Living — 10
- Beauty & Personal Care — 10
- Grocery & Essentials — 10
- Accessories — 10
- Sports & Outdoors — 10
- Toys & Kids — 10
- Automotive — 10
- Everyday Essentials — 10

Electronics, Fashion, and Home & Living are enabled for the installed
6Valley homepage category sections. The other seven categories remain fully
active and available through category listings/search without causing the
existing homepage category cache to preload the entire 100-product catalog.

## Seller and brand distribution

Products are assigned to the existing Fast X Horizon in-house shop and four
new clearly marked development shops:

- Fast X Horizon — 25
- NovaGear Store — 19
- UrbanThread — 19
- HomeNest Living — 19
- Glowvia Beauty — 18

Fictional demo brands are NovaGear, AeroPulse, UrbanThread, HomeNest,
Glowvia, PureCart, VeloFit, Kidora, MotorEdge, and Horizon Essentials.

## Product data

Names, concise short/full descriptions, clean demo-namespaced slugs, PKR
prices, SEO titles/descriptions, active/approved status, valid category/brand
and shop relationships, physical `pc` units, and valid stock fields are
provided. Prices range from PKR 590 to PKR 24,990, including low, mid, and
higher-priced demo items. Discounts use the actual product discount fields:

- 30 products without a discount
- 50 products with a 5–25% discount
- 20 products with a 25–45% discount

Stock is varied across normal, low-stock, and four out-of-stock products.

The 40 variant products use the installed `choice_options` and `variation`
JSON structures. Options include clothing/sport sizes, toy age groups,
automotive pack sizes, and capacity choices. Variant prices and quantities
are populated so the selected variant can be added to cart.

## Flash deal and reviews

One active development flash deal was created using the real 6Valley tables:
`flash_deals` plus `flash_deal_products`. It contains 18 demo products and
does not fake a badge in the product row.

Seventy-two realistic development reviews cover 60 products. Review comments
carry the `[FXH-DEMO]` marker, allowing cleanup to remove only these reviews.
No fake order history was created for best-seller ranking.

## Images

All images are local SVG placeholders stored under `storage/app/public` and
served through the existing `public/storage` link. They use an 800×800 square
viewBox and the already-approved Fast X Horizon palette; no master logo assets
were changed and no external image host is required.

Product assets:

- `storage/app/public/product/fxh-electronics.svg`
- `storage/app/public/product/fxh-fashion.svg`
- `storage/app/public/product/fxh-home-living.svg`
- `storage/app/public/product/fxh-beauty.svg`
- `storage/app/public/product/fxh-grocery.svg`
- `storage/app/public/product/fxh-accessories.svg`
- `storage/app/public/product/fxh-sports.svg`
- `storage/app/public/product/fxh-toys.svg`
- `storage/app/public/product/fxh-automotive.svg`
- `storage/app/public/product/fxh-essentials.svg`
- `storage/app/public/product/fxh-detail-grid.svg`
- `storage/app/public/product/fxh-detail-package.svg`

The same ten category assets are available in
`storage/app/public/product/thumbnail/`. Brand, category, and shop placeholder
assets are stored in their corresponding local `brand`, `category`, and `shop`
directories.

## Validation performed

- Database query confirmed exactly 100 demo products and 100 unique demo slugs
  after both seed executions.
- All 100 products are active, published, and approved; no orphan category or
  shop relationships were found.
- Product detail routes returned HTTP 200 for simple, variant, and seller
  products.
- `/products?name=headphones`, `hoodie`, `watch`, `lamp`, `serum`, and
  `resistance` returned HTTP 200 and matched the relevant demo products.
- Category listing and product image routes returned HTTP 200.
- Guest cart POST validation returned status 1 for one simple product and one
  selected variant (`Medium`). The two temporary QA cart rows were removed
  afterward.
- Homepage returned HTTP 200. With the final bounded homepage category data,
  the page contained 45 unique demo product slugs rather than all 100.
- Ten warm curl requests after one warm-up measured: median **1.521182s**,
  minimum **1.474300s**, maximum **1.584583s**, p95 **1.584583s**. This is a
  catalog-data measurement; no application performance code was changed.

## Limitations

Best-seller ranking was not artificially manufactured with fake completed
orders. Flutter/mobile data was not modified. The local SVG placeholders are
intended for UI layout testing and can later be replaced through the normal
media workflow if desired.
