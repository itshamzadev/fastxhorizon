/* Seed the supplied ARTO Mall V26 batch into the verified local catalog only. */
require('dotenv').config();
const fs = require('fs');
const path = require('path');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');
const { ingestSupplierImages } = require('../services/supplierImageIngestion');

const LOCAL_URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const SUPPLIER = 'ARTO Mall';
const SOURCE_FORMAT = 'ARTO_MALL_V26_LOCAL_SEED';
const ALLOWED_HOSTS = new Set(['artomall.com', 'i0.wp.com']);
const SOURCE_FILE = process.env.ARTO_V26_SOURCE || 'C:\\Users\\hamza\\.codex\\attachments\\f5d14406-ceb7-4963-843d-d7d085160a45\\pasted-text.txt';

const slugify = value => String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
const normalizeName = value => String(value).toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
const normalizeImage = value => { try { const url = new URL(value); url.search = ''; url.hash = ''; return url.toString().toLowerCase(); } catch { return String(value).toLowerCase(); } };

function assertLocal(uri) {
  const target = new URL(uri);
  if (!['127.0.0.1', 'localhost', '::1'].includes(target.hostname) || target.pathname !== '/fastxhorizon_local') throw new Error('Refusing non-local database target.');
  return target;
}

function readSource() {
  if (!fs.existsSync(SOURCE_FILE)) throw new Error(`V26 source file not found: ${SOURCE_FILE}`);
  const text = fs.readFileSync(SOURCE_FILE, 'utf8');
  const blocks = [...text.matchAll(/### Product \d+\s+(.*?)(?=\r?\n### Product \d+|\r?\n## [A-Z]\. |$)/gs)].map(match => match[1]);
  const field = (block, label) => { const match = block.match(new RegExp(`\\*\\*${label}:\\*\\*\\s*(.+)`)); return match ? match[1].trim() : ''; };
  return blocks.map(block => {
    const name = field(block, 'Title').replace(/^.*RED ROSE BRACELET.*$/i, 'Golden Red Rose Crystal Vine Bracelet for Women');
    const sourcePrice = Number(field(block, 'Supplier Price').replace(/[^0-9]/g, ''));
    const sellingPrice = Number(field(block, 'Selling Price').replace(/[^0-9]/g, ''));
    const image = (block.match(/^https:\/\/[^\r\n]+/m) || [''])[0].trim();
    return { name, sourcePrice, sellingPrice, category: field(block, 'Category'), subcategory: field(block, 'Subcategory'), description: field(block, 'Description'), image };
  }).filter(item => item.name && item.image);
}

async function findOrCreateCategory(name, parentSlug = null) {
  const slug = slugify(name);
  const existing = await Category.findOne({ slug, parentSlug });
  return existing || Category.create({ name, slug, parentSlug, active: true });
}

async function main() {
  const target = assertLocal(LOCAL_URI);
  const records = readSource();
  if (records.length !== 47) throw new Error(`Expected 47 V26 records, parsed ${records.length}. Refusing partial import.`);
  console.log(`Verified local database target: ${target.hostname}/${target.pathname.slice(1)}`);
  await mongoose.connect(LOCAL_URI, { serverSelectionTimeoutMS: 5000 });

  const beforeCount = await Product.countDocuments();
  const categoryCache = new Map();
  for (const item of records) {
    if (!categoryCache.has(item.category)) categoryCache.set(item.category, await findOrCreateCategory(item.category));
    const parent = categoryCache.get(item.category);
    const key = `${item.category}:${item.subcategory}`;
    if (!categoryCache.has(key)) categoryCache.set(key, await findOrCreateCategory(item.subcategory, parent.slug));
  }

  const existing = await Product.find({ supplierName: SUPPLIER }).select('+supplierImageSources name sourceHandle slug sku supplierKey image images').lean();
  const names = new Set(existing.flatMap(item => [item.name, item.sourceHandle]).filter(Boolean).map(normalizeName));
  const keys = new Set(existing.map(item => item.supplierKey).filter(Boolean));
  const images = new Set(existing.flatMap(item => [item.image, ...(item.images || []), ...((item.supplierImageSources || []).map(source => source.url))]).filter(value => /^https?:\/\//i.test(value || '')).map(normalizeImage));
  const skus = await Product.find({ sku: /^FXH-FXH-AT-/ }).select('sku').lean();
  let skuNumber = skus.reduce((max, item) => Math.max(max, Number(String(item.sku).match(/(\d{4})$/)?.[1] || 0)), 0);
  const usedSlugs = new Set((await Product.find({ slug: { $exists: true, $ne: '' } }).select('slug').lean()).map(item => item.slug));
  const report = { target: `${target.hostname}/${target.pathname.slice(1)}`, sourceProducts: records.length, beforeCount, inserted: [], duplicates: [], failures: [], categories: [] };
  const docs = [];

  for (const item of records) {
    const parent = categoryCache.get(item.category);
    const supplierKey = `${SUPPLIER}:${slugify(item.name)}`;
    try {
      const parsed = new URL(item.image);
      if (parsed.protocol !== 'https:' || !ALLOWED_HOSTS.has(parsed.hostname)) throw new Error('Image host or protocol is not approved.');
      if (!Number.isFinite(item.sourcePrice) || !Number.isFinite(item.sellingPrice) || item.sellingPrice <= 0) throw new Error('Invalid source or selling price.');
      if (names.has(normalizeName(item.name)) || keys.has(supplierKey) || images.has(normalizeImage(item.image))) {
        report.duplicates.push({ name: item.name, reason: 'Existing title, supplier key or normalized source image match.' });
        continue;
      }
      const media = await ingestSupplierImages([item.image], { supplier: 'artomall' });
      if (!media.length || !media[0].mediaPath) throw new Error('Image ingestion did not return internal media.');
      skuNumber += 1;
      const sku = `FXH-FXH-AT-${String(skuNumber).padStart(4, '0')}`;
      let slug = slugify(item.name); let suffix = 2;
      while (usedSlugs.has(slug)) slug = `${slugify(item.name)}-${suffix++}`;
      usedSlugs.add(slug);
      const category = item.category || 'Jewellery';
      const subcategory = item.subcategory || '';
      docs.push({
        name: item.name,
        description: item.description || `${item.name}.`,
        shortDescription: (item.description || `${item.name}.`).slice(0, 300),
        longDescription: item.description || `${item.name}.`,
        price: item.sellingPrice,
        currency: 'PKR', category, subcategory, categoryPath: [category, subcategory].filter(Boolean),
        productType: 'PHYSICAL', tags: ['jewellery', 'women', subcategory.toLowerCase()], slug, sku,
        storeName: 'FASTXHORIZON', brand: '', image: media[0].mediaPath, images: media.map(entry => entry.mediaPath),
        stock: 0, inventoryMode: 'SUPPLIER_BASED', availabilityStatus: 'AVAILABLE_TO_ORDER', supplierKey,
        supplierName: SUPPLIER, supplierSKU: '', sourceFormat: SOURCE_FORMAT, sourcePublished: '0', sourceInStock: false,
        sourcePrice: item.sourcePrice, sellingPrice: item.sellingPrice, draftSellingPrice: item.sellingPrice,
        confirmedSupplierCost: null, pricingStatus: 'MANUAL_OVERRIDE', supplierImageSources: media,
        status: 'active', featured: false, rating: 0, numReviews: 0, createdAt: new Date(), updatedAt: new Date(),
      });
      names.add(normalizeName(item.name)); keys.add(supplierKey); images.add(normalizeImage(item.image));
    } catch (error) {
      report.failures.push({ name: item.name, reason: error.message });
    }
  }
  if (docs.length) await Product.insertMany(docs, { ordered: true });
  report.inserted = docs.map(doc => ({ name: doc.name, sku: doc.sku, sourcePrice: doc.sourcePrice, sellingPrice: doc.sellingPrice, category: doc.category, subcategory: doc.subcategory, status: doc.status, image: doc.image }));
  report.afterCount = await Product.countDocuments();
  report.totalDuplicatesSkipped = report.duplicates.length;
  report.totalValidationFailures = report.failures.length;
  report.categories = [...categoryCache.values()].map(category => ({ name: category.name, slug: category.slug, parentSlug: category.parentSlug }));
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => { console.error(error.stack || error.message); await mongoose.disconnect().catch(() => {}); process.exitCode = 1; });
