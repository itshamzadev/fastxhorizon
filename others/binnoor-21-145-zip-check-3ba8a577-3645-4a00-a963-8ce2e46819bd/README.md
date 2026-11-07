# BinNoor Fab Products 21–145 Production Migration

Generated from the supplied `21 to 145` Stock Manager export. This package contains 118 new product records, 123 variants and 123 verified image files. The source contained 125 physical units.

## Important validation result

The source manifest actually contains catalog numbers 21–138; it contains 118 new product records, not an assumed 125. Existing products matching the original 20-product package were excluded. Product names and brands are preserved exactly from the source; the batch is not forced to a single customer-facing brand.

All imports are draft, use PKR, omit internal buying costs and supplier financial data, and use stable `supplierKey`/source IDs. Images must be copied to the persistent `/app/media/products` directory.

## Required environment
`PRODUCTION_MONGO_URI` must target exactly `fastxhorizon_production`. Never put it in this package or Git. Set `BACKEND_ROOT=/app` and `MEDIA_STORAGE_ROOT=/app/media` inside the backend environment.

## Commands

From this package directory inside the backend container:

`node import-production.js --dry-run`
`node import-production.js --apply` (requires verified backup/persistence confirmations)
`node verify-production.js`
`node publish-production.js` (separate explicit approval)
`node verify-production.js --public`

Apply requires `PRODUCTION_IMPORT_CONFIRM=YES`, `PRODUCTION_BACKUP_VERIFIED=YES`, `PRODUCTION_MEDIA_PERSISTENT=YES`, `PRODUCTION_BACKUP_ARCHIVE=/absolute/path/to/verified.archive.gz` and `PRODUCTION_NAME_INDEX_MIGRATION_APPROVED=YES` only as an acknowledgement; the script never drops or changes indexes.

## Safety

Dry-run first. Back up the production products/categories/SKU counter. The importer rejects loopback/local databases, checks every SKU and image hash, inserts only missing stable source keys, never deletes products, never overwrites conflicting media, and leaves new products as draft. If production has a different global SKU head than the package range, stop and regenerate/review the package.
