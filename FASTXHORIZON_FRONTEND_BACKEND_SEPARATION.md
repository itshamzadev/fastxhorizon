# FastXHorizon frontend/backend separation

## Current state

The active customer storefront remains the existing Blade theme. Admin and seller
panels are also Laravel-rendered. The repository contains the Vite/Inertia entry
point used by the optional Builder addon, but the Builder source module is not
present in this installation; its compiled bundle is present under `public/build`.
The existing UI was therefore not replaced or redirected during this incremental
migration.

Laravel already exposes mobile/customer APIs under `/api/v1` and `/api/v2`, and
seller APIs under `/api/v3`. Customer API authentication uses Laravel Passport
Bearer tokens. Passport is retained for independent frontend origins; Sanctum
SPA cookies would require controlled sibling-domain deployment and shared cookie
configuration.

## New stable storefront contract

```text
GET /api/v1/frontend/products
GET /api/v1/frontend/products/{slug}
GET /api/v1/frontend/categories
```

Responses use a stable `data` envelope. Product responses expose a resource shape
instead of serializing Eloquent models wholesale. Queries reuse existing active,
public, and purchasable rules and eager-load seller/shop/category relations.

## Frontend client

```text
resources/js/lib/apiClient.js
resources/js/services/catalogService.js
```

Configure the API origin with `VITE_API_BASE_URL`. The default `/api/v1` keeps
same-origin deployment working. A separately hosted frontend can use:

```text
VITE_API_BASE_URL=https://api.fastxhorizon.com/api/v1
```

The client attaches a Passport bearer token when present and normalizes API error
messages. Token storage should move to an httpOnly/BFF approach before a high-
security production frontend deployment.

## What remains coupled

- The active Blade storefront still uses Laravel routes, sessions, CSRF, and
  server-rendered SEO metadata.
- Checkout, payment redirects, customer profile, chat, wallet, and policies remain
  on the Laravel web surface.
- Admin and seller panels remain Laravel-rendered.
- Media still resolves through Laravel storage helpers, which remain S3-ready.

## Migration sequence

1. Add remaining customer-domain contracts using the same resource/envelope shape.
2. Add services for auth, cart, checkout, orders, sellers, reviews, wishlist,
   wallet, and localization.
3. Move customer pages one domain at a time while retaining Blade fallbacks.
4. Replace wildcard CORS with explicit production frontend origins.
5. Move sessions, cache, and queues to Redis and media to shared object storage
   before placing multiple backend instances behind a load balancer.
6. Extract the Builder source addon into a separate frontend workspace when its
   source module is available.

No database reset, seed, credential change, route removal, or existing UI
replacement was performed.
