require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

async function main() {
  const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
  if (!uri.includes('fastxhorizon_local')) throw new Error('Refusing to modify a non-local database.');
  await mongoose.connect(uri);
  const productDependencies = await Product.countDocuments({ $or: [{ category: 'Women' }, { categoryPath: 'Women' }] });
  if (productDependencies > 0) throw new Error(`Women category still has ${productDependencies} product dependencies; nothing was deleted.`);
  const result = await Category.deleteMany({ $or: [{ slug: 'women' }, { parentSlug: 'women' }] });
  console.log(JSON.stringify({ deletedCategoryRecords: result.deletedCount, womenProductDependencies: productDependencies, remainingProductCategory: 'Jewellery' }, null, 2));
  await mongoose.disconnect();
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
