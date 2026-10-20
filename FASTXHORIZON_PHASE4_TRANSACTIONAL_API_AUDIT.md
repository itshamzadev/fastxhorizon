# FastXHorizon Phase 4 Transactional API Audit

## Existing flow map

The current Blade checkout uses Laravel web sessions, `CartManager`,
`OrderManager`, shipping selection stored in cart/session state, coupon helpers,
tax configuration, and payment gateway redirects/callbacks. The existing mobile
API has related routes, but several return legacy/raw payload shapes and share
guest-session assumptions. Checkout also has digital-only payment restrictions,
shipping responsibility, minimum-order rules, inventory checks, wallet payment,
manual/offline payment, and gateway-specific callbacks.

## Safe APIs added in this phase

- `GET /api/v1/frontend/addresses`
- `POST /api/v1/frontend/addresses`
- `PUT /api/v1/frontend/addresses/{id}`
- `DELETE /api/v1/frontend/addresses/{id}`
- `GET /api/v1/frontend/orders/{id}`
- `GET /api/v1/frontend/orders/{id}/tracking`
- `GET /api/v1/frontend/wallet`
- `GET /api/v1/frontend/wallet/transactions`

These endpoints use Passport authentication, customer ownership checks, DTO/API
Resources, and do not expose raw Eloquent models wholesale.

## Address safety

Address CRUD preserves the existing required fields and delivery country/zip
restriction checks. Guest addresses are not accessible through the authenticated
frontend endpoints. An address ID is always scoped to the authenticated customer
before update or delete.

## Why checkout preview/order placement remains Blade

The authoritative quote and order pipeline currently depends on session/cart
state and `CartManager` selection. Shipping, coupon, tax, product/variant stock,
minimum order rules, digital-product payment rules, and gateway state must be
bound into a single API request context before a standalone frontend can safely
place orders. A naive preview endpoint could show totals that differ from the
actual order or consume coupon/stock state incorrectly, so no fake preview or
order endpoint was added.

Existing order placement paths already contain important protections in the
inspected flows: shopper checkout locking, database transactions, wallet row
locks, and wallet idempotency keys. They must be reused by a future dedicated
checkout service rather than duplicated in React.

## Shipping and coupons

Legacy API endpoints exist for seller shipping methods, choosing cart shipping,
chosen shipping methods, coupon lists, and coupon application. They still use
legacy request/session shapes and are not yet promoted as the standalone
frontend checkout contract. Coupon eligibility includes dates, customer scope,
first-order rules, usage limits, seller scope, minimum purchase, and discount
type; these rules must remain server-authoritative.

## Payment audit

- COD: order placement path, physical-product and shipping restrictions apply.
- Manual/offline payment: existing method list and offline completion flow remain
  in Laravel.
- Digital gateways: existing payment request/redirect and success hooks remain
  in Laravel until each provider's signature, replay, callback, and duplicate
  success behaviour has a dedicated API test.
- Wallet: read-only summary/transactions are now available; wallet writes remain
  backend-controlled through existing flows.

No gateway credentials or payment success response is exposed to the frontend.

## Guest cart

Guest cart behaviour remains with the existing Laravel session/cart system. The
standalone frontend does not invent a second guest-cart merge algorithm. A
future API cart contract must explicitly preserve cart group IDs, checked state,
variant quantities, digital/physical rules, and login merge semantics.

## Multi-node compatibility

The new read endpoints are stateless and use MySQL as the source of truth. They
do not write local files or depend on a particular backend node. Checkout cannot
be declared multi-node-ready until session/cart state is moved to shared Redis
or a fully request-scoped API checkout snapshot is implemented.

## React status

The standalone frontend now has address, order-detail/tracking, wallet service
modules ready for incremental UI migration. `VITE_CHECKOUT_MODE=blade` remains
the default safety switch. There is no React order-placement/payment flow yet.

## Remaining blockers

1. Build a request-scoped checkout snapshot/quote service around existing
   `OrderManager` and stock/tax/coupon rules.
2. Add idempotent order-intent storage keyed by customer and `Idempotency-Key`.
3. Add provider-specific payment initialization and callback state machines.
4. Move guest cart/session state to shared infrastructure or define a signed API
   cart token with safe login merge.
5. Test duplicate callbacks, stock races, coupon limits, refunds, and wallet
   side effects before enabling API checkout.
