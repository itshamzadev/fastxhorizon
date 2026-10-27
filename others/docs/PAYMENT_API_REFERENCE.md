# FastXHorizon V1 Payment API

Public:

- `GET /api/payments/config` returns enabled payment types and active manual methods/fields.
- `POST /api/checkout/create-order` recalculates order totals and accepts only enabled payment types. Manual orders require `methodKey`, `paymentValues` and `paymentEvidence`; proof remains pending verification.

Admin (administrator session plus `payments.read` or `payments.manage`):

- `GET /api/admin/payments/config`
- `PUT /api/admin/payments/config`
- `GET /api/admin/payments/reviews?state=PENDING_VERIFICATION`
- `GET /api/admin/payments/reviews/:id/evidence/:index`
- `POST /api/admin/payments/reviews/:id/decision` with `{ "state": "VERIFIED"|"REJECTED", "reason"?: string }`

The initial configuration is Automatic disabled, Manual enabled and COD disabled. Manual payment methods are not operational until an authorized administrator configures at least one method.
