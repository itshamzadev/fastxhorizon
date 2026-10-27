# Admin Integration Status

Updated 2026-10-26.

## Implemented

- Vital Admin runtime uses the real Express API by default; mock API mode is test-only unless `VITE_ENABLE_MOCK_API=true` is explicitly configured.
- Dashboard overview reads live MongoDB counts and order aggregates.
- Product list, search, pagination, create, update, archive/status changes and safe delete are connected to MongoDB.
- Customer and order list/detail reads are connected to MongoDB. Customer suspension and validated order status changes are available server-side.
- Server-side administrator session and permission middleware protects every `/api/admin/*` resource.
- The existing Fusion public product API remains the storefront data source, so products created in Admin use the same Product collection.

## Not yet implemented

- Categories, brands, variants and inventory movement ledger.
- Payment/manual-payment review, refunds, coupons and CMS content management.
- Admin-management UI, granular permission assignment UI and seller/reseller/affiliate workflows.
- Full order payment fields and collected-revenue accounting; the dashboard deliberately reports confirmed collected revenue as zero until an authoritative payment record exists.
- Product image upload pipeline and storefront CMS integration.

These incomplete areas return no invented financial figures and are not represented as complete features.
