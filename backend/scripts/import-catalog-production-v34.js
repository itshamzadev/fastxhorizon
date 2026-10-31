/*
 * Safe V34 catalog importer.
 *
 * Required environment variables:
 *   PRODUCTION_MONGO_URI             (must resolve to /fastxhorizon_production)
 *   CATALOG_MIGRATION_OUT            (optional; defaults to the V32 package)
 *   MEDIA_STORAGE_ROOT               (required for --apply; persistent production path)
 *   PRODUCTION_MEDIA_PERSISTENT=YES  (required for --apply)
 *
 * Usage:
 *   npm run catalog:import -- --dry-run
 *   npm run catalog:import -- --apply
 */
require('dotenv').config();

const fs = require('fs');
const fsp = fs.promises;
const path = require('path');
const crypto = require('crypto');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const PACKAGE = path.resolve(process.env.CATALOG_MIGRATION_OUT || 'others/docs/catalog-migration-v32');
const REPORT = path.join(PACKAGE, 'reports', 'production-import-v34.json');
const mode = process.argv.slice(2)[0];

if (!['--dry-run', '--apply'].includes(mode)) {
  throw new Error('Choose exactly one mode: --dry-run or --apply.');
}

function sha256(buffer) {
  return crypto.createHash('sha256').update(buffer).digest('hex');
}

