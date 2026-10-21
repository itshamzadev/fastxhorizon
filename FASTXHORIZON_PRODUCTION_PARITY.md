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

The command upserts presentation/company settings and copies the six FastXHorizon branding assets through Laravel's `public` storage disk. It does not read or copy arbitrary database rows.

The allowlist includes company identity, public contact details, business mode, currency presentation, homepage section presentation settings, and the six branding references. It explicitly excludes secrets, credentials, maintenance state, social credentials, catalog, seller, customer, review, order, wallet, coupon, inventory, deal assignments, and payment data.

Run it only after taking the normal production database and upload backup. Run it twice safely; it uses `updateOrCreate` and deterministic asset paths.

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
