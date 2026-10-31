# FASTXHORIZON Production Deployment Guide

This guide prepares the local repository for Coolify. No deployment, DNS change,
VPS change, or production database access has been performed by this task.

## Coolify resources and domains

Create separate Coolify application resources from this repository:

| Resource | Dockerfile | Internal port | Domain |
| --- | --- | ---: | --- |
| Customer | `frontend/Dockerfile` | 80 | `https://fastxhorizon.com` |
| Admin | `admin/Dockerfile` | 80 | `https://controlpanel.fastxhorizon.com` |
| API | `backend/Dockerfile` | 5000 | `https://api.fastxhorizon.com` |

Set `www.fastxhorizon.com` as an additional customer domain. The customer Nginx
configuration redirects it to the apex domain. Enable Coolify-managed HTTPS for
all three resources before accepting traffic.

## Build variables

For the Customer resource set:

```text
REACT_APP_API_BASE_URL=https://api.fastxhorizon.com/api
REACT_APP_API_USE_PROXY=false
```

For the Admin resource set:

```text
VITE_API_URL=https://api.fastxhorizon.com/api
VITE_ENABLE_MOCK_API=false
```

These are build-time values. Rebuild after changing them. Do not put API secrets
in either frontend.

## Backend environment

Use `deploy/coolify/backend.env.example` as the key list. Set real values only in
Coolify’s encrypted environment/secret interface. Required values include:

- `NODE_ENV=production`
- `PORT=5000`
- `ADMIN_BIND_HOST=0.0.0.0`
- `MONGO_URI` pointing to a private MongoDB service/network
- a unique long `JWT_SECRET`
- a unique `ADMIN_TOTP_ENCRYPTION_KEY`
- `ADMIN_DEV_SKIP_MFA=false`
- `ADMIN_COOKIE_DOMAIN=.fastxhorizon.com`
- `CORS_ORIGINS=https://fastxhorizon.com,https://www.fastxhorizon.com,https://controlpanel.fastxhorizon.com`
- `SKIP_SEED_ON_START=true`

Use a Coolify-managed MongoDB resource or a private MongoDB service with a
persistent volume and authenticated connection. Do not expose MongoDB publicly.
Back up the database before every migration and test a restore separately.

## Storage and payment evidence

Set `PAYMENT_PROOF_STORAGE_PATH=/app/storage/payment-proof` and mount a private,
persistent volume at that path. The current application still stores submitted
evidence in MongoDB base64 fields, while the protected reviewer endpoint serves
it only to authorized admins. A production object-storage adapter with private
bucket policy, malware scanning and retention rules is still required before
launch; do not mark this item complete merely because the volume exists.

Product media is served from `GET /media/products/<filename>` and must use the
same persistent mount in every backend deployment:

```text
Host:      /opt/fastxhorizon/persistent-media/media
Container: /app/media
Environment: MEDIA_STORAGE_ROOT=/app/media
Files:     /app/media/products/<filename>.webp
```

The Express route intentionally mounts only `/app/media/products`, so files
outside the product-media directory are not publicly served. After a Coolify
redeploy, verify the mount and request one known image before considering the
media fix complete.

## Safe catalog migration

The local catalog export is intentionally separate from application deployment:

```powershell
cd 'E:\Fast X Horizon'
$env:MONGO_URI='mongodb://127.0.0.1:27017/fastxhorizon_local'
npm --prefix backend run catalog:export-local
```

Review `others/docs/catalog-export.json` and transfer it through a controlled
deployment process. In a Coolify one-off command, set the production `MONGO_URI`,
`CATALOG_EXPORT_PATH`, and the explicit confirmation:

```text
PRODUCTION_IMPORT_CONFIRM=YES
```

Then run `npm run catalog:import` from the backend resource. The importer only
upserts categories by `(slug,parentSlug)` and products by `slug`; it does not
touch customers, orders, payments, sessions, admins, or financial records. It is
safe to rerun after reviewing the export. Never point the export script at a
non-loopback database, and never run the importer without a reviewed backup.

After import, verify the 23 jewellery products, top-level `Jewellery` hierarchy,
PKR currency, `AVAILABILITY_BASED`/`IN_STOCK` state, and the global payment
configuration (Manual enabled; Automatic and COD disabled).

## Health checks

- API liveness: `GET https://api.fastxhorizon.com/health`
- API readiness (MongoDB connected): `GET https://api.fastxhorizon.com/ready`
- Customer/Admin: HTTP 200 on `/`

Coolify should restart the API if `/ready` fails. Test the admin login, mandatory
production TOTP enrollment/verification, customer login, cart, manual-payment
checkout, order creation, and authorized payment review in a staging environment
before switching DNS.

## Rollback

1. Keep the previous Coolify image/version available.
2. Disable the new resource or roll back each app to the previous image.
3. Restore the database only if a migration was demonstrably incorrect, using a
   verified backup and a maintenance window.
4. Revert frontend build variables and API domain routing together.
5. Do not delete orders, payments, admins, or uploaded evidence during rollback.

## Launch blockers still requiring infrastructure decisions

- Production MongoDB host, credentials, private network and tested backup/restore.
- Coolify/VPS access and DNS/HTTPS configuration (not accessed here).
- Production secret generation and secure secret storage.
- Private object storage or a reviewed hardened policy for payment evidence.
- SMTP/email provider if confirmation or operational email workflows are enabled.
- Staging end-to-end tests against production-like HTTPS, cookies and TOTP.
