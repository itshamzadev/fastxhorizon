# Fast X Horizon Multi-Currency

## 1. Architecture and base currency

Fast X Horizon extends the installed 6Valley currency engine instead of adding
a second pricing system. Existing `Currency`, `currency_model`, session
selector, `currencyConverter`, `webCurrencyConverter`, payment-gateway
currency handling, and API `currency_list` contracts remain in use.

The development catalog and customer wallet/order ledger use **PKR** as the
canonical base currency. Existing product and order values were not rewritten.
The migration enables 6Valley multi-currency mode and adds PKR as the base
row. Currency table rates retain the 6Valley convention of units per USD; the
application normalizes display conversion as:

`display amount = base amount × target rate ÷ PKR base rate`

PKR is initialized at `280` as an admin-configured fixed fallback/test rate.
This is not represented as a current market-rate claim.

## 2. Selection priority

1. Manual selection in the current session; for authenticated customers it is
   also saved to `users.preferred_currency`.
2. Authenticated account country, then an unambiguous normalized phone prefix.
3. Cached visitor IP country using the installed Laravel GeoGenius package.
4. USD fallback.

Manual selection is marked in session and is never replaced by IP detection.
Private/local IPs do not trigger external geolocation. Visitor country is
cached in the session and GeoGenius also caches public-IP results.

## 3. Supported mapping

The centralized map currently covers PKR, USD, AED, GBP, SAR, QAR, KWD, OMR,
BHD, EUR, CAD, AUD, JPY, CNY, INR, and TRY where an enabled currency row is
available. Ambiguous `+1` numbers intentionally do not select a currency by
themselves.

## 4. Rates

`php artisan fastxhorizon:currency-rates-update` runs daily at 03:00 through
Laravel's scheduler. It uses the configured provider URL (default:
`https://open.er-api.com/v6/latest/USD`) with an optional
`CURRENCY_RATE_API_KEY` environment setting. It validates the response and
updates only rows in `auto` mode inside a database transaction. Fixed rows,
including PKR, are preserved.

Provider errors leave the last valid rates untouched. Existing `exchange_rate`
values act as the fallback until an administrator changes them. The Currency
admin page shows rate mode, source/update time, and a stale warning after 72
hours; rate editing remains behind the existing admin settings permissions.

## 5. Storefront and checkout

The header selector uses the existing `currency.change` route and active
currency rows. Product cards, details, cart, checkout, wallet displays, and
legacy price helpers reuse the shared converter branch. No product base price
is changed when the display currency changes.

New orders snapshot `base_currency_code`, `display_currency_code`,
`currency_exchange_rate`, and `display_order_amount`; existing accounting
amounts remain in PKR. Existing payment-gateway support checks continue to
choose the gateway transaction currency rather than trusting a browser amount.

## 6. Wallet and seller accounting

The customer wallet and seller earnings remain single-currency PKR ledgers.
Wallet refunds, debits, credits, platform-funded deals, commissions, and
settlements do not use the latest display rate. A customer may view a converted
equivalent, but ledger arithmetic remains canonical PKR.

## 7. API/mobile impact

The existing API `currency_list`, `currency_model`, and `current_currency_code`
contracts are preserved. No Flutter package IDs, API field names, or app source
contracts were renamed. Mobile clients can continue selecting a currency using
their existing current-currency request field.

## 8. Files and migrations

- `config/fastxhorizon_currency.php`
- `app/Services/FastXHorizonCurrencyService.php`
- `app/Http/Middleware/ResolveFastXHorizonCurrency.php`
- `app/Console/Commands/FastXHorizonUpdateCurrencyRates.php`
- `database/migrations/2026_10_12_000002_add_fastxhorizon_currency_context.php`
- `database/migrations/2026_10_12_000003_add_currency_snapshot_to_orders.php`
- Existing currency helpers, order creation, header selector, and admin
  currency view/controller were extended.

## 9. Verification

- Migrations completed without reset/truncate.
- `php artisan fastxhorizon:currency-rates-update --dry-run` returned valid
  provider rates without saving them.
- Manual USD and PKR switching returned HTTP 200 and persisted in the session.
- Product detail rendered `PKR8,990.00` after selecting PKR and `$32.11` after
  selecting USD for the existing PKR 8,990 base price.
- Homepage, customer login, and seller login returned HTTP 200 locally.
- Laravel view/cache commands passed.

Production still needs a real provider policy/API key decision, production
cron/scheduler configuration, and final gateway-by-gateway currency approval
before accepting live non-PKR payments.
