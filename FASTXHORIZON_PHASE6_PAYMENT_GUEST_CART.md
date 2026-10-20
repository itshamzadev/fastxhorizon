# FastXHorizon Phase 6 — Payment Lifecycle and Guest Cart Hardening

This document records the Phase 6 payment and checkout work. It is an additive
extension of the existing 6Valley checkout; the Blade checkout and all existing
payment routes remain available as the rollback path.

## Verified COD flow

The local verification used the existing demo customer `fxh-demo-customer-amina@example.test`
and an existing in-house product. No database reset or reseed was performed.

The verified path was:

`Passport login -> customer address -> customer cart -> shipping options -> authoritative preview -> COD order -> order detail -> payment status`

The quote was calculated by Laravel and returned PKR 8,995 (PKR 8,990 item
price plus PKR 5 shipping). The created local test orders were 100001 and
100002. The product stock changed from 33 to 31, confirming the existing order
stock path ran. These are local test records and were deliberately retained for
auditability.

## Idempotency

`POST /api/v1/frontend/checkout/order` requires `Idempotency-Key`.

- The key is unique per customer in `frontend_checkout_idempotencies`.
- A customer row lock serializes concurrent checkout attempts.
- A completed key stores the response and order reference for safe replay.
- Reusing a completed key with a changed payload returns HTTP 409.
- The order transaction also locks physical product rows before the existing
  `OrderManager::generateOrder()` path runs.
- A failed transaction rolls back its processing record, so a transient failure
  does not permanently consume a key.

The local replay test returned the original order `100002`; the changed-payload
test returned HTTP 409. A full two-process load test is not run against this
development database, but the protection is database-backed rather than node
memory-backed.

## Offline/manual payment

The existing `offline_payment_methods` and `offline_payments` architecture was
reused. The new API surface is:

- `GET /api/v1/frontend/payments/offline/options`
- `POST /api/v1/frontend/checkout/order` with `payment_method=offline_payment`,
  `method_id`, and whitelisted `method_informations` fields.

Offline orders remain unpaid and continue through the existing admin approval
workflow. Customer input is filtered against the configured method fields; the
frontend cannot mark an order paid.

The current installation has no active offline method configured, so the local
options response is empty. There is no existing payment-proof upload column or
workflow in this installation. Proof upload was not invented; the existing
Blade/manual flow remains the fallback and this is a known limitation.

## Safepay / digital gateway audit

No Safepay integration was found in the active codebase, routes, configuration,
database migrations, or frontend (`NO_SAFEPAY_IMPLEMENTATION_FOUND`). There is
therefore no safe initialization endpoint, provider callback, signature
verification, sandbox credential, or reconciliation lifecycle to migrate.

No fake Safepay flow was added and no live credentials were changed. A digital
gateway can be added in a later phase only after its complete provider contract
and sandbox credentials are available.

## Payment status

`GET /api/v1/frontend/orders/{id}/payment-status` is ownership-protected and
returns normalized payment method/status, a safe masked provider reference, and
the update timestamp. It never returns provider secrets or raw gateway payloads.

## Guest cart

API guest carts now receive an opaque, random `guest_token` with a 90-day expiry
in addition to the legacy numeric `guest_id`. `APIGuestMiddleware` resolves the
opaque token through the shared `guest_users` table, rejects invalid/expired
tokens, and retains numeric compatibility for existing clients. This removes
the API's dependency on node-local PHP session files. Blade guest-cart behavior
is unchanged for backward compatibility.

The guest-token endpoint and cart read were smoke-tested locally; an invalid
token returned HTTP 401. Guest-to-customer merge continues to use the existing
6Valley merge path and should be normalized further before enabling a new guest
checkout flow.

## Feature flags and fallback

The standalone frontend supports:

```env
VITE_CHECKOUT_MODE=blade
VITE_OFFLINE_PAYMENT_ENABLED=false
VITE_SAFEPAY_ENABLED=false
```

The backend still enforces actual payment availability; frontend flags are not
security controls. Blade checkout/payment routes were not removed.

## Changed files

- `app/Http/Controllers/RestAPI/v1/FrontendCheckoutController.php`
- `app/Http/Controllers/RestAPI/v1/FrontendOrderController.php`
- `app/Http/Controllers/RestAPI/v1/GeneralController.php`
- `app/Http/Middleware/APIGuestMiddleware.php`
- `app/Models/GuestUser.php`
- `app/Providers/RouteServiceProvider.php`
- `app/Services/FrontendCheckout/CheckoutQuoteService.php`
- `database/migrations/2026_10_19_000002_add_guest_cart_tokens.php`
- `frontend/src/App.jsx`
- `frontend/src/api/services.js`
- `frontend/.env.example`
- `routes/rest_api/v1/api.php`

## Validation

- PHP lint passed for the Phase 6 PHP files.
- Local Passport login passed.
- Address, cart, shipping, preview, COD order, order detail, and payment-status
  requests passed.
- Idempotency replay passed; changed-payload conflict returned HTTP 409.
- Guest token creation, valid cart lookup, and invalid-token rejection passed.
- Frontend production build passed using the existing Windows long-path-safe
  mapped-drive build procedure.
- `php artisan optimize:clear` and `php artisan view:cache` passed previously.
- The existing full SQLite feature suite still has known bootstrap failures
  (`business_settings` and `flash_deals` setup); those are not hidden or treated
  as Phase 6 passes.

## Phase 6 status

COD is locally end-to-end verified. Guest API carts are multi-node-safe through
shared token state. Offline payment support is additive but currently has no
active configured method and no proof-upload API. Safepay is not installed, so
the digital-payment success criterion remains blocked. Phase 6 is therefore
**not fully complete** until a real provider sandbox integration can be audited
and tested without trusting browser redirects.
