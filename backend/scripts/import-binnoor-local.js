/*
 * Safe local-only importer for the Stock Manager BinNoor Fab website export.
 * Default mode is read-only. Apply only with --apply.
 *
 * Never point this script at production: it rejects non-loopback hosts and
 * databases other than fastxhorizon_local.
 */
require('dotenv').config();
const fs = require('fs');
const fsp = fs.promises;
const path = require('path');
const crypto = require('crypto');
const mongoose = require('mongoose');
const { EJSON } = require('bson');
const Product = require('../models/Product');
const Category = require('../models/category');

const DEFAULT_SOURCE = path.resolve(__dirname, '../../BinNoor Fab 1-20');
const SOURCE = path.resolve(process.env.BINNOOR_EXPORT_PATH || DEFAULT_SOURCE);
const URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const REPORT = path.resolve(__dirname, '../../others/docs/binnoor-fab-local-import-report.json');
const MEDIA_ROOT = path.resolve(process.env.MEDIA_STORAGE_ROOT || path.join(__dirname, '..', 'data', 'media'));
const PRODUCT_MEDIA_ROOT = path.join(MEDIA_ROOT, 'products');
const APPLY = process.argv.includes('--apply');
const BRAND = 'BinNoor Fab';
const FABRIC = 'SHAMRAY';
const SKU_PREFIX = 'FXH-FXH-BN-';

