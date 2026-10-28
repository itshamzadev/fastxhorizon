require('dotenv').config();
const fs = require('fs');
const path = require('path');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const SUPPLIER = 'YOURMART';
const STORE = 'FASTXHORIZON';
const NOTICE = 'Availability is subject to supplier confirmation. If an item is unavailable after payment, a full refund will be issued.';

function parseCsv(text) {
  const rows = []; let row = []; let field = ''; let quoted = false;
  for (let i = 0; i < text.length; i += 1) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') { field += '"'; i += 1; }
      else if (c === '"') quoted = false;
      else field += c;
    } else if (c === '"' && field.length === 0) quoted = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i += 1;
      row.push(field); field = '';
      if (row.some(value => value !== '')) rows.push(row);
      row = [];
    } else field += c;
  }
  if (field || row.length) { row.push(field); if (row.some(value => value !== '')) rows.push(row); }
  if (!rows.length) return [];
  const headers = rows.shift().map(value => String(value).trim());
  return rows.map(values => Object.fromEntries(headers.map((header, index) => [header, values[index] ?? ''])));
}

function cleanHtml(value) {
  return String(value || '').replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ').replace(/<br\s*\/?\s*>/gi, '\n').replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').replace(/&amp;/gi, '&').replace(/&quot;/gi, '"').replace(/&#39;/gi, "'").replace(/[ \t]+/g, ' ').replace(/\n\s+/g, '\n').trim().slice(0, 5000);
}

function slugify(value) { return String(value || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 150); }
function num(value) { const n = Number(String(value || '').replace(/[^0-9.-]/g, '')); return Number.isFinite(n) && n >= 0 ? n : null; }
function draftPricing(sourcePrice) {
  if (sourcePrice === null) return { draftSellingPrice: null, targetProfit: null };
  const targetProfit = sourcePrice < 500 ? Math.min(150, Math.max(30, Math.round(sourcePrice * 0.3))) : sourcePrice < 1000 ? Math.min(300, Math.max(100, Math.round(sourcePrice * 0.25))) : sourcePrice < 2500 ? Math.min(700, Math.max(200, Math.round(sourcePrice * 0.22))) : sourcePrice < 5000 ? Math.min(1200, Math.max(400, Math.round(sourcePrice * 0.2))) : sourcePrice < 10000 ? Math.min(2000, Math.max(700, Math.round(sourcePrice * 0.18))) : Math.min(6000, Math.max(1000, Math.round(sourcePrice * 0.15)));
  return { draftSellingPrice: sourcePrice + targetProfit, targetProfit };
}

function categoryFor(product) {
  const text = `${product.title} ${product.type} ${product.tags}`.toLowerCase();
  const rules = [
    ['Electronics', 'Earbuds & Headphones', /earbud|headphone|headset/],
    ['Electronics', 'Smart Watches', /smart watch|smartwatch/],
    ['Electronics', 'Mobile Accessories', /charger|cable|cover|case|mobile|phone/],
    ['Electronics', 'Smart Gadgets', /gadget|bluetooth|speaker/],
    ['Home & Kitchen', 'Kitchen Tools', /kitchen|cutter|peeler|organizer/],
    ['Beauty & Personal Care', 'Skincare', /skin|serum|cream/],
    ['Beauty & Personal Care', 'Grooming', /groom|shaver|trimmer/],
    ['Fashion & Accessories', 'Bags', /bag|wallet/],
    ['Fashion & Accessories', 'Fragrances', /perfume|fragrance/],
    ['Kids & Toys', 'Educational Toys', /toy|kids|child/],
    ['Content Creation', 'Lighting', /light|ring light/],
    ['Content Creation', 'Creator Accessories', /tripod|microphone|creator/],
  ];
  const match = rules.find(([, , expression]) => expression.test(text));
  return match ? { parent: match[0], child: match[1] } : { parent: 'Other Accessories', child: 'Uncategorized' };
}

function groupProducts(rows) {
  const groups = new Map();
  for (const row of rows) {
    const handle = String(row.Handle || row.handle || row['URL handle'] || '').trim();
    if (!handle) continue;
    if (!groups.has(handle)) groups.set(handle, { handle, rows: [] });
    groups.get(handle).rows.push(row);
  }
  return [...groups.values()].map(group => {
    const first = group.rows.find(row => row.Title || row.Name) || group.rows[0];
    const title = String(first.Title || first.Name || group.handle).trim();
    const images = [...new Set(group.rows.map(row => row['Image Src'] || row['Image URL'] || row.image).filter(Boolean).map(String))];
    const sourcePrice = num(group.rows.find(row => row['Variant Price'] || row.Price)?.['Variant Price'] || group.rows.find(row => row.Price)?.Price);
    const supplierSKU = String(group.rows.find(row => row['Variant SKU'] || row.SKU)?.['Variant SKU'] || group.rows.find(row => row.SKU)?.SKU || '').trim();
    const description = cleanHtml(first['Body (HTML)'] || first.Description || '');
    return { handle: group.handle, title, description, images, sourcePrice, supplierSKU, sourceUrl: first.URL || first['Product URL'] || '', type: first.Type || first['Product Category'] || '', tags: String(first.Tags || '').split(',').map(tag => tag.trim()).filter(Boolean) };
  });
}