function safeProductionTarget(uri) {
  if (!uri) throw new Error('PRODUCTION_MONGO_URI is required.');
  const parsed = new URL(uri);
  if (parsed.pathname.replace(/^\//, '') !== 'fastxhorizon_production') {
    throw new Error('Refusing target: production database must be fastxhorizon_production.');
  }
  if (['localhost', '127.0.0.1', '::1'].includes(parsed.hostname)) {
    throw new Error('Refusing loopback target for production import.');
  }
  return { host: parsed.hostname, database: 'fastxhorizon_production', protocol: parsed.protocol };
}

function readJson(name) {
  return JSON.parse(fs.readFileSync(path.join(PACKAGE, name), 'utf8'));
}

function loadPackage() {
  const manifest = readJson('manifest.json');
  const categories = readJson('categories.json');
  const products = readJson('products.json');
  const media = readJson('media-manifest.json');
  if (manifest.schemaVersion !== 2 || manifest.packageType !== 'fastxhorizon-catalog-migration') {
    throw new Error('Unsupported V32 migration package.');
  }
  if (!Array.isArray(categories) || !Array.isArray(products) || !Array.isArray(media)) {
    throw new Error('Migration package JSON has invalid structure.');
  }
  if (manifest.productCount !== products.length || manifest.categoryCount !== categories.length) {
    throw new Error('Manifest counts do not match package contents.');
  }
  return { manifest, categories, products, media };
}

async function verifyMedia(data) {
  const missing = [];
  const corrupt = [];
  for (const item of data.media) {
    if (item.status !== 'ready') continue;
    const file = path.join(PACKAGE, 'media', item.filename);
    if (!fs.existsSync(file)) {
      missing.push(item.filename);
      continue;
    }
    const buffer = await fsp.readFile(file);
    if (buffer.length !== item.bytes || sha256(buffer) !== item.sha256) corrupt.push(item.filename);
  }
  if (missing.length || corrupt.length) {
    throw new Error(`Media validation failed: ${missing.length} missing, ${corrupt.length} checksum failures.`);
  }
  return data.media.filter(item => item.status === 'ready').length;
}

function validateProducts(products, media) {
  const seen = new Set();
  const mediaBySku = new Map(media.filter(item => item.status === 'ready').map(item => [item.productSku, item]));
  for (const product of products) {
    if (!product.sku || !/^FXH-FXH-AT-\d{4}$/.test(product.sku)) throw new Error(`Invalid SKU: ${product.sku}`);
    if (seen.has(product.sku)) throw new Error(`Duplicate package SKU: ${product.sku}`);
    seen.add(product.sku);
    if (!product.name || !product.slug || product.currency !== 'PKR') throw new Error(`Invalid mandatory fields for ${product.sku}`);
    if (product.plannedImportStatus !== 'draft') throw new Error(`Non-draft import status for ${product.sku}`);
    if (typeof product.price !== 'number' || product.price < 0) throw new Error(`Invalid selling price for ${product.sku}`);
    if (product.sourcePrice != null && (typeof product.sourcePrice !== 'number' || product.sourcePrice < 0)) throw new Error(`Invalid source price for ${product.sku}`);
    if (!mediaBySku.has(product.sku)) throw new Error(`No verified media for ${product.sku}`);
  }
}

function categoryKey(slug, parentSlug) { return `${slug}|${parentSlug || ''}`; }

function mediaPath(filename) {
  return `/media/products/${filename}`;
}

async function copyMedia(data, mediaRoot) {
  const copied = [];
  await fsp.mkdir(mediaRoot, { recursive: true });
  for (const item of data.media.filter(entry => entry.status === 'ready')) {
    const source = path.join(PACKAGE, 'media', item.filename);
    const target = path.join(mediaRoot, item.filename);
    if (fs.existsSync(target)) {
      const existing = await fsp.readFile(target);
      if (sha256(existing) !== item.sha256) throw new Error(`Media filename collision with different content: ${item.filename}`);
      copied.push({ filename: item.filename, action: 'already-present' });
      continue;
    }
    await fsp.copyFile(source, target);
    copied.push({ filename: item.filename, action: 'copied' });
  }
  return copied;
}

async function main() {
  const target = safeProductionTarget(process.env.PRODUCTION_MONGO_URI);
  const data = loadPackage();
  const mediaCount = await verifyMedia(data);
  validateProducts(data.products, data.media);
  if (mode === '--apply') {
    if (process.env.PRODUCTION_MEDIA_PERSISTENT !== 'YES') throw new Error('Set PRODUCTION_MEDIA_PERSISTENT=YES only after confirming the mounted persistent media directory.');
    if (!process.env.MEDIA_STORAGE_ROOT || !path.isAbsolute(process.env.MEDIA_STORAGE_ROOT)) throw new Error('MEDIA_STORAGE_ROOT must be an absolute persistent production path.');
  }

  await mongoose.connect(process.env.PRODUCTION_MONGO_URI, { serverSelectionTimeoutMS: 10000, readPreference: 'primary' });
  const db = mongoose.connection.db;
  if (db.databaseName !== 'fastxhorizon_production') throw new Error(`Connected to unexpected database: ${db.databaseName}`);

  const before = { products: await Product.countDocuments(), categories: await Category.countDocuments() };
  const existingProducts = await Product.find({}).select('sku supplierKey sourceHandle slug name').lean();
  const existingSkus = new Map(existingProducts.filter(item => item.sku).map(item => [item.sku, item]));
  const existingSupplierKeys = new Map(existingProducts.filter(item => item.supplierKey).map(item => [item.supplierKey, item]));
  const existingSlugs = new Map(existingProducts.filter(item => item.slug).map(item => [item.slug, item]));
  const existingCategories = await Category.find({}).lean();
  const categoryMap = new Map(existingCategories.map(item => [categoryKey(item.slug, item.parentSlug), item]));

  const report = {
    generatedAt: new Date().toISOString(), mode: mode.slice(2), target,
    package: { products: data.products.length, categories: data.categories.length, verifiedMedia: mediaCount },
    before, categories: { reused: [], inserted: [], failed: [] },
    products: { inserted: [], skipped: [], failed: [] }, media: { transferred: [], failed: [] },
  };

  for (const item of data.categories) {
    const key = categoryKey(item.slug, item.parentSlug);
    const existing = categoryMap.get(key);
    if (existing) { report.categories.reused.push({ slug: item.slug, parentSlug: item.parentSlug || null, id: String(existing._id) }); continue; }
    report.categories.inserted.push({ slug: item.slug, parentSlug: item.parentSlug || null });
    if (mode === '--apply') {
      const created = await Category.create({ name: item.name, slug: item.slug, parentSlug: item.parentSlug || null, description: item.description || '', image: item.image || '', active: item.active !== false });
      categoryMap.set(key, created);
      report.categories.inserted[report.categories.inserted.length - 1].id = String(created._id);
    }
  }

  if (mode === '--apply') {
    report.media.transferred = await copyMedia(data, process.env.MEDIA_STORAGE_ROOT);
  } else {
    report.media.transferred = data.media.filter(item => item.status === 'ready').map(item => ({ filename: item.filename, action: 'dry-run-ready' }));
  }

  const mediaBySku = new Map(data.media.filter(item => item.status === 'ready').map(item => [item.productSku, item]));
  for (const raw of data.products) {
    const duplicate = existingSkus.get(raw.sku) || existingSupplierKeys.get(raw.supplierKey) || existingSlugs.get(raw.slug);
    if (duplicate) { report.products.skipped.push({ sku: raw.sku, reason: 'existing SKU/sourceKey/slug', existingId: String(duplicate._id) }); continue; }
    const media = mediaBySku.get(raw.sku);
    const image = mediaPath(media.filename);
    const product = { ...raw, image, images: [image], status: 'draft', plannedImportStatus: undefined };
    delete product.categorySlug; delete product.subcategorySlug; delete product.sourceMediaStatus; delete product.plannedImportStatus;
    product.category = raw.category;
    product.subcategory = raw.subcategory || '';
    if (mode === '--apply') {
      try {
        const created = await Product.create(product);
        report.products.inserted.push({ sku: raw.sku, id: String(created._id) });
        existingSkus.set(raw.sku, created);
      } catch (error) {
        report.products.failed.push({ sku: raw.sku, error: error.code === 11000 ? 'unique-key-collision' : error.message });
      }
    } else report.products.inserted.push({ sku: raw.sku, action: 'would-insert' });
  }

  report.after = mode === '--apply' ? { products: await Product.countDocuments(), categories: await Category.countDocuments() } : before;
  await fsp.mkdir(path.dirname(REPORT), { recursive: true });
  await fsp.writeFile(REPORT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => {
  console.error(`Catalog import stopped: ${error.message}`);
  await mongoose.disconnect().catch(() => {});
  process.exitCode = 1;
});
