require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const leaves = {
  bracelets: 'Bracelets',
  bangles: 'Bangles',
  earrings: 'Earrings',
  necklaces: 'Necklaces',
  'bridal-jewellery-sets': 'Bridal Jewellery Sets',
  'jewellery-sets': 'Jewellery Sets',
  'pendant-earrings-sets': 'Pendant & Earrings Sets',
};

async function main() {
  const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
  if (!uri.includes('fastxhorizon_local')) throw new Error('Refusing to migrate a non-local database.');
  await mongoose.connect(uri);

  await Category.findOneAndUpdate({ slug: 'jewellery', parentSlug: null }, { $set: { name: 'Jewellery', active: true } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  for (const [slug, name] of Object.entries(leaves)) {
    await Category.findOneAndUpdate({ slug, parentSlug: 'jewellery' }, { $set: { name, active: true } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  }

  const products = await Product.find({ storeName: 'FASTXHORIZON', $or: [{ categoryPath: { $all: ['Women', 'Jewellery'] } }, { category: 'Jewellery', categoryPath: { $all: ['Jewellery'] } }] }).select('_id name categoryPath').lean();
  if (products.length !== 23) throw new Error(`Expected 23 imported jewellery products, found ${products.length}; no product records were changed.`);

  const operations = products.map(product => {
    const rawLeaf = String(product.categoryPath?.[2] || product.categoryPath?.[1] || '').toLowerCase();
    const leafName = leaves[rawLeaf] || Object.values(leaves).find(value => value.toLowerCase() === rawLeaf);
    if (!leafName) throw new Error(`Unknown jewellery leaf for ${product.name}`);
    return { updateOne: { filter: { _id: product._id }, update: { $set: { category: 'Jewellery', subcategory: leafName, categoryPath: ['Jewellery', leafName], productType: rawLeaf || leafName.toLowerCase().replace(/\s+/g, '-'), updatedAt: new Date() } } } };
  });
  const result = await Product.bulkWrite(operations, { ordered: true });

  // Retain the old Women > Jewellery node as an inactive compatibility record
  // so old URLs can be redirected without deleting unrelated Women taxonomy.
  await Category.updateOne({ slug: 'jewellery', parentSlug: 'women' }, { $set: { name: 'Jewellery (moved)', active: false } });
  console.log(JSON.stringify({ matched: products.length, modified: result.modifiedCount, topLevel: 'Jewellery', children: Object.values(leaves), legacyWomenCategoryRetained: true }, null, 2));
  await mongoose.disconnect();
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