function assertLocal(uri) {
  const parsed = new URL(uri);
  if (process.env.NODE_ENV === 'production' || !['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname)) throw new Error('YourMart import is restricted to a local MongoDB URI.');
}

async function main() {
  const arg = process.argv.find(value => value.startsWith('--csv='));
  const file = arg ? arg.slice(6) : process.env.YOURMART_CSV_PATH;
  if (!file) throw new Error('Provide --csv=path or YOURMART_CSV_PATH.');
  const absolute = path.resolve(file);
  if (!fs.existsSync(absolute)) throw new Error(`CSV file not found: ${absolute}`);
  const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
  assertLocal(uri);
  const products = groupProducts(parseCsv(fs.readFileSync(absolute, 'utf8')));
  const report = { supplier: SUPPLIER, rows: parseCsv(fs.readFileSync(absolute, 'utf8')).length, uniqueHandles: products.length, eligible: 0, imported: 0, skipped: 0, duplicates: 0, imagesProcessed: products.reduce((sum, item) => sum + item.images.length, 0), categoriesCreated: 0, priceReviewRequired: 0, failures: [], notice: NOTICE, dryRun: !process.argv.includes('--import') };
  const docs = products.map(item => {
    const category = categoryFor(item); const pricing = draftPricing(item.sourcePrice); report.priceReviewRequired += 1;
    return { item, category, pricing, doc: { name: item.title, description: item.description || item.title, shortDescription: item.description.slice(0, 300), longDescription: item.description, price: pricing.draftSellingPrice || 0, currency: 'PKR', category: category.parent, subcategory: category.child, categoryPath: [category.parent, category.child], productType: slugify(category.child), tags: item.tags, slug: `${slugify(item.title)}-${slugify(item.handle).slice(0, 24)}`, storeName: STORE, brand: STORE, image: item.images[0] || '', images: item.images, stock: 0, inventoryMode: 'SUPPLIER_BASED', availabilityStatus: 'AVAILABLE_TO_ORDER', status: 'draft', supplierKey: `${SUPPLIER}:${item.handle}`, supplierName: SUPPLIER, supplierSKU: item.supplierSKU, sourceHandle: item.handle, sourceUrl: item.sourceUrl, sourcePrice: item.sourcePrice, confirmedSupplierCost: null, operationalCostEstimate: null, sellingPrice: null, draftSellingPrice: pricing.draftSellingPrice, targetProfit: pricing.targetProfit, estimatedNetProfit: null, pricingStatus: 'REVIEW_REQUIRED', lastPriceReviewAt: null, rating: 0, numReviews: 0 } };
  });
  report.eligible = docs.filter(entry => entry.item.title && entry.item.images.length && entry.item.sourcePrice !== null).length;
  if (!report.dryRun) {
    if (process.env.LOCAL_IMPORT_CONFIRM !== 'YES') throw new Error('Import requires LOCAL_IMPORT_CONFIRM=YES.');
    await mongoose.connect(uri);
    const categoryKeys = new Set();
    for (const entry of docs) {
      const parentSlug = slugify(entry.category.parent);
      const childSlug = slugify(entry.category.child);
      if (!categoryKeys.has(parentSlug)) { const before = await Category.exists({ slug: parentSlug, parentSlug: null }); await Category.findOneAndUpdate({ slug: parentSlug, parentSlug: null }, { $setOnInsert: { name: entry.category.parent, slug: parentSlug, parentSlug: null, active: true } }, { upsert: true, setDefaultsOnInsert: true }); if (!before) report.categoriesCreated += 1; categoryKeys.add(parentSlug); }
      const childKey = `${parentSlug}/${childSlug}`;
      if (!categoryKeys.has(childKey)) { const before = await Category.exists({ slug: childSlug, parentSlug }); await Category.findOneAndUpdate({ slug: childSlug, parentSlug }, { $setOnInsert: { name: entry.category.child, slug: childSlug, parentSlug, active: true } }, { upsert: true, setDefaultsOnInsert: true }); if (!before) report.categoriesCreated += 1; categoryKeys.add(childKey); }
      if (!entry.item.title || !entry.item.images.length || entry.item.sourcePrice === null) { report.skipped += 1; continue; }
      try {
        const { price, status, pricingStatus, ...refreshable } = entry.doc;
        await Product.findOneAndUpdate({ supplierKey: entry.doc.supplierKey }, { $set: refreshable, $setOnInsert: { price, status, pricingStatus } }, { upsert: true, new: true, setDefaultsOnInsert: true, runValidators: true });
        report.imported += 1;
      } catch (error) { report.failures.push({ handle: entry.item.handle, message: error.message }); }
    }
    await mongoose.disconnect();
  }
  const reportPath = path.resolve(__dirname, '../../others/docs/yourmart-import-report.json');
  fs.mkdirSync(path.dirname(reportPath), { recursive: true });
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
}

if (require.main === module) main().catch(error => { console.error(`YourMart import failed: ${error.message}`); process.exitCode = 1; });
module.exports = { parseCsv, cleanHtml, groupProducts, draftPricing, categoryFor };
