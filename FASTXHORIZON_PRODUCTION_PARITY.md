# FastXHorizon production parity

The Laravel/Blade storefront remains the production customer frontend. The standalone React app is not used as the public website cutover.

## Source of truth

Local FastXHorizon presentation was audited from `business_settings`, the active Blade theme, and the local company media directory. The source-controlled branding bundle is under:

`public/assets/fastxhorizon-branding/`

It contains only the existing FastXHorizon web, invoice, mobile, footer, favicon, and loader assets.

## Safe sync command

The command is allowlisted and dry-run by default:

```bash
php artisan fastxhorizon:sync-branding
php artisan fastxhorizon:sync-branding --apply
```

Use `--skip-assets` only when the production storage deployment is handled separately.

The command upserts presentation/company settings and copies the six FastXHorizon branding assets through Laravel's `public` storage disk. It also synchronizes only the existing `about-us` visual fields and the two explicitly named rows in `company_leadership_profiles`. It does not read or copy arbitrary database rows.

The allowlist includes company identity, public contact details, reserved-company registration fields, business mode, currency presentation, homepage section presentation settings, the six branding references, the three existing About Us theme images, and these two leadership profiles:

| Record | Blade source | Photo value |
|---|---|---|
| Muhammad Younas | `company_leadership_profiles` (`name` match) | `public/assets/front-end/img/leadership/muhammad-younas-profile.png` |
| Muhammad Hamza | `company_leadership_profiles` (`name` match) | `public/assets/front-end/img/leadership/muhammad-hamza-profile.png` |

The leadership photo values intentionally remain `public/assets/...`: `resources/themes/default/web-views/pages/business_page.blade.php` detects that prefix and calls `dynamicAsset()`. The files are source-controlled and validated by the sync command, so production receives the exact files on `git pull`; they are not seller/customer uploads. The About Us fields are `business_pages.hero_image`, `supporting_image_1`, and `supporting_image_2`, with `theme:company-policy/...` values resolved by `theme_asset()`.

The header/mobile/footer/favicon/loader values are `business_settings` rows consumed by `getWebConfig()` and resolved through `storageLink()`. Their source files are the existing local files under `storage/app/public/company/`, mirrored in `public/assets/fastxhorizon-branding/`, and their production targets are `storage/app/public/company/...` (served as `/storage/company/...` after `storage:link`). It explicitly excludes secrets, credentials, maintenance state, social credentials, catalog, seller, customer, review, order, wallet, coupon, inventory, deal assignments, and payment data.

Run it only after taking the normal production database and upload backup. Run it twice safely; it uses `updateOrCreate`, deterministic asset paths, and stable `name` matches for the two leadership profiles. It never bulk-copies `users`, `sellers`, `shops`, or any transactional table.

## Production deployment

```bash
cd /www/wwwroot/fastxhorizon
git pull origin main
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan storage:link
php artisan fastxhorizon:sync-branding --apply
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Do not import the localhost database. Do not run demo seeders on production. Do not enable Safepay without verified credentials and provider configuration. Offline payment must remain disabled until real production instructions are configured.

## Data protection

The sync command does not touch products, product stocks, variants, product media, sellers, shops, customers, carts, orders, order details, reviews, transactions, wallets, coupons, flash-deal assignments, payment proofs, or test accounts.

Production should be checked independently after deployment:

```bash
php artisan tinker --execute="echo App\\Models\\Product::count().PHP_EOL; echo App\\Models\\Order::count().PHP_EOL; echo App\\Models\\Customer::count().PHP_EOL; echo App\\Models\\Review::count().PHP_EOL;"
```

These counts are observation-only and must not be replaced with localhost counts.
