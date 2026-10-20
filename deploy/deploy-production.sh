#!/usr/bin/env bash
set -euo pipefail

# Run from a checked-out backend release. Do not run against an unreviewed
# working tree. This script intentionally performs no reset, seed, or rollback.
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
PROJECT_ROOT="${PROJECT_ROOT:-/srv/fastxhorizon/backend/current}"
BASE_SCHEMA_READY="${BASE_SCHEMA_READY:-0}"
DEPLOY_LOCK="${DEPLOY_LOCK:-/tmp/fastxhorizon-deploy.lock}"

if ! mkdir "$DEPLOY_LOCK" 2>/dev/null; then
    echo "Another FastXHorizon deployment is already running: $DEPLOY_LOCK" >&2
    exit 1
fi
trap 'rmdir "$DEPLOY_LOCK"' EXIT

if [[ "$BASE_SCHEMA_READY" != "1" ]]; then
    echo "Refusing routine deployment until the official 6Valley base schema has been initialized." >&2
    echo "Follow FASTXHORIZON_PRODUCTION_DEPLOYMENT.md, then set BASE_SCHEMA_READY=1." >&2
    exit 1
fi

cd "$PROJECT_ROOT"
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart

echo "Backend deployment steps completed; verify /api/health and /api/ready before serving traffic."
