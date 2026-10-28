# Admin CSV Import and Pricing Approval

## Scope

The Admin Panel now provides a permission-protected workflow for supplier catalog preview, idempotent import, pricing review, approval and publication. It does not automatically publish supplier records, and it does not expose supplier financial metadata through customer APIs.

## Admin routes

- `/dashboard/products/import` — upload a CSV, inspect server-side validation, and explicitly confirm an import.
- `/dashboard/products/pricing` — review source price, confirmed cost, operational cost, draft/final price and pricing status.
- `/dashboard/products/import-history` — inspect import outcomes and counts.

## API endpoints

All endpoints require an authenticated Admin session and the named server-side permission. `super_admin` is allowed by the existing RBAC middleware.

- `POST /api/admin/product-imports/preview` (`products.import`) — accepts CSV text, max 10 MB; no database writes.
- `POST /api/admin/product-imports/confirm` (`products.import`) — accepts CSV text and `X-Import-Mode: new_only|skip_existing|update_existing`; local development only, idempotent by `YOURMART:<handle>`.
- `GET /api/admin/product-imports` (`products.import`) — paginated import history.
- `GET /api/admin/pricing/products` (`products.pricing.read`) — paginated private pricing view.
- `PATCH /api/admin/pricing/products/:id` (`products.pricing.manage`) — fixed-profit, percentage-markup or manual price calculation.
- `POST /api/admin/pricing/approve` (`products.pricing.manage`) — explicit bulk approval after validation.
- `POST /api/admin/pricing/publish` (`products.publish`) — explicit bulk publication for approved/manual-override products only.

## Data rules

YouMart rows use stable `supplierKey` identifiers. Re-importing with `update_existing` refreshes source catalog fields and images but intentionally preserves confirmed supplier cost, operational estimate, selling price, pricing status and publication status. New records are inserted as `draft` + `REVIEW_REQUIRED` with `sellingPrice` unset and a draft calculation retained for review.

The current local catalog audit found 730 existing YouMart records and 23 active jewellery records. No import or publication was performed by this implementation.

## Pricing modes

- Fixed profit: confirmed cost + operational estimate + target profit.
- Percentage markup: (confirmed cost + operational estimate) × (1 + markup / 100).
- Manual: an Admin-supplied final selling price, recorded as `MANUAL_OVERRIDE`.

Supplier source price is not treated as confirmed supplier cost. Publication requires a positive final price and complete public product information; profit-based approval also requires a confirmed cost.

## Operational limitations

The current confirmation endpoint is intentionally blocked in `NODE_ENV=production`; production migration remains the separately documented catalog export/import procedure. CSV files and private pricing reports must remain outside version control. Bulk operations are capped at 500 IDs and require an explicit confirmation payload.