function assertLocal(uri) {
  const parsed = new URL(uri);
  if (!['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname)) throw new Error('Refusing non-local MongoDB host.');
  if (parsed.pathname.replace(/^\//, '') !== 'fastxhorizon_local') throw new Error('Refusing database other than fastxhorizon_local.');
  return parsed;
}

function slugify(value) {
  return String(value || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 220);
}

function sha256(data) { return crypto.createHash('sha256').update(data).digest('hex'); }
function asNumber(value, label) {
  const number = Number(value);
  if (!Number.isFinite(number) || number < 0) throw new Error(`${label} must be a non-negative number.`);
  return number;
}
function padSku(number) { return `${SKU_PREFIX}${String(number).padStart(4, '0')}`; }

async function loadExport() {
  const payload = JSON.parse(await fsp.readFile(path.join(SOURCE, 'products.json'), 'utf8'));
  const manifest = JSON.parse(await fsp.readFile(path.join(SOURCE, 'manifest.json'), 'utf8'));
  if (manifest.source !== 'FXH_STOCK_MANAGER' || manifest.formatVersion !== 3) throw new Error('Unsupported Stock Manager export format.');
  if (!Array.isArray(payload.products) || payload.products.length !== 20) throw new Error('Expected exactly 20 products.');
  if (!Array.isArray(payload.variants) || payload.variants.length !== 20) throw new Error('Expected exactly 20 variants.');
  const productsById = new Map(payload.products.map((item) => [item.importId || item.id, item]));
  const variantsByProduct = new Map();
  for (const variant of payload.variants) {
    const key = variant.productImportId || variant.product_id;
    if (!productsById.has(key)) throw new Error(`Variant ${variant.id} references an unknown product.`);
    variantsByProduct.set(key, variant);
  }
  const errors = [];
  const images = [];
  const sourceKeys = new Set();
  for (const item of payload.products) {
    const sourceKey = item.supplierKey;
    if (!sourceKey || sourceKeys.has(sourceKey)) errors.push(`Duplicate/missing supplierKey: ${sourceKey || '(missing)'}`);
    sourceKeys.add(sourceKey);
    if (item.brandName !== BRAND || item.fabricMaterial !== FABRIC || item.categorySlug !== 'clothing') errors.push(`Mapping mismatch for ${item.importId}.`);
    if (item.status !== 'draft') errors.push(`Product ${item.importId} is not draft.`);
    const original = asNumber(item.originalPrice, `${item.importId}.originalPrice`);
    const selling = asNumber(item.sellingPrice, `${item.importId}.sellingPrice`);
    if (selling > original) errors.push(`Selling price exceeds original price for ${item.importId}.`);
    const expectedDiscount = original > 0 ? Math.round(((original - selling) / original) * 10000) / 100 : 0;
    if (Number(item.discountPercentage || 0) !== expectedDiscount) errors.push(`Discount mismatch for ${item.importId}.`);
    if (!Array.isArray(item.images) || item.images.length === 0) errors.push(`Missing image reference for ${item.importId}.`);
    for (const ref of item.images || []) {
      const relative = path.normalize(ref);
      if (relative.startsWith('..') || path.isAbsolute(relative)) errors.push(`Unsafe image path ${ref}.`);
      const file = path.resolve(SOURCE, relative);
      if (!file.startsWith(`${SOURCE}${path.sep}`) || !fs.existsSync(file)) errors.push(`Missing image file ${ref}.`);
      else { const data = await fsp.readFile(file); if (!data.length) errors.push(`Empty image file ${ref}.`); else images.push({ ref, file, bytes: data.length, sha256: sha256(data) }); }
    }
  }
  if (errors.length) throw new Error(`Export validation failed:\n${errors.join('\n')}`);
  return { payload, manifest, products: payload.products, variantsByProduct, images };
}

async function currentSkuState(db) {
  const rows = await db.collection('products').find({}, { projection: { sku: 1 } }).toArray();
  const used = new Set(); let highest = 0; const invalid = [];
  for (const row of rows) {
    const sku = String(row.sku || '');
    const match = sku.match(/^FXH-FXH-[A-Z]+-([0-9]{4})$/);
    if (!match) invalid.push(sku); else { const n = Number(match[1]); used.add(n); highest = Math.max(highest, n); }
  }
  return { used, highest, invalid, count: rows.length };
}

async function ensureNameIndexCompatibility(db) {
  const indexes = await db.collection('products').indexes();
  const nameIndex = indexes.find((index) => index.name === 'name_1');
  if (nameIndex?.unique) {
    if (!APPLY) return { changed: true, action: 'drop-unique-name-index-on-apply' };
    await db.collection('products').dropIndex('name_1');
    return { changed: true, action: 'dropped-unique-name-index' };
  }
  return { changed: false, action: 'already-compatible' };
}

async function makeDocument(item, variant, assignedNumber, mediaPaths) {
  const original = asNumber(item.originalPrice, 'originalPrice');
  const selling = asNumber(item.sellingPrice, 'sellingPrice');
  const quantity = asNumber(item.quantity, 'quantity');
  const sourceKey = item.supplierKey;
  const stableSlug = `${slugify(item.slug || item.name)}-${item.sourceProductId}`;
  const sku = padSku(assignedNumber);
  const variantDoc = {
    key: `default-${item.sourceProductId}`,
    options: {}, sku, price: selling, compareAtPrice: original,
    images: mediaPaths, inventoryMode: 'NUMERIC', stock: quantity,
    availabilityStatus: quantity > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK', enabled: true,
  };
  const doc = {
    name: item.name, description: item.description || '', price: selling, currency: 'PKR',
    category: item.categoryName || 'Clothing', subcategory: '', categoryPath: [item.categoryName || 'Clothing'],
    productType: 'PHYSICAL', shortDescription: '', longDescription: item.description || '',
    tags: [FABRIC, BRAND], slug: stableSlug, storeName: 'FASTXHORIZON', image: mediaPaths[0] || '', images: mediaPaths,
    variantsEnabled: true, variantAttributes: [], variants: [variantDoc], specifications: item.specifications || [], attributes: [],
    sku, brand: BRAND, stock: quantity, inventoryMode: 'NUMERIC', availabilityStatus: quantity > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK',
    supplierKey: sourceKey, supplierName: BRAND, supplierSKU: item.sku || '', sourceHandle: item.sourceProductId,
    sourceFormat: 'FXH_STOCK_MANAGER_V3', sourcePublished: 'draft', sourceInStock: quantity > 0, sourceUrl: '',
    sourcePrice: null, confirmedSupplierCost: null, operationalCostEstimate: null, sellingPrice: selling,
    draftSellingPrice: selling, targetProfit: null, estimatedNetProfit: null, pricingStatus: 'REVIEW_REQUIRED',
    status: 'draft', featured: false, rating: 0, numReviews: 0,
  };
  await new Product(doc).validate();
  return doc;
}

async function backup(db) {
  const dir = path.resolve(__dirname, '../../others/docs', `binnoor-local-backup-${new Date().toISOString().replace(/[:.]/g, '-')}`);
  await fsp.mkdir(dir, { recursive: true });
  for (const collection of ['products', 'categories']) {
    const rows = await db.collection(collection).find({}).toArray();
    await fsp.writeFile(path.join(dir, `${collection}.ejson`), EJSON.stringify(rows, null, 2));
  }
  return dir;
}

async function main() {
  assertLocal(URI);
  const input = await loadExport();
  await mongoose.connect(URI, { serverSelectionTimeoutMS: 5000 });
  const db = mongoose.connection.db;
  const skuState = await currentSkuState(db);
  const existing = await db.collection('products').find({ supplierKey: { $in: input.products.map((p) => p.supplierKey) } }, { projection: { supplierKey: 1, sku: 1 } }).toArray();
  const existingByKey = new Map(existing.map((p) => [p.supplierKey, p]));
  const pending = input.products.filter((p) => !existingByKey.has(p.supplierKey));
  const numbers = []; let candidate = skuState.highest + 1;
  while (numbers.length < pending.length) { if (!skuState.used.has(candidate)) numbers.push(candidate); candidate += 1; }
  const category = await db.collection('categories').findOne({ slug: 'clothing', parentSlug: null });
  const report = {
    mode: APPLY ? 'apply' : 'dry-run', source: SOURCE, target: '127.0.0.1/fastxhorizon_local',
    before: { products: skuState.count, highestGlobalSku: skuState.highest, invalidSkuCount: skuState.invalid.length, existingBinNoor: existing.length },
    validation: { products: input.products.length, variants: input.payload.variants.length, images: input.images.length, missingImages: 0, brand: BRAND, fabric: FABRIC, category: 'clothing', categoryExists: Boolean(category) },
    allocation: pending.map((p, i) => ({ sourceProductId: p.sourceProductId, name: p.name, sku: padSku(numbers[i]), alreadyImported: false })),
    skippedAlreadyImported: existing.map((p) => ({ supplierKey: p.supplierKey, sku: p.sku })),
    warnings: [], errors: [],
  };
  const indexCompatibility = await ensureNameIndexCompatibility(db); report.indexCompatibility = indexCompatibility;
  if (!category) report.warnings.push('Clothing category will be created on apply.');
  if (skuState.invalid.length) report.warnings.push(`Existing invalid SKU count: ${skuState.invalid.length}; no existing SKU was changed.`);
  if (!pending.length) report.warnings.push('All 20 source products are already imported; no product writes required.');
  if (APPLY && pending.length) {
    const backupDir = await backup(db); report.backup = backupDir;
    let createdCategory = false; const insertedIds = []; const copiedFiles = [];
    try {
      if (!category) { await Category.create({ name: 'Clothing', slug: 'clothing', parentSlug: null, active: true }); createdCategory = true; }
      // Keep the counter in a dedicated collection; $max makes initialization safe
      // even if another process has already advanced it.
      const counter = db.collection('sku_counters');
      await counter.findOneAndUpdate({ _id: 'FXH_GLOBAL' }, { $max: { value: skuState.highest } }, { upsert: true });
      const reserved = await counter.findOneAndUpdate({ _id: 'FXH_GLOBAL' }, { $inc: { value: pending.length } }, { returnDocument: 'after' });
      // MongoDB driver 6 returns the document directly; older drivers wrap it
      // in `{ value: document }`. Support both without weakening the check.
      const counterDocument = reserved && reserved.value && reserved.value.value !== undefined ? reserved.value : reserved;
      const first = counterDocument.value - pending.length + 1;
      if (first !== numbers[0]) throw new Error('SKU sequence changed during allocation; retry import safely.');
      await fsp.mkdir(PRODUCT_MEDIA_ROOT, { recursive: true });
      const mediaByRef = new Map();
      for (const image of input.images) { const destination = path.join(PRODUCT_MEDIA_ROOT, path.basename(image.ref)); const bytes = await fsp.readFile(image.file); if (fs.existsSync(destination) && sha256(await fsp.readFile(destination)) !== image.sha256) throw new Error(`Media collision: ${path.basename(destination)}`); if (!fs.existsSync(destination)) { await fsp.copyFile(image.file, destination); copiedFiles.push(destination); } mediaByRef.set(image.ref, `/media/products/${path.basename(destination)}`); }
      const docs = [];
      for (let i = 0; i < pending.length; i++) { const item = pending[i]; const paths = item.images.map((ref) => mediaByRef.get(ref)); docs.push(await makeDocument(item, input.variantsByProduct.get(item.importId || item.id), first + i, paths)); }
      const inserted = await db.collection('products').insertMany(docs, { ordered: true });
      insertedIds.push(...Object.values(inserted.insertedIds));
      report.after = { products: await db.collection('products').countDocuments(), imported: docs.length, imagesCopied: copiedFiles.length };
    } catch (error) {
      if (insertedIds.length) await db.collection('products').deleteMany({ _id: { $in: insertedIds } });
      for (const file of copiedFiles) await fsp.rm(file, { force: true });
      if (createdCategory) await db.collection('categories').deleteOne({ slug: 'clothing', parentSlug: null });
      throw error;
    }
  }
  if (APPLY && !report.after) {
    report.after = { products: await db.collection('products').countDocuments(), imported: 0, imagesCopied: 0, alreadyImported: existing.length };
  }
  report.generatedAt = new Date().toISOString();
  await fsp.mkdir(path.dirname(REPORT), { recursive: true }); await fsp.writeFile(REPORT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async (error) => { console.error(error.stack || error.message); await mongoose.disconnect().catch(() => {}); process.exit(1); });
