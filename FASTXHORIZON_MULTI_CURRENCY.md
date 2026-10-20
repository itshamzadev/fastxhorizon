# Fast X Horizon Multi-Currency

This is the canonical multi-currency audit for the Fast X Horizon implementation.

## Architecture

The implementation extends the installed 6Valley `Currency` model/table,
existing currency selector, session state, converters, payment handling, and
API currency contracts. It does not create a parallel pricing engine.

PKR is the canonical base currency because the existing catalog, wallet, seller
settlement, and order accounting use PKR values. Product prices are never
rewritten when the display currency changes.

Rates use one convention: each currency row stores units per USD. Display
conversion is `base amount × target rate ÷ PKR base rate`.

## Selection priority

1. Manual session/customer preference.
2. Authenticated account/address country, then an unambiguous phone prefix.
3. Cached public-IP country for guests.
4. USD fallback.

Manual selection is persisted for authenticated customers as
`users.preferred_currency` and is not overridden by IP detection. Private/local
IPs fall back to account signals or USD.

## Supported mapping and rates

Centralized country mapping covers PKR, USD, AED, GBP, SAR, QAR, KWD, OMR, BHD,
EUR, CAD, AUD, JPY, CNY, INR, and TRY when an enabled currency row exists.
Ambiguous `+1` phone numbers do not guess a country.

`php artisan fastxhorizon:currency-rates-update` updates `auto` currencies
daily at 03:00 via Laravel scheduling. The default provider is
`https://open.er-api.com/v6/latest/USD`; an optional API key is read from
`CURRENCY_RATE_API_KEY` and is never exposed to the client. Fixed rates, such
as the development PKR/USD reference, are preserved. Provider failure leaves
the last valid rate untouched.

## Checkout, wallet, and accounting

New orders snapshot base currency, display currency, exchange rate, and the
converted display amount. Canonical order, wallet, commission, platform-deal,
refund, and seller-settlement calculations remain in PKR. Historical orders do
not change when rates are updated. Payment gateways continue to use their
supported transaction currency rather than trusting browser values.

## Admin and API

Currency management remains at the existing admin currency settings page. It
supports enabled currencies, fixed/automatic rate mode, fallback/reference
rate, source, update time, and stale-rate visibility under existing admin
authorization. Existing API fields such as `currency_list`,
`current_currency_code`, and `currency_model` remain compatible; Flutter
package IDs and API contracts were not renamed.

## Files and migrations

- `config/fastxhorizon_currency.php`
- `app/Services/FastXHorizonCurrencyService.php`
- `app/Http/Middleware/ResolveFastXHorizonCurrency.php`
- `app/Console/Commands/FastXHorizonUpdateCurrencyRates.php`
- `database/migrations/2026_10_12_000002_add_fastxhorizon_currency_context.php`
- `database/migrations/2026_10_12_000003_add_currency_snapshot_to_orders.php`
- Existing currency helpers, order creation, selector, and admin currency
  controller/view were extended.

## Verification

Migrations ran without reset/truncate. The provider update command successfully
updated nine automatic currency rows while preserving fixed PKR/USD rows.
Manual PKR/USD switching persisted in session and rendered the existing PKR
8,990 product as `PKR8,990.00` and `$32.11`. Homepage, customer login, seller
login, and product detail returned HTTP 200; PHP syntax and Laravel caches
passed. Production still requires a provider policy/key decision, scheduler
cron, and gateway-by-gateway approval for live non-PKR transaction currencies.
