# FastXHorizon production deployment

This is the release entrypoint for the current Laravel backend plus standalone
React/Vite customer frontend. The local database is development/demo data and
must never be copied to production.

## First deployment

1. Create two sites on the VPS:
   - api.fastxhorizon.com -> Laravel project public/
   - www.fastxhorizon.com -> the frontend dist/ directory
2. Create an empty MySQL database and a least-privilege application user.
3. Configure a production .env from .env.example. Generate a new APP_KEY;
   configure MySQL, Redis, Passport, mail, CORS, and storage values with real
   deployment secrets outside Git.
4. Initialize the original 6Valley base schema through the application
   installer, or the reviewed equivalent:
   - browse to the protected installer on the new, private origin;
   - complete database connection setup;
   - import installation/backup/database.sql;
   - complete system settings and the installer finalization steps.

   This repository does not contain all original base-table creation
   migrations. Running php artisan migrate against an empty database is not a
   substitute for this step. The supplied base SQL contains the core schema,
   business_settings, flash_deals, admin roles, and default setup. Its
   inspected SQL has no INSERT INTO products or INSERT INTO orders block.
   Verify the file and target database before making the site public.
5. Run additive migrations only after the base schema exists:

   php artisan migrate --force

   Never use migrate:fresh, db:wipe, truncate, or a production seed.
6. Install backend dependencies:

   composer install --no-dev --prefer-dist --optimize-autoloader
   php artisan passport:keys --force
   php artisan storage:link
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

7. Build the frontend from source:

   cd frontend
   npm ci
   npm run build

   Deploy frontend/dist to the frontend site. Do not commit node_modules or
   dist unless the hosting workflow explicitly requires build artifacts.
8. Configure the supplied Nginx examples, replacing project paths, certificate
   paths, and the verified PHP-FPM socket.
9. Configure Redis-backed sessions, cache, queues, and locks. Install the
   Supervisor program from deploy/supervisor/fastxhorizon-worker.conf under the
   site user where possible.
10. Add the scheduler cron entry:

    * * * * * /usr/bin/php /srv/fastxhorizon/backend/current/artisan schedule:run >> /dev/null 2>&1

11. Configure TLS, then verify /api/health, /api/ready, catalog APIs, login,
    COD checkout, and focused offline-payment tests before DNS cutover. Keep
    Safepay disabled.

## Routine redeployment

From a reviewed release checkout:

    export BASE_SCHEMA_READY=1
    export PROJECT_ROOT=/srv/fastxhorizon/backend/current
    bash deploy/deploy-production.sh

The routine script must refuse an uninitialized database and performs no reset,
seed, rollback, or database export. Build the frontend separately with npm ci
and npm run build.

## Domains, caching, storage, and rollback

- www.fastxhorizon.com serves React.
- api.fastxhorizon.com serves Laravel APIs and retains Blade/admin/seller
  fallback routes.
- Do not cache authenticated API, cart, checkout, order, payment,
  proof-upload, or admin responses.
- Local public storage is acceptable for the first single-backend launch but is
  not safe for multi-node failover; migrate to S3/R2 before adding nodes.
- Before deployment, back up MySQL, uploaded storage, .env outside Git, and the
  current release commit. Roll back to the prior release, rebuild caches, and
  restart workers. Never automatically roll database migrations backward.

## Launch defaults

- COD: verified and available.
- Offline payment: enable only after configuring real customer-facing
  instructions in admin; never commit account details.
- Safepay: disabled until official sandbox documentation, credentials, and
  webhook verification are available.
- Firebase: optional; leave FIREBASE_CREDENTIALS blank until real credentials
  are supplied.
- Blade storefront and checkout remain the immediate rollback path.
