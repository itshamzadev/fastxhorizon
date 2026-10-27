# Production Admin and Catalog Migration

This procedure is for an authorized operator using a Coolify terminal. It does
not run automatically at application startup. Do not run it against a local or
production database until the target and backup have been checked.

## 1. Provision the first production Super Admin

The existing `backend/scripts/create-admin.js` is the only bootstrap path. It
uses the existing Admin model, bcrypt password hashing, duplicate-email check and
the normal production MFA enrollment flow. It does not print the password,
TOTP secret or recovery codes.

In the Coolify backend container, with `MONGO_URI` already set to the private
production MongoDB service, run interactively:

```sh
cd /app
NODE_ENV=production \
ADMIN_DEV_SKIP_MFA=false \
PRODUCTION_ADMIN_BOOTSTRAP_CONFIRM=YES \
npm run admin:create
```

Enter the email, a permanent password of at least 12 characters, and display
name when prompted. The command refuses loopback MongoDB URIs in production,
refuses `ADMIN_DEV_SKIP_MFA=true`, and refuses to run without the explicit
confirmation. Do not pass the password as a command-line argument. Do not put
the password, TOTP secret or recovery codes in Coolify logs or environment
history. If the email already exists, the command fails without overwriting it.

The current Admin model has no first-login password-change flag. Therefore the
operator must enter a strong permanent password during bootstrap. On first login
the production Admin flow requires TOTP enrollment/verification; the development
MFA bypass remains disabled.

After creation, sign in at `https://controlpanel.fastxhorizon.com`, complete the
Google Authenticator enrollment, save recovery codes in an approved password
manager, and verify logout/session revocation.

## 2. Export and validate the local catalog

Run locally only; the exporter rejects non-loopback MongoDB hosts:

```powershell
cd 'E:\Fast X Horizon'
$env:MONGO_URI='mongodb://127.0.0.1:27017/fastxhorizon_local'
npm --prefix backend run catalog:export-local
npm --prefix backend run catalog:validate
```

The export contains only active categories/products. It does not export Admins,
password hashes, sessions, customers, orders, payments or payment evidence. The
validator reports the actual counts and checks unique slugs, PKR currency,
company store, Jewellery category, image URLs and availability-based IN_STOCK
configuration. Do not assume the count is 23.

Review `others/docs/catalog-export.json` locally. It is ignored by Git and must
be transferred to Coolify through a private, temporary file upload or private
volume. Never place it in a public frontend asset directory.

## 3. Backup production MongoDB before import

Use the Coolify MongoDB service’s authenticated terminal/backup facility. Create
an archive on private persistent storage, not in a public application directory,
and verify its checksum and that it can be listed/restored in an isolated test.
The exact MongoDB URI must be read from the Coolify secret, never printed:

```sh
mongodump --uri="$MONGO_URI" --archive=/private-backups/fastxhorizon-before-catalog-$(date +%Y%m%d%H%M%S).archive --gzip
sha256sum /private-backups/fastxhorizon-before-catalog-*.archive
```

If Coolify does not provide a private backup volume and restore test, stop and
resolve that infrastructure blocker before importing.

## 4. Securely transfer and import

Upload the reviewed export to a temporary private path in the backend container,
for example `/tmp/catalog-export.json`, using Coolify’s private terminal/file
mechanism. Then run:

```sh
cd /app
CATALOG_EXPORT_PATH=/tmp/catalog-export.json npm run catalog:validate
PRODUCTION_IMPORT_CONFIRM=YES \
CATALOG_EXPORT_PATH=/tmp/catalog-export.json \
npm run catalog:import
rm -f /tmp/catalog-export.json
```

The importer only upserts categories by `(slug,parentSlug)` and products by
stable `slug`. It does not reset/drop MongoDB and does not modify Admins,
customers, sessions, orders, payments or refunds. Rerunning the same reviewed
export is idempotent. It requires `PRODUCTION_IMPORT_CONFIRM=YES` and a
production `MONGO_URI`; it is never called by `index.js` startup.

## 5. Read-only verification queries

Run these against the private production MongoDB using `mongosh` or the Admin
Panel, without printing connection credentials:

```javascript
db.products.countDocuments({ status: 'active', category: 'Jewellery' })
db.products.aggregate([{ $match: { status: 'active', category: 'Jewellery' } }, { $group: { _id: '$slug', count: { $sum: 1 } } }, { $match: { count: { $gt: 1 } } }])
db.products.countDocuments({ status: 'active', category: 'Jewellery', currency: 'PKR', storeName: 'FASTXHORIZON', inventoryMode: 'AVAILABILITY_BASED', availabilityStatus: 'IN_STOCK' })
db.categories.find({ slug: 'jewellery', active: true }).count()
db.paymentconfigurations.findOne({}, { automatic: 1, manual: 1, cod: 1 })
```

Also verify the customer homepage/category/search/detail pages and Admin product
list through HTTPS. Confirm Manual Advance Payment remains enabled while
Automatic Payment and COD remain disabled. Do not display numeric availability
quantities or delivery estimates.

## 6. Rollback

The importer does not delete records. First disable/unpublish only the affected
catalog records through an authorized reviewed operation. If a database restore
is required, put the applications in maintenance mode, use the verified backup,
validate counts and payment/order records, then restore service. Never drop the
production database or overwrite Admin/customer/payment data as a shortcut.
