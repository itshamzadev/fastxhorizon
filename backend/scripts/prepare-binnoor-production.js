/*
 * BinNoor Fab production readiness/import tool.
 *
 * Default mode is --dry-run and performs no production writes. Production
 * apply requires explicit confirmations, a verified backup, and a persistent
 * media mount. Never put the URI or credentials in Git.
 */
require('dotenv').config();
const fs = require('fs');
const fsp = fs.promises;
const path = require('path');
const crypto = require('crypto');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const mode = process.argv[2] || '--dry-run';
if (!['--dry-run', '--apply'].includes(mode)) throw new Error('Use --dry-run or --apply.');
const SOURCE = path.resolve(process.env.BINNOOR_EXPORT_PATH || path.join(__dirname, '../../BinNoor Fab 1-20'));
const URI = process.env.PRODUCTION_MONGO_URI || '';
const REPORT = path.resolve(process.env.BINNOOR_PRODUCTION_REPORT || path.join(__dirname, '../../others/docs/binnoor-production-readiness.json'));
const BRAND = 'BinNoor Fab';
const MEDIA_ROOT = process.env.MEDIA_STORAGE_ROOT || '';
const SKU_PREFIX = 'FXH-FXH-BN-';

function sha256(data) { return crypto.createHash('sha256').update(data).digest('hex'); }
function slugify(value) { return String(value || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 220); }
function number(value, label) { const n = Number(value); if (!Number.isFinite(n) || n < 0) throw new Error(`${label} must be a non-negative number.`); return n; }
function sku(n) { return `${SKU_PREFIX}${String(n).padStart(4, '0')}`; }
function targetInfo(uri) {
  if (!uri) throw new Error('PRODUCTION_MONGO_URI is not configured; no production connection attempted.');
  const parsed = new URL(uri);
  if (parsed.pathname.replace(/^\//, '') !== 'fastxhorizon_production') throw new Error('URI database must be fastxhorizon_production.');
  if (['localhost', '127.0.0.1', '::1'].includes(parsed.hostname)) throw new Error('Production dry-run refuses a loopback MongoDB host.');
  return { host: parsed.hostname, database: 'fastxhorizon_production', protocol: parsed.protocol };
}
function read(name) { return JSON.parse(fs.readFileSync(path.join(SOURCE, name), 'utf8')); }

async function loadSource() {
  const manifest = read('manifest.json');
  const payload = read('products.json');
  if (manifest.source !== 'FXH_STOCK_MANAGER' || manifest.formatVersion !== 3) throw new Error('Unsupported BinNoor export format.');
  if (!Array.isArray(payload.products) || payload.products.length !== 20 || !Array.isArray(payload.variants) || payload.variants.length !== 20) throw new Error('Expected exactly 20 products and 20 variants.');
  const sourceIds = new Set(); const keys = new Set(); const images = []; const errors = [];
  for (const item of payload.products) {
    if (!item.sourceProductId || sourceIds.has(item.sourceProductId)) errors.push(`Duplicate sourceProductId: ${item.sourceProductId}`); sourceIds.add(item.sourceProductId);
    if (!item.supplierKey || keys.has(item.supplierKey)) errors.push(`Duplicate supplierKey: ${item.supplierKey}`); keys.add(item.supplierKey);
    if (item.brandName !== BRAND || item.fabricMaterial !== 'SHAMRAY' || item.categorySlug !== 'clothing' || item.status !== 'draft') errors.push(`Invalid mapping/status: ${item.sourceProductId}`);
    const original = number(item.originalPrice, `${item.sourceProductId}.originalPrice`); const selling = number(item.sellingPrice, `${item.sourceProductId}.sellingPrice`);
    if (selling > original) errors.push(`Selling price exceeds original price: ${item.sourceProductId}`);
    const expected = original > 0 ? Math.round(((original - selling) / original) * 10000) / 100 : 0;
    if (Number(item.discountPercentage || 0) !== expected) errors.push(`Discount mismatch: ${item.sourceProductId}`);
    for (const ref of item.images || []) {
      const rel = path.normalize(ref); const file = path.resolve(SOURCE, rel);
      if (path.isAbsolute(rel) || rel.startsWith('..') || !file.startsWith(`${SOURCE}${path.sep}`) || !fs.existsSync(file)) errors.push(`Missing/unsafe image: ${ref}`);
      else { const data = await fsp.readFile(file); if (!data.length) errors.push(`Empty image: ${ref}`); else images.push({ ref, filename: path.basename(file), bytes: data.length, sha256: sha256(data), source: file }); }
    }
  }
  if (errors.length) throw new Error(`Source validation failed:\n${errors.join('\n')}`);
  return { manifest, payload, images };
}

async function skuState(db) {
  const rows = await db.collection('products').find({}, { projection: { sku: 1 } }).toArray();
  const used = new Set(); let highest = 0; const invalid = [];
  for (const row of rows) { const m = String(row.sku || '').match(/^FXH-FXH-[A-Z]+-([0-9]{4})$/); if (!m) invalid.push(row.sku || ''); else { const n = Number(m[1]); used.add(n); highest = Math.max(highest, n); } }
  return { used, highest, invalid, count: rows.length };
}
function categoryKey(slug, parentSlug) { return `${slug}|${parentSlug || ''}`; }

function buildProduct(item, variant, assignedSku, mediaPaths) {
  const original = number(item.originalPrice, `${item.sourceProductId}.originalPrice`);
  const selling = number(item.sellingPrice, `${item.sourceProductId}.sellingPrice`);
  const stock = number(item.quantity, `${item.sourceProductId}.quantity`);
  const variantDoc = { key: `default-${item.sourceProductId}`, options: {}, sku: assignedSku, price: selling, compareAtPrice: original, images: mediaPaths, inventoryMode: 'NUMERIC', stock, availabilityStatus: stock > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK', enabled: true };
  return {
    name: item.name, description: item.description || '', price: selling, currency: 'PKR', category: item.categoryName || 'Clothing', subcategory: '', categoryPath: [item.categoryName || 'Clothing'],
    productType: 'PHYSICAL', shortDescription: '', longDescription: item.description || '', tags: ['SHAMRAY', BRAND], slug: `${slugify(item.slug || item.name)}-${item.sourceProductId}`,
    storeName: 'FASTXHORIZON', image: mediaPaths[0] || '', images: mediaPaths, variantsEnabled: true, variantAttributes: [], variants: [variantDoc], specifications: item.specifications || [], attributes: [],
    sku: assignedSku, brand: BRAND, stock, inventoryMode: 'NUMERIC', availabilityStatus: stock > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK', supplierKey: item.supplierKey, supplierName: BRAND,
    supplierSKU: item.sku || '', sourceHandle: item.sourceProductId, sourceFormat: 'FXH_STOCK_MANAGER_V3', sourcePublished: 'draft', sourceInStock: stock > 0, sourceUrl: '',
    sourcePrice: null, confirmedSupplierCost: null, operationalCostEstimate: null, sellingPrice: selling, draftSellingPrice: selling, targetProfit: null, estimatedNetProfit: null,
    pricingStatus: 'REVIEW_REQUIRED', status: 'draft', featured: false, rating: 0, numReviews: 0,
  };
}

async function main() {
  const source = await loadSource();
  const report = { generatedAt: new Date().toISOString(), mode: mode.slice(2), source: SOURCE, target: null, sourceValidation: { products: 20, variants: 20, images: source.images.length, missingImages: 0, brand: BRAND, fabric: 'SHAMRAY', category: 'clothing' }, production: null, comparison: null, media: { persistentRoot: MEDIA_ROOT || null, targetDirectory: MEDIA_ROOT ? path.join(MEDIA_ROOT, 'products') : null, status: MEDIA_ROOT ? 'configured' : 'blocked' }, blockers: [], warnings: [] };
  try { report.target = targetInfo(URI); } catch (e) { report.blockers.push(e.message); await fsp.mkdir(path.dirname(REPORT), { recursive: true }); await fsp.writeFile(REPORT, JSON.stringify(report, null, 2)); console.log(JSON.stringify(report, null, 2)); return; }
  if (mode === '--apply') {
    if (process.env.PRODUCTION_IMPORT_CONFIRM !== 'YES') report.blockers.push('Set PRODUCTION_IMPORT_CONFIRM=YES after reviewing this dry-run.');
    if (process.env.PRODUCTION_BACKUP_VERIFIED !== 'YES') report.blockers.push('Set PRODUCTION_BACKUP_VERIFIED=YES only after a verified mongodump backup.');
    if (!process.env.PRODUCTION_BACKUP_ARCHIVE || !fs.existsSync(path.resolve(process.env.PRODUCTION_BACKUP_ARCHIVE))) report.blockers.push('PRODUCTION_BACKUP_ARCHIVE must point to an existing verified backup archive.');
    if (process.env.PRODUCTION_MEDIA_PERSISTENT !== 'YES') report.blockers.push('Set PRODUCTION_MEDIA_PERSISTENT=YES only after confirming the persistent media mount.');
    if (!MEDIA_ROOT || !path.isAbsolute(MEDIA_ROOT)) report.blockers.push('MEDIA_STORAGE_ROOT must be an absolute path such as /app/media.');
    if (report.blockers.length) throw new Error(report.blockers.join('\n'));
  }
  await mongoose.connect(URI, { serverSelectionTimeoutMS: 10000, readPreference: 'primaryPreferred' });
  const db = mongoose.connection.db;
  if (db.databaseName !== 'fastxhorizon_production') throw new Error(`Connected to unexpected database: ${db.databaseName}`);
  const state = await skuState(db);
  const indexes = await db.collection('products').indexes();
  const nameIndex = indexes.find((i) => i.name === 'name_1');
  const products = await Product.find({}).select('sku supplierKey slug name supplierName').lean();
  const bySku = new Map(products.filter(p => p.sku).map(p => [p.sku, p]));
  const byKey = new Map(products.filter(p => p.supplierKey).map(p => [p.supplierKey, p]));
  const bySlug = new Map(products.filter(p => p.slug).map(p => [p.slug, p]));
  const categories = await Category.find({}).select('name slug parentSlug active').lean();
  const clothing = categories.find(c => categoryKey(c.slug, c.parentSlug) === 'clothing|');
  const pending = []; const skipped = []; const collisions = []; let next = state.highest + 1;
  for (const item of source.payload.products) {
    const existing = byKey.get(item.supplierKey) || bySku.get(sku(item.catalogNumber)) || bySlug.get(`${slugify(item.slug || item.name)}-${item.sourceProductId}`);
    if (existing) { skipped.push({ sourceProductId: item.sourceProductId, supplierKey: item.supplierKey, existingId: String(existing._id), existingSku: existing.sku, action: 'skip-idempotent' }); continue; }
    while (state.used.has(next)) next += 1;
    const assigned = sku(next); state.used.add(next); next += 1;
    if (bySku.has(assigned)) collisions.push({ sku: assigned, existingId: String(bySku.get(assigned)._id) });
    pending.push({ sourceProductId: item.sourceProductId, supplierKey: item.supplierKey, name: item.name, sku: assigned, slug: `${slugify(item.slug || item.name)}-${item.sourceProductId}`, status: 'draft', imageFiles: item.images.map(ref => path.basename(ref)) });
  }
  report.production = { productCount: products.length, categoryCount: categories.length, highestGlobalSku: state.highest, invalidSkuCount: state.invalid.length, productIndexes: indexes, categoryIndexes: await db.collection('categories').indexes(), uniqueNameIndex: Boolean(nameIndex?.unique), clothingCategoryExists: Boolean(clothing) };
  report.comparison = { productsReadyToInsert: pending.length, productsAlreadyPresent: skipped.length, assigned: pending, skipped, skuCollisions: collisions, categoriesToCreate: clothing ? [] : [{ name: 'Clothing', slug: 'clothing', parentSlug: null }] };
  if (nameIndex?.unique && pending.length > 1) { report.blockers.push('Production has unique name_1, but all 20 incoming products share the same name. Apply requires explicit unique-name-index migration approval.'); report.warnings.push('Dropping name_1 changes only the index; existing product documents remain unchanged.'); }
  if (state.invalid.length) report.warnings.push(`Existing invalid SKU count: ${state.invalid.length}; no existing SKU will be rewritten.`);
  if (!clothing) report.warnings.push('Clothing category is absent and would be created only during approved apply.');
  if (mode === '--dry-run') report.media.status = MEDIA_ROOT ? 'ready-to-copy-after-approval' : 'blocked-no-persistent-root';
  if (mode === '--apply') {
    if (report.blockers.length && process.env.PRODUCTION_NAME_INDEX_MIGRATION_CONFIRM !== 'YES') throw new Error(report.blockers.join('\n') + '\nSet PRODUCTION_NAME_INDEX_MIGRATION_CONFIRM=YES only after explicit approval.');
    if (collisions.length) throw new Error('SKU collisions detected; refusing apply.');
    const counter = db.collection('sku_counters');
    await counter.findOneAndUpdate({ _id: 'FXH_GLOBAL' }, { $max: { value: state.highest } }, { upsert: true });
    const reservation = await counter.findOneAndUpdate({ _id: 'FXH_GLOBAL' }, { $inc: { value: pending.length } }, { returnDocument: 'after' });
    const counterDoc = reservation && reservation.value && reservation.value.value !== undefined ? reservation.value : reservation;
    const first = counterDoc.value - pending.length + 1;
    const copied = []; const inserted = []; let categoryCreated = false; let indexDropped = false; const productCollection = db.collection('products');
    try {
      if (pending.length && first !== Number(pending[0].sku.slice(-4))) throw new Error('Global SKU counter changed; aborting before writes.');
      await fsp.mkdir(path.join(MEDIA_ROOT, 'products'), { recursive: true });
      const mediaBySource = new Map(source.images.map(image => [image.ref, image]));
      for (const image of source.images) { const target = path.join(MEDIA_ROOT, 'products', image.filename); if (fs.existsSync(target) && sha256(await fsp.readFile(target)) !== image.sha256) throw new Error(`Media collision: ${image.filename}`); if (!fs.existsSync(target)) { await fsp.copyFile(image.source, target); copied.push(target); } }
      if (!clothing) { await Category.create({ name: 'Clothing', slug: 'clothing', parentSlug: null, active: true }); categoryCreated = true; }
      if (nameIndex?.unique) { await db.collection('products').dropIndex('name_1'); indexDropped = true; }
      const variants = new Map(source.payload.variants.map(v => [v.productImportId || v.product_id, v]));
      for (let i = 0; i < pending.length; i++) {
        const item = source.payload.products.find(p => p.sourceProductId === pending[i].sourceProductId);
        const mediaPaths = item.images.map(ref => `/media/products/${path.basename(ref)}`);
        const doc = buildProduct(item, variants.get(item.importId || item.id), sku(first + i), mediaPaths);
        const valid = new Product(doc); await valid.validate();
        const created = await productCollection.insertOne(valid.toObject({ depopulate: true })); inserted.push(created.insertedId);
      }
      report.after = { products: await Product.countDocuments(), categories: await Category.countDocuments(), inserted: inserted.length, mediaCopied: copied.length };
      report.media.status = 'copied';
    } catch (error) {
      if (inserted.length) await productCollection.deleteMany({ _id: { $in: inserted } });
      for (const file of copied) await fsp.rm(file, { force: true });
      if (categoryCreated) await db.collection('categories').deleteOne({ slug: 'clothing', parentSlug: null });
      if (indexDropped) await productCollection.createIndex({ name: 1 }, { name: 'name_1', unique: true, background: true });
      throw error;
    }
  }
  await fsp.mkdir(path.dirname(REPORT), { recursive: true }); await fsp.writeFile(REPORT, JSON.stringify(report, null, 2)); console.log(JSON.stringify(report, null, 2)); await mongoose.disconnect();
}
main().catch(async error => { console.error(`Production readiness stopped: ${error.message}`); await mongoose.disconnect().catch(() => {}); process.exitCode = 1; });
