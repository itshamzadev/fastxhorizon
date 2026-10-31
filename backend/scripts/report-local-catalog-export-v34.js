/* Generate a verification report from raw local MongoDB collections. */
require('dotenv').config();
const fs = require('fs');
const path = require('path');
const mongoose = require('mongoose');

const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const expected = new URL(uri);
if (!['127.0.0.1', 'localhost', '::1'].includes(expected.hostname) || expected.pathname !== '/fastxhorizon_local') {
  throw new Error('Refusing non-local target. Expected 127.0.0.1/fastxhorizon_local.');
}
const out = path.resolve(process.env.CATALOG_FULL_EXPORT_OUT || 'others/docs/catalog-full-export-v34');

function values(doc, key) {
  const value = doc[key];
  if (Array.isArray(value)) return value.flatMap(item => typeof item === 'string' ? [item] : item && typeof item.mediaPath === 'string' ? [item.mediaPath] : []);
  return typeof value === 'string' ? [value] : [];
}

async function main() {
  await mongoose.connect(uri, { serverSelectionTimeoutMS: 5000 });
  const db = mongoose.connection.db;
  if (db.databaseName !== 'fastxhorizon_local') throw new Error(`Unexpected database: ${db.databaseName}`);
  const products = await db.collection('products').find({}).toArray();
  const categories = await db.collection('categories').find({}).toArray();
  const productIndexes = await db.collection('products').indexes();
  const categoryIndexes = await db.collection('categories').indexes();
  const mediaReferences = [...new Set(products.flatMap(product => [...values(product, 'image'), ...values(product, 'images')]).filter(item => item.startsWith('/media/products/')))]
    .map(item => item.replace(/^\//, ''));
  const inventoryModes = {};
  const statuses = {};
  for (const product of products) {
    inventoryModes[product.inventoryMode || '(missing)'] = (inventoryModes[product.inventoryMode || '(missing)'] || 0) + 1;
    statuses[product.status || '(missing)'] = (statuses[product.status || '(missing)'] || 0) + 1;
  }
  const report = {
    generatedAt: new Date().toISOString(),
    source: { host: expected.hostname, database: db.databaseName, collections: ['products', 'categories'] },
    counts: { products: products.length, categories: categories.length, skuCount: new Set(products.map(item => item.sku).filter(Boolean)).size, mediaPathReferences: mediaReferences.length },
    imagePathReferences: mediaReferences,
    inventoryModes,
    statusDistribution: statuses,
    indexes: { products: productIndexes, categories: categoryIndexes },
    media: { relativePaths: mediaReferences, note: 'BSON dump preserves MongoDB documents; media files are packaged separately.' },
  };
  fs.mkdirSync(out, { recursive: true });
  fs.writeFileSync(path.join(out, 'catalog-export-report.json'), JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(out, 'referenced-media-paths.json'), JSON.stringify(mediaReferences, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => {
  console.error(error.stack || error.message);
  await mongoose.disconnect().catch(() => {});
  process.exitCode = 1;
});
