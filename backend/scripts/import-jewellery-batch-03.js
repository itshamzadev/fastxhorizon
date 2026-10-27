require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const categories = [
  ['Jewellery', 'jewellery', null],
  ['Bracelets', 'bracelets', 'jewellery'],
  ['Necklaces', 'necklaces', 'jewellery'],
];

const products = [
  ['Golden Black Blossom Flower Vine Bracelet – Statement Black Zircon Flower Bracelet for Women', 1160, 'A bold yet graceful golden vine bracelet featuring a prominent central flower with glossy black pear-cut zircon petals, complemented by smaller black floral accents. Designed to add a striking statement to elegant outfits.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-138.png?fit=1254%2C1254&ssl=1', ['black bracelet', 'flower bracelet', 'vine bracelet', 'zircon bracelet', 'women jewellery']],
  ['Golden Puffy Heart Charm Bracelet – Dainty Heart Station Chain Bracelet for Women', 1160, 'A delicate golden chain bracelet featuring glossy puffed heart charms spaced along its length. A minimalist and romantic accessory suitable for everyday wear or gifting.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-142.png?fit=554%2C554&ssl=1', ['heart bracelet', 'charm bracelet', 'gold bracelet', 'dainty bracelet', 'women jewellery']],
  ['Golden Layered Figaro Chain Necklace Set of 4 – Multi-Length Link Chains for Women', 1290, 'A coordinated set of four golden Figaro-style link chains in different lengths and thicknesses, designed for layered styling or wearing individually.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-143.png?fit=1100%2C1100&ssl=1', ['figaro chain', 'layered necklace', 'necklace set', 'gold chain', 'women jewellery']],
  ['Pink Rose Bracelet', 1290, 'A romantic golden vine bracelet decorated with sparkling pink crystal flowers and leaf accents. An elegant accessory suitable for gifting and special occasions.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/1000069481.jpg?fit=1008%2C1008&ssl=1', ['pink rose bracelet', 'rose bracelet', 'floral bracelet', 'gold bracelet', 'women jewellery']],
  ['Silver Ruby Red Leaf Vine Bracelet – Red Zircon Stone Bracelet for Women', 1160, 'An elegant silver-tone vine bracelet decorated with oval-cut ruby-red zircon-style stones and delicate leaf-inspired detailing.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-118.png?fit=1024%2C1024&ssl=1', ['red bracelet', 'leaf bracelet', 'silver bracelet', 'ruby red bracelet', 'women jewellery']],
  ['Golden Ruby Red Flower & Bow Vine Bracelet – Red Zircon Stone Bracelet for Women', 1160, 'A graceful golden vine bracelet featuring ruby-red zircon-style floral accents, bow detailing and small clear-stone leaves. Designed for an elegant, romantic appearance.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-121.png?fit=1024%2C1024&ssl=1', ['red flower bracelet', 'bow bracelet', 'vine bracelet', 'gold bracelet', 'women jewellery']],
  ['Golden Black Flower & Bow Vine Bracelet – Adjustable Black Zircon Stone Bracelet for Women', 1160, 'An adjustable golden vine bracelet decorated with black zircon-style flowers, delicate bow elements and clear-stone leaf accents. A bold accessory with an elegant finish.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-122.png?fit=1050%2C1400&ssl=1', ['black flower bracelet', 'bow bracelet', 'adjustable bracelet', 'vine bracelet', 'women jewellery']],
];

const slugify = value => value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);

async function main() {
  await mongoose.connect(process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local');
  for (const [name, slug, parentSlug] of categories) {
    await Category.findOneAndUpdate({ slug, parentSlug }, { $set: { name, active: true } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  }
  const results = [];
  for (const [name, price, description, image, tags] of products) {
    const existing = await Product.findOne({ name }).select('_id slug');
    const leaf = /necklace/i.test(name) ? 'Necklaces' : 'Bracelets';
    const productType = leaf.toLowerCase();
    const doc = await Product.findOneAndUpdate(
      { name },
      { $set: { name, description, shortDescription: description, longDescription: description, price, category: 'Jewellery', subcategory: leaf, categoryPath: ['Jewellery', leaf], productType, image, tags, slug: slugify(name), brand: 'FASTXHORIZON', storeName: 'FASTXHORIZON', stock: 0, inventoryMode: 'AVAILABILITY_BASED', availabilityStatus: 'IN_STOCK', status: 'active', rating: 0, numReviews: 0, updatedAt: new Date() }, $setOnInsert: { featured: false } },
      { upsert: true, new: true, runValidators: true },
    );
    results.push({ name: doc.name, id: String(doc._id), slug: doc.slug, action: existing ? 'updated' : 'inserted', price: doc.price, status: doc.status, inventoryMode: doc.inventoryMode, availabilityStatus: doc.availabilityStatus });
  }
  console.log(JSON.stringify({ categories, products: results }, null, 2));
  await mongoose.disconnect();
}
main().catch(error => { console.error(error); process.exitCode = 1; });
