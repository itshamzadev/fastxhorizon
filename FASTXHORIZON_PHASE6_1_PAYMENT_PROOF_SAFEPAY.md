# FastXHorizon Phase 6.1 — Safepay Sandbox and Offline Proofs

## Audit

The installation already has:

- `orders.payment_status`, `payment_method`, `transaction_ref`, and payment
  history/order-detail handling.
- `offline_payment_methods` and `offline_payments`.
- Existing admin offline-method configuration under
  `admin/third-party/offline-payment-method`.
- Existing Blade offline checkout and admin order payment review.

The gaps were provider transaction storage, customer proof storage, secure
proof access, API proof submission, and a standalone React proof screen.

No Safepay code, SDK, configuration, callback, webhook, or credentials existed.
The official provider contract could not be verified from the local project, so
provider-specific request/signature fields were intentionally not invented.

## Offline payment proof workflow

New table: `offline_payment_proofs`.

The customer API is:

`POST /api/v1/frontend/orders/{id}/payment-proof`

It requires an owned unpaid offline-payment order, an active offline method,
the exact authoritative order amount, transaction reference, and a validated
JPG/JPEG/PNG/PDF upload up to 5 MB. Files use Laravel's configured filesystem
disk and are not stored under a public path. File hashes and a database unique
constraint prevent the same proof being submitted twice. A second pending proof
for one order is rejected.

Payment status now includes the customer's proof state and safe rejection reason.
Uploading a proof never marks an order paid.

Admin review remains in the existing order details area:

- view/download the protected proof
- approve
- reject with a reason

Approval locks the proof and order in one transaction, marks the order and order
details paid, and stores the submitted transaction reference. Repeated approval
or rejection returns a conflict and cannot repeat payment side effects.

The current database has zero active offline methods and offline payment is
disabled, so no test proof was fabricated or submitted. Admin can configure a
real Bank Transfer/JazzCash/Easypaisa-style method using the existing method
screen, then enable offline payment deliberately.

## Safepay boundary

Added:

- `config/safepay.php`
- sandbox-only environment placeholders in `.env.example`
- `PaymentGatewayInterface`
- `SafepayGateway` fail-closed adapter
- `POST /api/v1/frontend/payments/safepay/initialize`
- `POST /api/v1/payments/safepay/webhook`
- `payment_transactions` table for future provider references

The adapter refuses to initialize until `SAFEPAY_ENABLED=true`,
`SAFEPAY_ENV=sandbox`, and verified provider fields/credentials are present.
The webhook returns 503 while disabled. No browser redirect or query parameter
can mark an order paid. No live credentials were added.

## Environment

```env
SAFEPAY_ENABLED=false
SAFEPAY_ENV=sandbox
SAFEPAY_API_KEY=
SAFEPAY_SECRET_KEY=
SAFEPAY_WEBHOOK_SECRET=
SAFEPAY_BASE_URL=
SAFEPAY_SUCCESS_URL=
SAFEPAY_CANCEL_URL=
```

These are placeholders only. The exact Safepay API and signature contract must
be confirmed before implementing the HTTP initialization and webhook verifier.

## React

The standalone frontend now has payment-proof upload service support and shows a
proof form after an offline order is created. Safepay is not exposed as a
selectable payment method while the backend/provider is disabled. COD and Blade
checkout remain unchanged and available as rollback paths.

## Verification

- Migration `2026_10_19_000003_create_payment_transactions_and_offline_payment_proofs`
  applied successfully.
- PHP syntax checks passed for all new/changed PHP files.
- React production build passed.
- Safepay initialize request against a COD order was rejected with HTTP 422.
- Disabled unauthenticated webhook endpoint returned HTTP 503.
- Offline options endpoint returned an empty list because no active method is
  configured.
- `php artisan optimize:clear` passed.

Full offline proof approval could not be executed without an active configured
offline method and a real local payment order. Safepay sandbox initialization,
signature verification, duplicate webhook, amount mismatch, and provider status
tests remain blocked until verified sandbox credentials and official provider
contract details are supplied.

## Status

COD remains verified from Phase 6. Offline proof architecture and admin flow are
implemented but not end-to-end exercised in this database. Safepay is a secure
fail-closed scaffold, not a claimed provider integration. Phase 6.1 is therefore
**not complete** until a real offline method and Safepay sandbox can be tested.
