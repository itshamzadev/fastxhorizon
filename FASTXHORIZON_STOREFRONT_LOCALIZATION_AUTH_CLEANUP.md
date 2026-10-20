# Fast X Horizon storefront cleanup

## Completed in this pass

- Removed public App Store/Google Play promotion from the active web footer.
- Removed the public office-address block from the footer.
- Removed the dedicated Track Order footer link; authenticated order details remain unchanged.
- Preserved centralized social-media settings and added safe external-link attributes.
- Removed the customer-facing Deliveryman Inbox tab and return 404 for disabled customer Deliveryman chat routes/API requests. Dormant deliveryman models/routes remain for compatibility and historical data.
- Rebuilt the public FAQ view at `/helpTopic` with a Fast X Horizon hero, search field, category filters, accordion answers, and no broken empty-state image.
- Added an idempotent `FastXHorizonFaqSeeder` with 63 practical FAQ records using the existing `help_topics` table.
- Currency dropdown entries now use ISO format: `CODE - Full Currency Name`, with normalized names for the required PKR, USD, AED, SAR, GBP and other supported currencies.

## Existing architecture retained

Social links continue to come from the existing admin-managed `social_media` settings. Currency and language persistence continue to use the existing 6Valley session/configuration architecture. No product, wallet, order, seller accounting, payment, or policy business logic was changed.

## Intentionally not removed

Flutter source/apps, dormant deliveryman controllers/routes/data, core order tracking controllers, and existing localization records were not deleted. Admin deliveryman navigation and full customer/seller/admin authentication redesign require a separate feature-flag/settings pass and were not destructively removed here.

## Validation

- `/ ` returned HTTP 200.
- `/helpTopic` returned HTTP 200.
- FAQ page rendered 71 active records after preserving existing seller-registration FAQs.
- Footer checks confirmed no active app badges, Track Order footer link, or public shop-address block.
- `php artisan optimize:clear` and `php artisan view:cache` completed successfully.

