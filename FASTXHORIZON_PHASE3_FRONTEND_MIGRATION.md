# FastXHorizon Phase 3 — Customer Frontend Extraction

## Status

This phase creates a real, independently buildable customer frontend at
`frontend/`. It does not delete or replace the existing Laravel Blade
storefront. The Laravel app remains the backend, API host, and safe fallback.

## Migration map

| Customer area | Current implementation | Phase 3 status |
|---|---|---|
| Homepage/catalog rails | Blade plus existing v1 APIs | React safe now for catalog baseline |
| Categories | Blade and legacy API | React safe now via frontend category API |
| Product listing/search | Blade and v1 catalog API | React safe now |
| Product detail | Blade and v1 product resource | React safe now |
| Seller product listing | Blade and existing seller API | React safe now via alias |
| Reviews display | Blade and existing review API | React API service added; UI remains minimal |
| Login/register/logout | Blade sessions and Passport API | React flow added using Passport bearer token |
| Account/profile | Blade customer pages | React account summary added |
| Cart | Blade/session guest behavior and API | React API shell added; backend remains source of truth |
| Wishlist | Blade and API | React route/service added |
| Orders | Blade and existing API | React order-list shell added |
| Checkout/payment | Blade sessions, shipping, coupons, gateway callbacks | Blade temporarily; do not migrate yet |
| Addresses/coupons/tracking | Blade and mixed legacy APIs | Needs contract/resource work first |
| Wallet/returns/support/chat | Blade/customer workflows | Remains Blade temporarily |
| Admin/seller dashboards | Laravel web panels | Remains Laravel intentionally |
| Legal/static/SEO-sensitive pages | Blade/CMS rendering | Remains Blade for SEO and CMS compatibility |

## Standalone frontend

The app is a normal React/Vite project:

```text
frontend/
  src/api/client.js
  src/api/services.js
  src/App.jsx
  src/main.jsx
  src/styles.css
  package.json
  package-lock.json
  vite.config.js
```

It uses `VITE_API_BASE_URL` for the API origin and does not contain a regional
server-selection rule or hardcoded production API domain in components. Media
values are consumed from API responses; `VITE_MEDIA_BASE_URL` is available for
future CDN/object-storage URL resolution.

## Authentication decision

Laravel Passport bearer tokens remain in use because the current backend already
issues and validates them. This standalone prototype preserves the existing
`fxh_api_token` browser storage convention so it can interoperate with the
current API. This is practical but has the normal XSS exposure of localStorage;
the future hardened option is an httpOnly secure cookie/BFF or a dedicated
short-lived-token flow after the backend contract supports it. Sanctum was not
introduced.

## Blade fallback and rollback

The current Laravel storefront routes, views, controllers, checkout, payment
callbacks, admin, and seller panels were not removed. The current backend
default is `FRONTEND_MODE=blade`. Rollback from the independent frontend is a
DNS/reverse-proxy change: point `www.fastxhorizon.com` back to the Laravel
storefront. No database rollback is required.

The standalone frontend can be deployed independently at `www` while the
existing Blade routes remain available on the Laravel host during migration.

## Checkout boundary

The React cart route is intentionally a source-of-truth API shell. Checkout is
not falsely marked migrated: the existing Blade checkout remains responsible
for address selection, shipping, coupons, COD/offline payment, gateway flows,
taxes, stock checks, and order creation until those contracts are fully tested.

## SEO

The React app sets route-level document titles, but it is a client-rendered SPA
and is not equivalent to SSR/prerendered SEO. Existing Blade product/category,
legal, and static pages remain available for indexed SEO-sensitive routes until
metadata, canonical URLs, OpenGraph, structured data, sitemap behaviour, and
SSR/prerendering are migrated deliberately.

## Build and deployment

```bash
cd frontend
npm ci
npm run build
```

The output is `frontend/dist/` and can be served by Nginx/CDN. The server must
fallback unknown customer routes to `index.html`, for example:

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

Configure the backend CORS allow-list for the deployed frontend origin and set
the frontend environment before building:

```dotenv
VITE_API_BASE_URL=https://api.fastxhorizon.com/api/v1
VITE_APP_URL=https://www.fastxhorizon.com
VITE_MEDIA_BASE_URL=https://cdn.fastxhorizon.com
```

## Remaining blockers to complete separation

- Full checkout/payment API contracts and idempotency tests.
- DTO/resource normalization for cart, order detail, addresses, coupons,
  tracking, reviews, and seller detail.
- Guest cart/session merge semantics across the independent frontend.
- SSR/prerender strategy for product/category SEO.
- Migration of wallet, returns, support, chat, and customer profile workflows.
- Object storage/CDN activation in deployment configuration.
