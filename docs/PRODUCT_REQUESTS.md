# Product Requests and 50% Advance

The out-of-stock request flow uses `ArrangementRequest` and keeps guest tracking private with a random tracking token. A customer can submit a request without an account, while authenticated requests also receive a `customerId` and are available at `GET /api/arrangements/mine`.

## Lifecycle

`PENDING_REVIEW` → `CHECKING_AVAILABILITY` → `AVAILABLE_AWAITING_ADVANCE` → `ADVANCE_SUBMITTED` → `ADVANCE_VERIFIED` → `ORDERED_FROM_SUPPLIER` → `PRODUCT_ARRIVED` → `READY_FOR_DELIVERY` → `SHIPPED` → `DELIVERED` → `COMPLETED`.

The admin endpoint recalculates totals server-side from the confirmed unit price and quantity. The advance is exactly 50%; uploaded evidence only creates `PAYMENT_SUBMITTED` and never verifies payment. Admin payment verification is idempotent and writes an audit log. Refund completion requires an actual refund reference and method.

## Endpoints

- Customer: `POST /api/arrangements`, `GET /api/arrangements/:requestNumber?token=...`, multipart `POST /api/arrangements/:requestNumber/advance/evidence`.
- Authenticated customer: `GET /api/arrangements/mine`.
- Admin: `GET /api/admin/product-requests`, `GET /api/admin/product-requests/:id`, `PATCH /api/admin/product-requests/:id/workflow`, `POST /api/admin/product-requests/:id/payment/decision`, and `POST /api/admin/product-requests/:id/refund`.

Payment proof is stored outside public media under `PRIVATE_EVIDENCE_STORAGE_ROOT` and served only after tracking-token or admin authorization. Set `PRIVATE_EVIDENCE_MAX_BYTES` to enforce the upload limit.

Existing MongoDB documents remain readable because legacy arrangement statuses and payment states are retained as compatibility enum values. Deploy the backend and admin together, verify the private evidence directory permissions, and run the existing MongoDB backup/migration process before production rollout.
