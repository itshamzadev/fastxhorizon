/* Build a portable BinNoor production package from LOCAL MongoDB only. */
require('dotenv').config();
const fs = require('fs');
const fsp = fs.promises;
const path = require('path');
const crypto = require('crypto');
const mongoose = require('mongoose');

const Product = require('../models/product');
const OUT = path.resolve(process.env.BINNOOR_PACKAGE_OUT || path.join(__dirname, '../../others/binnoor-production-migration'));
const LOCAL_URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const MEDIA_ROOT = path.resolve(process.env.MEDIA_STORAGE_ROOT || path.join(__dirname, '..', 'data', 'media'));
const RANGE = /^FXH-FXH-BN-(02(1[1-9]|2[0-9])|0230)$/;

function sha256(data) { return crypto.createHash('sha256').update(data).digest('hex'); }
function safeLocal(uri) { const u = new URL(uri); if (!['127.0.0.1', 'localhost', '::1'].includes(u.hostname) || u.pathname.slice(1) !== 'fastxhorizon_local') throw new Error('Refusing non-local or unexpected MongoDB target.'); }
function omitPrivate(product) {
  const images = (product.images?.length ? product.images : [product.image]).filter(Boolean);
  return {
    sourceProductId: product.sourceHandle,
    supplierKey: product.supplierKey,
    sku: product.sku,
    name: product.name,
    description: product.description,
    category: product.category,
    subcategory: product.subcategory || '',
    categoryPath: product.categoryPath || [],
    brand: product.brand,
    fabric: product.specifications?.find(x => x.key === 'fabric')?.value || 'SHAMRAY',
    currency: product.currency || 'PKR',
    price: product.price,
    originalPrice: product.variants?.[0]?.compareAtPrice ?? product.price,
    sellingPrice: product.sellingPrice ?? product.price,
    discountAmount: Math.max(0, (product.variants?.[0]?.compareAtPrice ?? product.price) - (product.sellingPrice ?? product.price)),
    discountPercentage: (product.variants?.[0]?.compareAtPrice ?? product.price) > 0 ? Math.round((Math.max(0, (product.variants?.[0]?.compareAtPrice ?? product.price) - (product.sellingPrice ?? product.price)) / (product.variants?.[0]?.compareAtPrice ?? product.price)) * 10000) / 100 : 0,
    stock: product.stock,
    inventoryMode: product.inventoryMode,
    availabilityStatus: product.availabilityStatus,
    status: 'draft',
    slug: product.slug,
    images,
    specifications: (product.specifications || []).filter(x => x.public !== false && x.key === 'fabric'),
  };
}

