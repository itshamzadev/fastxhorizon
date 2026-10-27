/* Validate a reviewed catalog export without connecting to any database. */
require('dotenv').config();
const fs = require('fs');
const path = require('path');

const exportPath = process.env.CATALOG_EXPORT_PATH
  ? path.resolve(process.env.CATALOG_EXPORT_PATH)
  : path.resolve(__dirname, '../../others/docs/catalog-export.json');
const payload = JSON.parse(fs.readFileSync(exportPath, 'utf8'));
if (payload.schemaVersion !== 1 || !Array.isArray(payload.categories) || !Array.isArray(payload.products)) {
  throw new Error('Malformed or unsupported catalog export.');
}

const errors = [];
const slugs = new Set();
const categoryKeys = new Set();
for (const category of payload.categories) {
  const key = `${category.slug}|${category.parentSlug || ''}`;
  if (!category.name || !category.slug || categoryKeys.has(key)) errors.push(`Invalid or duplicate category: ${key}`);
  categoryKeys.add(key);
}
for (const product of payload.products) {
  if (!product.name || !product.slug || slugs.has(product.slug)) errors.push(`Invalid or duplicate product slug: ${product.slug || '(missing)'}`);
  if (product.currency !== 'PKR') errors.push(`${product.slug}: currency must be PKR`);
  if (product.category !== 'Jewellery') errors.push(`${product.slug}: category must be Jewellery`);
  if (product.storeName !== 'FASTXHORIZON') errors.push(`${product.slug}: storeName must be FASTXHORIZON`);
  if (product.inventoryMode !== 'AVAILABILITY_BASED' || product.availabilityStatus !== 'IN_STOCK') errors.push(`${product.slug}: availability inventory is not IN_STOCK`);
  if (!product.image || !/^https?:\/\//i.test(product.image)) errors.push(`${product.slug}: image URL is missing or invalid`);
  slugs.add(product.slug);
}
if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}
console.log(`Catalog export valid: ${payload.categories.length} categories, ${payload.products.length} products.`);
