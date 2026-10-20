# FastXHorizon Phase 2: API Separation and Multi-Node Readiness

This document describes the target architecture. It is not a claim that the
current local installation is already deployed across regions.

```text
                    USERS
                      |
                CDN / EDGE
                      |
            www.fastxhorizon.com
                SHARED FRONTEND
                      |
             api.fastxhorizon.com
                      |
          GLOBAL LOAD BALANCER
              /       |       \
             /        |        \
        ASIA API   EUROPE API   US API
           |           |          |
           +-----------+----------+
                       |
              SHARED SERVICES
               MySQL / Redis
                       |
                OBJECT STORAGE
```

## Current architecture audit

- Laravel 12.66 with Blade as the active storefront; the optional Builder
  React/Inertia bundle remains present but its source module is not installed.
- Laravel Passport is the existing API authentication mechanism. Bearer tokens
  are validated by the shared application/database configuration, not by the
  node that issued them.
- Phase 1 already exposes the stable catalog contract at
  `/api/v1/frontend/products`, `/api/v1/frontend/products/{slug}`, and
  `/api/v1/frontend/categories`, with API Resources and a centralized Axios
  client using `VITE_API_BASE_URL`.
- The local `.env` currently uses file sessions, file cache, and database
  queues. These are safe for one node only; production multi-node deployments
  must set Redis or database-backed shared equivalents.
- Checkout already has shopper-level cache locking and transactional order
  generation. Wallet order payment and wallet withdrawals already use database
  transactions, row locks, and idempotency keys in the inspected paths.
- Campaign recipient jobs now use Laravel's shared-cache uniqueness lock to
  prevent duplicate queue dispatches across workers.

## Shared-state production configuration

All application instances should use the same primary MySQL database, shared
Redis, and shared object storage. Configure, per environment:

```dotenv
APP_REGION=asia
APP_INSTANCE_ID=api-asia-01
VITE_API_BASE_URL=https://api.fastxhorizon.com/api/v1
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=shared-redis.internal
REDIS_PORT=6379
REDIS_PASSWORD=...
REDIS_DB=0
REDIS_CACHE_DB=1
FILESYSTEM_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_ENDPOINT=
CORS_ALLOWED_ORIGINS=https://www.fastxhorizon.com
```

`APP_REGION` and `APP_INSTANCE_ID` are observability metadata only. They must
not change prices, currency, seller assignment, or other business behaviour.
No production secrets are stored in this repository.

## API migration scope

The existing versioned v1 API already contains authentication, cart, product,
category, brand, seller, review, customer, order, wishlist and wallet routes.
The Phase 1 frontend contract is intentionally additive and remains the clean
starting point for extracting the customer frontend. Existing Blade routes and
admin/vendor routes are retained as a migration-safe fallback.

The next extraction step should add DTO/resource-backed frontend contracts for
cart, checkout, orders and account operations one domain at a time. Existing
controllers/services should be reused; raw Eloquent models should not become a
new frontend contract.

Phase 2 now adds stable frontend aliases for account (`/api/v1/frontend/account`),
authentication (`/api/v1/frontend/auth/login`, `/register`, `/logout`), cart,
wishlist, customer orders, seller products, and product reviews. The aliases
reuse existing authorization and business-rule controllers. Account responses
use `UserResource`; the remaining legacy response shapes should be normalized
into dedicated Resources as each domain is migrated.

## Idempotency and concurrency

- Read requests are safe to retry once in the centralized Axios client.
- POST/PUT/DELETE requests are not automatically retried by the client.
- Wallet order payment already accepts `Idempotency-Key` (or a deterministic
  fallback), locks the customer row, and checks the wallet ledger.
- Wallet ledger and customer withdrawal flows use DB transactions and row locks.
- Storefront checkout serializes placement per shopper and wraps order creation
  in a transaction.
- Payment gateway callbacks and all non-wallet order/payment paths still need a
  provider-by-provider idempotency review before global retries are enabled.
- Inventory, coupons, seller balances, and refund flows should continue to be
  audited around their existing OrderManager/service boundaries before adding
  broad locks.

## Scheduler and queues

The product-view prune and currency-rate tasks have one canonical registration
in `bootstrap/app.php`, protected with `onOneServer()` and
`withoutOverlapping()`. Run the scheduler from one or more nodes only when the
shared cache driver is configured; use a single cron entry per node with the
distributed lock enabled. Queue workers can run on multiple nodes using the
same Redis/database queue and failed-jobs store. Graceful worker restarts are
required during deployment.

## Storage and remaining local assumptions

Laravel upload helpers generally use `Storage` disks and the application has
an S3 disk configuration, so future object storage is supported by environment
configuration. A number of report/export, installer, language, addon and PDF
paths intentionally use local filesystem paths; those administrative/build
operations are not yet object-storage-safe and should not be placed behind a
shared stateless public-node assumption without a separate refactor.

## Deployment sequence for a future rolling release

1. Remove one API instance from the load balancer.
2. Deploy code and install dependencies without dev packages.
3. Run config/route/view cache optimization.
4. Run backwards-compatible database migrations once.
5. Restart that node's queue workers gracefully.
6. Check `/api/health` and `/api/ready`.
7. Return the node to traffic and repeat for the next instance.

Do not use destructive migrations or database resets during a rolling release.

## Health endpoints

- `GET /api/health` is a low-cost liveness response.
- `GET /api/ready` checks the database and, when configured/required, Redis.
  It returns HTTP 503 without exposing connection details when dependencies
  are unavailable.

## Current blockers to genuine multi-region deployment

The local defaults still use file sessions/cache and database queues; those
must be changed in deployment environment configuration. Local media is still
the fallback disk. The public Blade storefront remains same-application and
there is no complete extracted frontend workspace in this installation because
the Builder source module is absent. Admin/vendor pages remain Laravel-coupled
by design for this phase. A shared primary database is still required; active-
active or cross-region database writes are not implemented.
