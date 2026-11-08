# Stock Manager synchronization foundation

This document describes the first implemented synchronization foundation. It is
not a production deployment instruction.

## Local desktop state

The desktop SQLite database now has versioned migrations and the following
tables:

- `schema_migrations`
- `sync_devices`
- `sync_identity_mappings`
- `sync_events`

Local events are written with a stable event ID and idempotency key. Retrying a
queued event does not create another local queue row.

Product removal is archival only. The compatibility delete IPC operation now
sets `products.status = 'archived'` and records an audit log; stock movements,
sales and purchase history are retained.

## Backend endpoints

The backend exposes a separate device-authenticated namespace:

```text
POST /api/stock-manager/devices/register
POST /api/stock-manager/sync/push
GET  /api/stock-manager/sync/pull
POST /api/stock-manager/sync/ack
GET  /api/stock-manager/reconciliation
POST /api/stock-manager/identity
```

Device registration requires the server-side
`STOCK_MANAGER_REGISTRATION_SECRET`. The returned device token is hashed by the
server and encrypted with Electron `safeStorage` on Windows. Revocation is
performed by changing the device status to `revoked` in the server database.

Never put `MONGO_URI`, the registration secret or a production database
credential in the desktop application.

## Event safety

Push accepts bounded batches and rejects malformed events. Repeated event IDs
or idempotency keys are acknowledged without applying stock twice. Numeric
offline-sale, online-sale, receipt and adjustment events use conditional MongoDB
stock updates so a last unit cannot be oversold.

Online checkout records a website-origin event after a successful order. A
desktop consumer for applying pulled website events and a full reconciliation
UI remain required before claiming complete two-way synchronization.

## Development verification

Use an isolated backend database and set `STOCK_MANAGER_REGISTRATION_SECRET`
only in the local backend environment. Do not run synchronization tests against
`fastxhorizon_production`.
