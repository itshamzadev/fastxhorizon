/* Export catalog-only data from the local database for a reviewed production import. */
require('dotenv').config();
const fs = require('fs');
const path = require('path');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const localUri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const parsed = new URL(localUri);
if (!['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname)) {
  throw new Error('This export script only reads a loopback/local MongoDB URI.');
}

async function main() {
  await mongoose.connect(localUri);
  const [categories, products] = await Promise.all([
    Category.find({ active: true }).sort({ parentSlug: 1, slug: 1 }).lean(),
    Product.find({ status: 'active' }).sort({ slug: 1 }).lean(),
  ]);
  const payload = {
    schemaVersion: 1,
    exportedAt: new Date().toISOString(),
    source: 'local-loopback-only',
    categories: categories.map(({ _id, __v, createdAt, updatedAt, ...category }) => category),
    products: products.map(({ _id, __v, createdAt, updatedAt, ...product }) => ({
      ...product,
      // The three local jewellery batches were explicitly priced in PKR, but
      // legacy documents predate the currency field. Normalize only that
      // known company-owned catalogue scope; never relabel other records.
      ...(product.category === 'Jewellery' && product.storeName === 'FASTXHORIZON' && !product.currency
        ? { currency: 'PKR' }
        : {}),
    })),
  };
  const output = path.resolve(__dirname, '../../others/docs/catalog-export.json');
  fs.writeFileSync(output, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
  console.log(`Exported ${payload.categories.length} categories and ${payload.products.length} products to ${output}`);
  await mongoose.disconnect();
}

main().catch(async (error) => {
  console.error(error.message);
  await mongoose.disconnect().catch(() => {});
  process.exit(1);
});
