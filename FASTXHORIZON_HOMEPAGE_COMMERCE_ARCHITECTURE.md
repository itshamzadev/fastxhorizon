# Fast X Horizon Homepage Commerce Architecture

Status: implemented for the active `default` theme only.

This phase covers only the first three commerce sections on the customer homepage:

1. Fast X Horizon Deals
2. Featured Products
3. Categories

The existing 6Valley product, category, flash-deal and admin authorization architecture is reused. No duplicate product or category tables were introduced.

## 1. Deal architecture

Deals use the existing `flash_deals` and `flash_deal_products` tables and their existing admin routes:

- Admin deal list: `admin/deal/flash`
- Create/edit deal: existing `admin.deal.flash-add` / `admin.deal.update`
- Select products: `admin/deal/add-product/{deal_id}`
- Enable/disable: existing `admin.deal.status-update`

The existing status controller intentionally permits one active flash deal at a time. Homepage selection also requires:

- section setting enabled;
- deal type `flash_deal`;
- status enabled;
- current date inside the deal window;
- product status/request approval eligibility;
- approved seller and an existing seller shop for seller products.

When no valid products remain, the complete deal section is omitted. Product selection uses real product IDs; it never copies products.

The deal picker now supports source, seller/shop, category, brand and name/code filtering. The selection page also records a deal discount type and funding type. Existing deal rows default to `seller` funding for backward compatibility.

## 2. Platform-funded vs seller-funded discount

`flash_deal_products.funding_type` is additive and accepts `seller` or `platform`.

- Seller-funded: the deal discount is part of the seller-side product discount.
- Platform-funded: the customer receives the deal discount, but the discount is tracked separately as a platform contribution.

The product `unit_price` is never overwritten. Effective pricing is calculated by `getProductPricingBreakdown()` and is capped at zero, so a percentage or fixed discount cannot produce a negative price.

The calculation returns the normal discount, deal discount, seller discount, platform discount, effective customer price, deal ID and funding type.

## 3. Seller settlement behavior

Cart rows snapshot the active deal ID, funding type and platform contribution. Order details snapshot:

- `flash_deal_id`
- `flash_deal_funding`
- `platform_discount`
- `seller_discount`

Customer totals use the full effective discount. Seller settlement/commission basis adds back only the platform-funded amount, preserving the seller's pre-platform-promotion basis while keeping seller-funded discounts seller-funded. Existing checkout and payment routes were not renamed or replaced.

Migration: `database/migrations/2026_10_11_120000_add_homepage_commerce_deal_snapshots.php`

## 4. Deal admin controls

The existing Flash Deal screen remains the deal management area. Its product selection page now provides:

- All products / Fast X Horizon in-house / seller products;
- seller/shop filter;
- category filter;
- brand filter;
- product name/code search;
- multi-selection using the existing 6Valley selector;
- percentage or fixed deal discount;
- seller-funded or Fast X Horizon-funded setting.

Only active, approved and publicly eligible products can be added. Ineligible products are also rejected server-side if a crafted request submits them.

## 5. Featured Products

Featured Products reuses the existing `products.featured` flag and existing product admin controls. Admin can select or unselect products from the product list, with the existing seller/category/brand/search filters. Existing `featured_product_priority` controls provide deterministic ordering; newest featured products remain the fallback ordering.

The homepage adds independent `homepage_featured_setup` settings:

- enabled/disabled;
- section title;
- product limit.

Featured products can be in-house or from approved sellers. No discount is created by featuring a product. The section is omitted when disabled or when no publicly eligible featured products remain.

## 6. Category ownership

Categories continue to use the existing admin-managed `categories` table, hierarchy, `home_status`, `priority`, icon and active taxonomy management. Seller product forms load categories from this admin taxonomy; sellers do not create global categories from product forms.

Homepage category settings are independent in `homepage_categories_setup`:

- enabled/disabled;
- display limit.

Admin category Home Status and priority determine selection and order. The homepage category query uses real active/public product relationships and avoids per-category product-count queries. Inactive or empty configured categories are naturally excluded.

## 7. Homepage conditional rendering and order

The active default theme renders the three sections in this order:

1. Fast X Horizon Deals, only if a valid active deal and product set exists;
2. Featured Products, only if enabled and valid featured products exist;
3. Categories, only if enabled and configured active categories exist.

Hidden sections produce no empty wrapper or reserved placeholder space. All three use the existing shared compact Fast X Horizon product card where products are rendered.

Relevant files:

- `app/Http/Controllers/Web/HomeController.php`
- `app/Utils/ProductManager.php`
- `app/Utils/CategoryManager.php`
- `app/Traits/CacheManagerTrait.php`
- `resources/themes/default/web-views/home.blade.php`
- `resources/themes/default/web-views/partials/_flash-deal.blade.php`
- `resources/themes/default/web-views/partials/_category-section-home.blade.php`

## 8. Admin homepage settings

The three section switches and limits are stored as separate business settings and shown in the existing admin Priority Setup screen:

- `/admin/priority-setup`
- `homepage_deals_setup`
- `homepage_featured_setup`
- `homepage_categories_setup`

Deal product selection remains in the dedicated Flash Deal area. Featured selection remains in Product List. Category selection remains in Category Management. Changing one section's settings does not change the other section's selected records.

## 9. API/mobile impact

Existing API routes, product IDs, flash-deal response names, package IDs and Firebase identifiers were preserved. The new funding/snapshot fields are additive database/model fields. No mobile application rebuild or response-field rename was required in this phase.

## 10. Performance/query strategy

- Homepage product limits are applied independently.
- Deal and featured products eager-load the deal relation used by the shared card.
- Public eligibility uses approved seller and existing shop checks.
- Category filtering uses relationship constraints and existing category cache paths.
- Deal-product repository mutations clear product and flash-deal caches.
- No new per-card AJAX request or product-copy operation was introduced.

## 11. Database safety

Only additive nullable/defaulted columns were introduced. No `migrate:fresh`, truncate, reset, product deletion or branding-setting reset was used. Existing demo catalog records remain available for local QA.

## 12. QA scenarios

Validated locally:

- Homepage HTTP 200 with an active mixed in-house/seller demo deal.
- Customer product/category/featured routes HTTP 200.
- Deal price breakdown includes platform contribution without changing `unit_price`.
- Inactive/unapproved seller-shop products are excluded by the public eligibility scope.
- Featured and category settings are read independently.

Authenticated admin/vendor UI and full checkout/payment processing still require a valid local session for browser-level QA; no authentication bypass was used.

Focused automated coverage: `tests/Unit/HomepageCommercePricingTest.php` passes (2 tests, 6 assertions), covering platform-funded separation and discount caps.

The full existing suite currently reports 16 failures and 16 passes. The failures are pre-existing test-environment/application issues: missing SQLite `business_settings`/`flash_deals` schema in the test harness, the known missing `AgentLoopService::isFollowUp()` method, and an unrelated AI query-guard assertion. No homepage-commerce test failed.
