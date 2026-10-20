# FastXHorizon Phase 6.2 — Payment Integration & E2E Verification

## Scope

This phase hardens the existing offline/manual-payment workflow and keeps the
Safepay integration fail-closed until its real sandbox contract and credentials
are available. COD, Blade checkout, existing gateway routes, order totals and
inventory behavior were preserved.

## Existing architecture audit

- Offline methods already use the existing `offline_payment_methods` table,
  admin CRUD screens, `offline_payment` business setting, and checkout method
  information flow.
- Customer orders are stored in `orders`; offline payment details are stored in
  `offline_payments`.
- Proof submissions are stored privately using Laravel `Storage`, not a public
  filesystem path.
- Admin order details is the existing review surface; no second admin payment
  application was created.
- Payment provider state is represented by the additive
  `payment_transactions` table. Safepay does not have a verified provider
  adapter in the current codebase.

## Implemented in Phase 6.2

### Offline methods

Existing admin routes are reused:

- `admin/third-party/offline-payment-method/index`
- add/update/delete
- status toggle

The administrator must enter the real customer-visible instructions and account
information. No bank account, wallet number, or other production payment detail
was fabricated or inserted.

### Offline proof lifecycle

- An active method must exist and the global offline-payment setting must be
  enabled before the method is offered to customers.
- A proof is accepted only for an authenticated owner of an unpaid
  `offline_payment` order.
- The proof method must match the method recorded when the order was created.
- The claimed amount is checked against the immutable server-side order amount;
  the client amount is never used as an authority.
- Allowed files are JPG/JPEG/PNG/PDF, maximum 5 MiB, stored on the configured
  Laravel filesystem disk under a private payment-proof path.
- Duplicate file hashes and concurrent pending submissions are rejected.
- Admin review locks both the proof and order. Approval is one-way, updates the
  existing order payment fields, and creates a normalized `offline/paid`
  `payment_transactions` record. Rejection keeps the order unpaid.
- `offline_payment_proof_events` records customer submission and admin review
  transitions with actor, status, timestamp and rejection note.
- Proof downloads are controlled by the existing admin route and stream from
  the configured disk; there is no public URL.

## Added schema

- `payment_transactions` and `offline_payment_proofs` were added in the previous
  phase.
- `offline_payment_proof_events` was added in migration
  `2026_10_19_000004_create_offline_payment_proof_events.php`.

All changes are additive and were applied with `php artisan migrate --force`.
No reset, truncate, reseed, or production-record deletion was performed.

## Safepay status

Safepay remains disabled by default:

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

The local project contains no Safepay SDK, verified request/response contract,
credentials, or webhook signature specification. An attempt to access official
Safepay documentation during this task was unavailable because the external
documentation request failed. Therefore no endpoint, payload, signature, or
success response was guessed. The current adapter intentionally refuses to
initialize or accept a webhook while disabled/unverified.

To complete Safepay sandbox verification, obtain:

1. the official sandbox base URL and API version;
2. the exact checkout/session initialization payload and response fields;
3. authentication/signing rules;
4. success, failure, cancellation and webhook event schemas;
5. the exact webhook signature calculation and replay guidance;
6. sandbox merchant credentials and permitted return/webhook URLs;
7. provider reference and amount/currency reconciliation rules.

No live credentials were changed and no real charge was attempted.

## Tests and verification

Passed:

- PHP syntax checks for changed controllers, models and migrations.
- `php artisan migrate --force` for the additive migration.
- `php artisan optimize:clear`.
- `php artisan view:cache`.
- Safepay unit coverage was added for fail-closed behavior and local status
  normalization.

Phase 6.3 added a focused disposable SQLite fixture at
`tests/Feature/OfflinePaymentProofWorkflowTest.php`. It creates only the
minimal payment/order schema in an in-memory database and uses fake local
storage; it does not run against the application database. The focused suite
now passes:

```text
php artisan test --filter OfflinePaymentProofWorkflowTest --testsuite=Feature --colors=never
8 tests, 20 assertions passed in the original offline-only run; the final
combined focused run is 10 tests and 25 assertions passed.
```

Covered flows include valid submit/approve, reject/resubmit, owner isolation,
incorrect amount, unsupported MIME, over-5MiB files, duplicate file hashes,
double review protection, one payment transaction, and audit events.

Blocked:

- The repository's general SQLite feature bootstrap is incomplete: unrelated
  normal feature tests still fail because required pre-existing tables such as
  `business_settings` (and, in other feature runs, `flash_deals`) do not have
  base migrations in this checkout. The payment suite avoids that unrelated
  legacy gap with its explicit disposable schema instead of altering
  production migrations.
- No active offline method is configured in the current local database, so no
  production/local data was mutated to manufacture a passing payment test.
- Safepay sandbox E2E cannot run without the verified external contract and
  sandbox credentials listed above.

The previously verified COD flow and its Blade fallback remain unchanged.

## Remaining phase status

- Offline method admin management: **implemented by existing architecture and
  reused**.
- Offline proof API/storage/review/audit: **verified in the focused disposable
  payment lifecycle suite**. Full application-wide feature tests still have the
  unrelated legacy SQLite bootstrap failure described above.
- Safepay: **implemented as fail-closed scaffold, not sandbox verified**.
- Phase 6.2: **not complete**, because the external Safepay requirements and
  isolated offline proof E2E verification are still outstanding.
