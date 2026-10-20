# FastXHorizon Phase 5 — Checkout and Payment Audit

## Existing flow

The existing Blade checkout uses the database-backed `carts` and
`cart_shippings` tables, `CartManager`, and `OrderManager::generateOrder()`.
Prices, product discounts, tax, seller grouping, shipping, coupons and stock
changes are calculated in Laravel. Blade routes and payment callbacks remain
unchanged.

The existing REST order controller already reuses this pipeline for legacy
mobile/API callers, but it is not a standalone checkout contract: it is
session-aware, accepts legacy request shapes, and has no general order
idempotency record.

## Phase 5 implementation

The new customer API adds:

- `GET /api/v1/frontend/checkout/shipping-options`
- `POST /api/v1/frontend/checkout/preview`
- `POST /api/v1/frontend/checkout/coupon`
- `POST /api/v1/frontend/checkout/order`

All require Passport customer authentication. The quote reads the
authenticated customer's checked cart, validates owned addresses, validates
physical stock (including selected variation quantities), leaves digital
products outside physical-stock validation, and delegates pricing/tax/coupon/
seller calculations to the existing order/cart utilities.

`frontend_checkout_idempotencies` is an additive table. It stores the customer,
Idempotency-Key, request hash, processing/completed state and the resulting
order IDs/response. A customer row lock plus a unique customer/key index makes
same-key retries safe across multiple PHP nodes. Reusing a key with a different
payload returns a conflict.

Before COD order creation, physical product rows are locked in a transaction;
stock and the quote are recalculated immediately before calling the existing
`OrderManager::generateOrder()` pipeline. The old Blade COD flow is not
removed.

## Payments

COD is the first standalone payment method because it has no provider redirect
or callback lifecycle. Offline/manual payment and digital gateways (including
Safepay and any configured provider) remain Blade fallback paths until each
provider's initialization, signature verification, webhook replay protection,
and reconciliation flow is tested end to end.

## React behavior

The standalone frontend has an API checkout route and service, but the default
is still `VITE_CHECKOUT_MODE=blade`. Set `VITE_CHECKOUT_MODE=api` only after
customer API credentials, cart data, addresses, and a safe COD test account
are available. `VITE_BLADE_CHECKOUT_URL` provides immediate rollback.

## Known limitations

- Guest checkout is not moved to this contract; authenticated checkout is
  required.
- Provider-backed payments are not claimed migrated.
- Existing product stock decrement code in `OrderManager` is reused; future
  hardening should also make stock restoration/decrement variation-aware under
  every legacy order-status path.
- The local test suite still has the previously known SQLite bootstrap failures
  involving existing `business_settings` and `flash_deals` tables.
