# FastXHorizon Phase 7 — Production Hardening, SEO & Deployment Cutover

## Current blocker audit

The application is deployable in a guarded way, but it is not an unqualified
“production ready” release yet:

1. Safepay is disabled because its sandbox contract, credentials and webhook
   signature rules are not verified.
2. The vendor checkout omits original base migrations, so the general SQLite
   feature suite still has legacy-schema failures. Focused payment tests use a
   disposable schema and pass.
3. Local filesystem media is single-node only. Use object storage before
   multi-node or multi-region deployment.
4. The standalone Vite frontend is a client-rendered SPA. Product/category/
   seller SEO parity with Blade SSR is not guaranteed.
5. A single backend has restart and deployment interruption risk; zero downtime
   requires at least two backend nodes behind a load balancer.

## Environment

Use `.env.example` as a secret-free production template. Required production
values include `APP_KEY`, database credentials, Redis credentials, Passport
keys, `APP_URL`, `CORS_ALLOWED_ORIGINS`, and a real `FILESYSTEM_DISK`.

For the standalone frontend use `frontend/.env.example`:

```env
VITE_API_BASE_URL=https://api.fastxhorizon.com/api/v1
VITE_APP_URL=https://www.fastxhorizon.com
VITE_MEDIA_BASE_URL=
VITE_CHECKOUT_MODE=api
VITE_OFFLINE_PAYMENT_ENABLED=true
VITE_SAFEPAY_ENABLED=false
```

Frontend flags do not enable backend capabilities; Laravel remains the final
authority.

## SEO strategy

Blade remains the SEO-safe fallback for homepage, product, category, seller,
company and legal routes. The React app can set client-side titles and metadata,
but Vite SPA metadata is not equivalent to SSR/prerendering for crawlers.

Before switching the public domain to React, either prerender those public
routes or keep them on Blade. Keep one canonical public URL per route and ensure
the sitemap contains only `https://www.fastxhorizon.com` URLs. The production
`public/robots.txt` excludes API, admin, customer, cart and checkout paths.

## Nginx

Examples are provided in:

- `deploy/nginx/frontend.fastxhorizon.com.conf`
- `deploy/nginx/api.fastxhorizon.com.conf`

They include SPA fallback, hashed-asset caching, HTTPS redirect examples,
Laravel front-controller routing, 8 MiB proof upload capacity, hidden-file
denial, and PHP 8.4 FPM. Certificate paths are intentionally not invented.

## Redis, queues and scheduler

Production should use shared Redis for cache, API sessions where required,
queues and distributed locks. Set a unique `REDIS_PREFIX` per environment.

Install `deploy/supervisor/fastxhorizon-worker.conf`, adjust the PHP/project
paths, and run workers as the web application user. Monitor queue backlog and
failed jobs.

Run the scheduler from one designated node until every scheduled command has a
verified distributed lock. The current scheduler definitions are registered in
`bootstrap/app.php`; do not run duplicate cron jobs across nodes without
`onOneServer()`/`withoutOverlapping()` protection.

Cron entry:

```cron
* * * * * cd /srv/fastxhorizon/backend/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Storage

`FILESYSTEM_DISK=local` is acceptable only for a single backend node with
backups. Payment proofs use Laravel Storage and controlled admin downloads.
Before adding nodes, move product, seller, customer and proof media to S3/R2
compatible storage and set the matching environment variables. Run
`php artisan storage:link` only when the selected local/public disk requires it;
do not overwrite an existing link blindly.

## Deployment sequence

Review the release, back up MySQL and storage, then:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
cd frontend && npm ci && npm run build
cd ..
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache   # only after verifying route compatibility
php artisan view:cache
php artisan queue:restart
curl -fsS https://api.fastxhorizon.com/api/health
curl -fsS https://api.fastxhorizon.com/api/ready
```

`deploy/deploy-production.sh` contains the backend-safe subset. It does not
reset, seed, truncate, or roll back data. Verify migrations before execution.

For one backend, maintenance/restarts can interrupt requests. For multiple
nodes: drain one node, deploy and migrate once, restart workers, verify health
and readiness, restore traffic, then repeat.

## Backup and rollback

Before deployment, make a verified MySQL dump, copy uploaded storage, preserve
the current release and store `.env` outside the repository. Restore the dump
and storage using the hosting provider’s documented restore procedure; test the
backup before cutover.

Rollback is to the previous backend release and the previous frontend `dist`.
Keep Blade routes and checkout active. If React cutover fails, point
`www.fastxhorizon.com` back to the Laravel public root and keep
`api.fastxhorizon.com` available.

## Cloudflare/CDN guidance

- Proxy the public frontend and API only after origin health checks pass.
- Use Full (strict) TLS with valid origin certificates.
- Cache hashed frontend assets aggressively.
- Do not cache authenticated API, cart, checkout, order, payment, proof-upload
  or admin responses.
- Keep `/api/health` and `/api/ready` available for load-balancer checks.
- Move media to `cdn.fastxhorizon.com` backed by S3/R2 before multi-region use.

## Validation status

The Phase 6.3 focused payment tests, Safepay fail-closed tests, PHP lint,
frontend production build, `optimize:clear`, and `view:cache` passed before this
document was written. A live production deployment, real SMTP/payment provider,
DNS, TLS and Nginx runtime were not executed in this local workspace.

## Deployment decision

Safe for a controlled single-node launch with COD and verified offline proof
payments only after operator configuration, backups, TLS, Redis and storage
checks. Not safe to claim full multi-region production readiness while local
media remains in use, SEO-sensitive routes remain SPA-only, or Safepay is
required. Safepay remains intentionally disabled.

## Optional Firebase/FCM configuration

Firebase is optional and is not required for route discovery, health checks, or
unrelated APIs. The legacy business_settings value push_notification_key
currently contains the placeholder "Put your firebase server key here."; it is
treated as unconfigured.

FirebaseServiceProvider now resolves credentials only when a Firebase feature
actually requests the messaging/auth service. Configure FIREBASE_CREDENTIALS as
a real service-account file path or validated JSON document before enabling
Firebase. The placeholder must not be replaced with a fake value. Without
credentials, Firebase topic subscription returns a controlled 503 and unrelated
Artisan commands/routes continue normally.
