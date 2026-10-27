/*
 * Explicit production catalog import. It only upserts categories/products and
 * never touches customers, orders, payments or administrator records.
 *
 * Required: CATALOG_EXPORT_PATH, MONGO_URI, PRODUCTION_IMPORT_CONFIRM=YES
 */
require('dotenv').config();
const fs = require('fs');
const path = require('path');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

if (process.env.PRODUCTION_IMPORT_CONFIRM !== 'YES') {
  throw new Error('Refusing import. Set PRODUCTION_IMPORT_CONFIRM=YES after reviewing the export and target database.');
}
if (!process.env.MONGO_URI) throw new Error('MONGO_URI is required.');

const exportPath = path.resolve(process.env.CATALOG_EXPORT_PATH || '../../others/docs/catalog-export.json');
const allowedProductFields = [
  'name', 'description', 'price', 'currency', 'category', 'subcategory', 'categoryPath',
  'productType', 'shortDescription', 'longDescription', 'tags', 'slug', 'storeName',
  'image', 'sku', 'brand', 'stock', 'inventoryMode', 'availabilityStatus', 'status',
  'featured', 'rating', 'numReviews',
];

function pick(source, fields) {
  return fields.reduce((result, field) => {
    if (source[field] !== undefined) result[field] = source[field];
    return result;
  }, {});
}

async function main() {
  const payload = JSON.parse(fs.readFileSync(exportPath, 'utf8'));
  if (payload.schemaVersion !== 1 || !Array.isArray(payload.categories) || !Array.isArray(payload.products)) {
    throw new Error('Unsupported or malformed catalog export.');
  }
  await mongoose.connect(process.env.MONGO_URI);
  let categories = 0;
  let products = 0;
  for (const category of payload.categories) {
    if (!category.slug || !category.name) continue;
    await Category.updateOne(
      { slug: category.slug, parentSlug: category.parentSlug || null },
      { $set: { name: category.name, active: category.active !== false } },
      { upsert: true },
    );
    categories += 1;
  }
  for (const raw of payload.products) {
    if (!raw.slug || !raw.name || !raw.category || raw.currency !== 'PKR') continue;
    const product = pick(raw, allowedProductFields);
    await Product.updateOne({ slug: raw.slug }, { $set: product }, { upsert: true, runValidators: true });
    products += 1;
  }
  console.log(`Imported/upserted ${categories} categories and ${products} products. No order, payment, customer, or admin records were modified.`);
  await mongoose.disconnect();
}

main().catch(async (error) => {
  console.error(error.message);
  await mongoose.disconnect().catch(() => {});
  process.exit(1);
});
