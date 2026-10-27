require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');

async function main() {
  const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
  if (!uri.includes('fastxhorizon_local')) throw new Error('Refusing to update a non-local database.');
  await mongoose.connect(uri);
  const filter = { categoryPath: { $all: ['Women', 'Jewellery'] }, storeName: 'FASTXHORIZON' };
  const products = await Product.find(filter).select('_id name inventoryMode availabilityStatus stock status').lean();
  if (products.length !== 23) throw new Error(`Expected 23 imported jewellery products, found ${products.length}; no records were changed.`);
  const result = await Product.updateMany(filter, { $set: { inventoryMode: 'AVAILABILITY_BASED', availabilityStatus: 'IN_STOCK', stock: 0, status: 'active', updatedAt: new Date() } });
  console.log(JSON.stringify({ matched: result.matchedCount, modified: result.modifiedCount, products: products.map(({ _id, name }) => ({ id: String(_id), name })) }, null, 2));
  await mongoose.disconnect();
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
