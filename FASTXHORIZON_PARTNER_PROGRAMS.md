# FastXHORIZON partner programs

This implementation adds separate reseller and affiliate programs without changing the existing seller account, customer referral-reward, customer wallet, or seller wallet systems.

## Data model

The additive migration `2026_10_22_000001_create_fastxhorizon_partner_program_tables.php` creates profiles, applications, attributions, commission rules, commission snapshots, an immutable partner ledger, payout requests, and partner audit events. Money is stored as fixed-precision decimals. No partner rows are seeded.

Both programs use the same internal services but always carry an explicit `program` value (`reseller` or `affiliate`), so records and permissions cannot be silently mixed.

## Attribution and commission

Approved profiles receive a unique `RS...` or `AF...` code. `?reseller=CODE` and `?ref=CODE` are captured in a first-party session/cookie by `partner.attribution`. At order placement, the backend resolves the approved profile and stores an order attribution; the customer cannot submit a partner ID to create a commission. Self-referrals are rejected.

The commission base is the order amount less shipping and tax. A commission is snapshotted at order creation as `pending`, becomes `available` after `delivered`/`completed`, and is reversed on cancelled/returned/refunded statuses. A unique partner/order/program constraint and idempotent ledger writes prevent duplicates. Product/category rule columns are present for future rule administration; the current default resolver uses the highest-priority global rule.

## Configuration

`config/fastxhorizon.php` defines safe-disabled defaults and environment overrides. `fastxhorizon:sync-partner-programs` previews or upserts only program settings in `business_settings`:

```text
php artisan fastxhorizon:sync-partner-programs
php artisan fastxhorizon:sync-partner-programs --apply
```

The command never creates demo partners, orders, commissions, payouts, products, customers, sellers, or wallet entries. Admin can adjust the enabled state, application state, rate type/rate, attribution period, and minimum payout through the partner settings pages.

## Routes

Customers:

- `/reseller/apply`, `/reseller/dashboard`, `/reseller/payout`
- `/affiliate/apply`, `/affiliate/dashboard`, `/affiliate/payout`

Admins (existing admin guard and `people` module permission):

- `/admin/partner-programs/{reseller|affiliate}/applications`
- `/profiles`, `/payouts`, `/settings` and their review actions

Master Admin continues to use the existing role-1 super-admin path; no email bypass was added.

## Production deployment

```text
php artisan migrate --force
php artisan fastxhorizon:sync-partner-programs
# review the dry run
php artisan fastxhorizon:sync-partner-programs --apply
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Programs remain disabled until the business enables them and configures real commission/payout policy. No payout provider or partner-specific mail notification was added in this slice; existing FastXHorizon mail identity configuration remains available for the next notification integration.
