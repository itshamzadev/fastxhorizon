# FASTXHORIZON BinNoor Fab Production Migration

This package contains exactly 20 draft BinNoor Fab products. It was generated from local MongoDB `fastxhorizon_local`. No production connection or write is performed by the package builder.

## Package contents

- `products.json`: 20 public/import-safe product documents
- `categories.json`: Clothing category
- `brands.json`: BinNoor Fab brand
- `media/`: 20 verified source images
- `import-binnoor-production.js`: guarded dry-run/apply importer
- `verify-binnoor-production.js`: post-import verification

Internal buying costs, margins, supplier costs, Stock Manager model numbers and supplier image metadata are excluded.

## Upload to Ubuntu/VPS

Run from the laptop (replace the SSH target and destination):

```powershell
scp -r .\binnoor-production-migration deploy@YOUR_VPS:/tmp/
bash -lc 'scp .\binnoor-production-migration.zip deploy@YOUR_VPS:/tmp/'
```

Then copy the package into the backend container or a controlled mounted working directory. Do not place the MongoDB URI in this package.

## Required environment

Set securely in the VPS/Coolify shell, never in Git:

- `PRODUCTION_MONGO_URI`: private URI whose database path is exactly `fastxhorizon_production`
- `BACKEND_ROOT`: existing backend root, normally `/app`
- `MEDIA_STORAGE_ROOT=/app/media`: persistent media root

The host volume must map `/opt/fastxhorizon/persistent-media/media:/app/media`; product files therefore go to `/app/media/products`.

## Commands

From the package directory inside the backend environment:

```bash
export BACKEND_ROOT=/app
export MEDIA_STORAGE_ROOT=/app/media
node import-binnoor-production.js --dry-run
node verify-binnoor-production.js
```

Before import, create a verified backup of products, categories and the SKU counter:

```bash
mongodump --uri \"$PRODUCTION_MONGO_URI\" --db fastxhorizon_production --collection products --collection categories --collection sku_counters --archive=/opt/fastxhorizon/backups/binnoor-pre-import.archive.gz --gzip
```

Only after reviewing the dry-run and backup:

```bash
export PRODUCTION_IMPORT_CONFIRM=YES
export PRODUCTION_BACKUP_VERIFIED=YES
export PRODUCTION_BACKUP_ARCHIVE=/opt/fastxhorizon/backups/binnoor-pre-import.archive.gz
export PRODUCTION_MEDIA_PERSISTENT=YES
export PRODUCTION_NAME_INDEX_MIGRATION_APPROVED=YES
node import-binnoor-production.js --apply
node verify-binnoor-production.js
```

The importer refuses non-production databases, loopback URIs, SKU collisions, missing media, unverified backups, non-persistent media roots and incompatible unique `name_1` indexes. It is idempotent by `supplierKey` and never deletes unrelated products.

## Publishing

Import remains draft. Publishing must be a separate, reviewed operation limited to these exact supplier keys/SKUs. Use the existing authenticated admin API or a reviewed server-side script; never run a broad status update. Verify public URLs after publishing.

## Rollback

Stop the backend import job, restore only the backed-up catalog collections in a controlled maintenance window, and remove only the 20 package media files if required. Do not use `dropDatabase`. Preserve orders, users, payments and admins.
