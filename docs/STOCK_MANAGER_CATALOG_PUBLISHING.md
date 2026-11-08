# Stock Manager catalog publishing

Product edits are local-first. `products.local_revision`, `published_revision`, `publish_state`, `remote_product_id` and `remote_version` are maintained by SQLite migration 4. Product create/edit/archive operations do not enter the operational stock event queue.

Use the Electron Publishing Center:

1. Save products normally; they remain local and appear as `pending`.
2. Open **Publishing Center** and review the pending change list.
3. Select products, or use **Update Live** for all pending items.
4. Confirm the operation. The app publishes through `/api/stock-manager/catalog` using the registered device token.
5. Product and variant identity mappings are stored server-side. Retries use the stable local product ID and revision.

Catalog publication does not overwrite server stock on an existing product. Stock changes continue through the operational sync channel (`/api/stock-manager/sync/*`).

The media endpoint accepts authenticated PNG, JPEG and WebP uploads only. Files are content-validated, SHA-256 named and written under `MEDIA_STORAGE_ROOT/products`; local Windows paths are never sent to the storefront. The persistent production mount must be configured before enabling publication.

## Required environment

Backend:

```text
STOCK_MANAGER_REGISTRATION_SECRET=<server-only secret>
MEDIA_STORAGE_ROOT=/app/media
STOCK_MANAGER_MEDIA_MAX_BYTES=5242880
```

Desktop configuration uses the existing API URL/device registration settings. Never place MongoDB credentials in the desktop app.

## Safe bulk editing

Bulk tools must update SQLite through the approved application/IPC or a reviewed local service, not raw table edits. Each successful local transaction must preserve `id`/SKU, increment `local_revision`, set `publish_state=pending`, clear the previous publication error and create an audit record where the operation supports it. Publishing is never implicit.