async function main() {
  safeLocal(LOCAL_URI);
  await mongoose.connect(LOCAL_URI, { serverSelectionTimeoutMS: 5000 });
  const all = await Product.find({ supplierName: 'BinNoor Fab' }).sort({ sku: 1 }).lean();
  const products = all.filter(p => RANGE.test(p.sku));
  if (products.length !== 20) throw new Error(`Expected 20 BinNoor products in range, found ${products.length}.`);
  if (new Set(products.map(p => p.sku)).size !== 20) throw new Error('Duplicate BinNoor SKU detected.');
  const source = products.map(omitPrivate);
  const errors = [];
  for (const p of source) {
    if (!p.sourceProductId || !p.supplierKey || p.brand !== 'BinNoor Fab' || p.fabric !== 'SHAMRAY' || p.category !== 'Clothing') errors.push(`Invalid mapping: ${p.sku}`);
    if (!p.images.length) errors.push(`No image: ${p.sku}`);
    if (p.sellingPrice > p.originalPrice) errors.push(`Invalid price: ${p.sku}`);
  }
  if (errors.length) throw new Error(errors.join('\n'));
  await fsp.rm(OUT, { recursive: true, force: true });
  await fsp.mkdir(path.join(OUT, 'media'), { recursive: true });
  const media = [];
  for (const p of source) {
    const refs = [];
    for (const mediaPath of p.images) {
      if (!/^\/media\/products\/[A-Za-z0-9._-]+$/i.test(mediaPath)) throw new Error(`Unsafe media path ${mediaPath}`);
      const filename = path.basename(mediaPath); const sourcePath = path.join(MEDIA_ROOT, 'products', filename); const targetPath = path.join(OUT, 'media', filename);
      if (!fs.existsSync(sourcePath)) throw new Error(`Missing local media ${sourcePath}`);
      const data = await fsp.readFile(sourcePath); await fsp.copyFile(sourcePath, targetPath);
      const entry = { sku: p.sku, filename, publicPath: `/media/products/${filename}`, bytes: data.length, sha256: sha256(data) }; media.push(entry); refs.push(entry.publicPath);
    }
    p.images = refs;
  }
  const categories = [{ name: 'Clothing', slug: 'clothing', parentSlug: null, active: true }];
  const brands = [{ name: 'BinNoor Fab', active: true }];
  const manifest = { packageType: 'fastxhorizon-binnoor-production-migration', schemaVersion: 1, source: 'fastxhorizon_local', createdAt: new Date().toISOString(), productCount: 20, imageCount: media.length, skuRange: { from: source[0].sku, to: source[source.length - 1].sku }, statusOnImport: 'draft', includesInternalCosts: false, excludes: ['confirmedSupplierCost', 'operationalCostEstimate', 'targetProfit', 'estimatedNetProfit', 'supplierSKU', 'supplierImageSources'] };
  await fsp.writeFile(path.join(OUT, 'products.json'), JSON.stringify({ schemaVersion: 1, products: source }, null, 2));
  await fsp.writeFile(path.join(OUT, 'categories.json'), JSON.stringify(categories, null, 2));
  await fsp.writeFile(path.join(OUT, 'brands.json'), JSON.stringify(brands, null, 2));
  await fsp.writeFile(path.join(OUT, 'media-manifest.json'), JSON.stringify(media, null, 2));
  await fsp.writeFile(path.join(OUT, 'manifest.json'), JSON.stringify(manifest, null, 2));
  await fsp.copyFile(path.join(__dirname, 'binnoor-production-import.template.js'), path.join(OUT, 'import-binnoor-production.js'));
  await fsp.copyFile(path.join(__dirname, 'verify-binnoor-production.template.js'), path.join(OUT, 'verify-binnoor-production.js'));
  await fsp.copyFile(path.join(__dirname, 'publish-binnoor-production.template.js'), path.join(OUT, 'publish-binnoor-production.js'));
  await fsp.writeFile(path.join(OUT, 'README.md'), `# FASTXHORIZON BinNoor Fab Production Migration\n\nThis package contains exactly 20 draft BinNoor Fab products. It was generated from local MongoDB \`fastxhorizon_local\`. No production connection or write is performed by the package builder.\n\n## Package contents\n\n- \`products.json\`: 20 public/import-safe product documents\n- \`categories.json\`: Clothing category\n- \`brands.json\`: BinNoor Fab brand\n- \`media/\`: 20 verified source images\n- \`import-binnoor-production.js\`: guarded dry-run/apply importer\n- \`verify-binnoor-production.js\`: post-import verification\n\nInternal buying costs, margins, supplier costs, Stock Manager model numbers and supplier image metadata are excluded.\n\n## Upload to Ubuntu/VPS\n\nRun from the laptop (replace the SSH target and destination):\n\n\`\`\`powershell\nscp -r .\\binnoor-production-migration deploy@YOUR_VPS:/tmp/\nbash -lc 'scp .\\binnoor-production-migration.zip deploy@YOUR_VPS:/tmp/'\n\`\`\`\n\nThen copy the package into the backend container or a controlled mounted working directory. Do not place the MongoDB URI in this package.\n\n## Required environment\n\nSet securely in the VPS/Coolify shell, never in Git:\n\n- \`PRODUCTION_MONGO_URI\`: private URI whose database path is exactly \`fastxhorizon_production\`\n- \`BACKEND_ROOT\`: existing backend root, normally \`/app\`\n- \`MEDIA_STORAGE_ROOT=/app/media\`: persistent media root\n\nThe host volume must map \`/opt/fastxhorizon/persistent-media/media:/app/media\`; product files therefore go to \`/app/media/products\`.\n\n## Commands\n\nFrom the package directory inside the backend environment:\n\n\`\`\`bash\nexport BACKEND_ROOT=/app\nexport MEDIA_STORAGE_ROOT=/app/media\nnode import-binnoor-production.js --dry-run\nnode verify-binnoor-production.js\n\`\`\`\n\nBefore import, create a verified backup of products, categories and the SKU counter:\n\n\`\`\`bash\nmongodump --uri \\"$PRODUCTION_MONGO_URI\\" --db fastxhorizon_production --collection products --collection categories --collection sku_counters --archive=/opt/fastxhorizon/backups/binnoor-pre-import.archive.gz --gzip\n\`\`\`\n\nOnly after reviewing the dry-run and backup:\n\n\`\`\`bash\nexport PRODUCTION_IMPORT_CONFIRM=YES\nexport PRODUCTION_BACKUP_VERIFIED=YES\nexport PRODUCTION_BACKUP_ARCHIVE=/opt/fastxhorizon/backups/binnoor-pre-import.archive.gz\nexport PRODUCTION_MEDIA_PERSISTENT=YES\nexport PRODUCTION_NAME_INDEX_MIGRATION_APPROVED=YES\nnode import-binnoor-production.js --apply\nnode verify-binnoor-production.js\n\`\`\`\n\nThe importer refuses non-production databases, loopback URIs, SKU collisions, missing media, unverified backups, non-persistent media roots and incompatible unique \`name_1\` indexes. It is idempotent by \`supplierKey\` and never deletes unrelated products.\n\n## Publishing\n\nImport remains draft. Publishing must be a separate, reviewed operation limited to these exact supplier keys/SKUs. Use the existing authenticated admin API or a reviewed server-side script; never run a broad status update. Verify public URLs after publishing.\n\n## Rollback\n\nStop the backend import job, restore only the backed-up catalog collections in a controlled maintenance window, and remove only the 20 package media files if required. Do not use \`dropDatabase\`. Preserve orders, users, payments and admins.\n`);
  console.log(JSON.stringify({ output: OUT, products: source.length, images: media.length, skus: source.map(x => x.sku), internalCostFieldsExported: false }, null, 2));
  await mongoose.disconnect();
}
main().catch(async e => { console.error(e.message); await mongoose.disconnect().catch(() => {}); process.exit(1); });
