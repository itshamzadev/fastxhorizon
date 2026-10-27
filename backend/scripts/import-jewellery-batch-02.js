require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const categories = [
  ['Jewellery', 'jewellery', null], ['Bangles', 'bangles', 'jewellery'], ['Bracelets', 'bracelets', 'jewellery'], ['Earrings', 'earrings', 'jewellery'], ['Pendant & Earrings Sets', 'pendant-earrings-sets', 'jewellery'],
];
const products = [
  ['Golden Filigree Twist Bangle – Openwork Diagonal Pattern Kara Bangle for Women', 1390, 'bangles', 'Classic and elegant golden filigree bangle featuring an openwork diagonal twisted pattern and fine textured detailing. A traditional-inspired accessory suitable for festive occasions and elegant everyday styling.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-144.png?fit=800%2C800&ssl=1', ['filigree bangle', 'twist bangle', 'kara', 'women jewellery']],
  ['Golden Phoenix Bird Pendant & Earrings Set – Zircon Wing Necklace with Hoop Drop Earrings for Women', 1340, 'pendant-earrings-sets', 'An elegant golden bird pendant with outstretched feathered wings and sparkling zircon-style accents, paired with complementary hoop drop earrings. Designed for a graceful statement look.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-145.png?fit=1280%2C1280&ssl=1', ['phoenix pendant', 'earrings set', 'wing necklace', 'women jewellery']],
  ['Golden Textured Monstera Leaf Drop Earrings – Crescent Stud Tropical Leaf Statement Earrings for Women', 1680, 'earrings', 'Tropical-inspired golden statement earrings featuring textured monstera leaves suspended from polished crescent-shaped studs. A bold accessory for contemporary styling.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-146.png?fit=1200%2C1200&ssl=1', ['monstera earrings', 'leaf earrings', 'drop earrings', 'women jewellery']],
  ['Golden Double Circle Pearl Stud Earrings – Modern Orbit Ring Pearl Studs for Women', 1080, 'earrings', 'Modern golden double-circle stud earrings featuring a luminous round pearl within layered open rings. A stylish combination of classic pearl detailing and contemporary design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-148.png?fit=1254%2C1254&ssl=1', ['pearl studs', 'circle earrings', 'orbit earrings', 'women jewellery']],
  ['Golden Ruby Red Rose Pendant & Earrings Set – Red Zircon Rose Necklace with Drop Earrings for Women', 1210, 'pendant-earrings-sets', 'Romantic golden rose jewellery set with ruby-red zircon-style stones forming the rose bloom and leaves. Includes a coordinating pendant necklace and earrings for a refined look.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-149.png?fit=1000%2C1000&ssl=1', ['rose pendant', 'red jewellery', 'pendant earrings set', 'women jewellery']],
  ['Golden Black Four Leaf Clover Stud Earrings – Beaded Border Clover Studs for Women', 820, 'earrings', 'Elegant black four-leaf clover stud earrings with glossy detailing and a fine golden beaded border. A classic design that pairs easily with traditional and modern outfits.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-151.png?fit=1254%2C1254&ssl=1', ['clover earrings', 'black studs', 'four leaf clover', 'women jewellery']],
  ['Golden Nail Cuff Bangle Bracelet – Polished Hinged Nail Design Kada for Women', 950, 'bangles', 'Modern golden nail-inspired cuff bangle with a polished finish, distinctive nail-head detailing and a sleek wraparound silhouette. Designed for minimal statement styling.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-153.png?fit=554%2C554&ssl=1', ['nail cuff', 'cuff bangle', 'kada', 'women jewellery']],
  ['Golden Ocean Blue Oval Leaf Vine Bracelet – Blue Zircon Stone Bracelet for Women', 1160, 'bracelets', 'Elegant golden vine bracelet decorated with deep ocean-blue oval-cut zircon-style stones and delicate leaf-inspired detailing. A vibrant accessory for festive or formal looks.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-137.png?fit=1080%2C1073&ssl=1', ['blue bracelet', 'leaf bracelet', 'vine bracelet', 'women jewellery']],
];
const slugify = value => value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
async function main() {
  await mongoose.connect(process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local');
  for (const [name, slug, parentSlug] of categories) await Category.findOneAndUpdate({ slug, parentSlug }, { $set: { name, active: true } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  const imported = [];
  for (const [name, price, leafSlug, description, image, tags] of products) {
    const slug = slugify(name);
    const leafName = categories.find(item => item[1] === leafSlug)?.[0] || leafSlug;
    const doc = await Product.findOneAndUpdate({ name }, { $set: { name, description, shortDescription: description, longDescription: description, price, category: 'Jewellery', subcategory: leafName, categoryPath: ['Jewellery', leafName], productType: leafSlug, image, tags, slug, brand: 'FASTXHORIZON', storeName: 'FASTXHORIZON', stock: 0, inventoryMode: 'AVAILABILITY_BASED', availabilityStatus: 'IN_STOCK', status: 'active', rating: 0, numReviews: 0, updatedAt: new Date() }, $setOnInsert: { featured: false } }, { upsert: true, new: true, runValidators: true });
    imported.push({ name: doc.name, id: String(doc._id), slug: doc.slug, inventoryMode: doc.inventoryMode, availabilityStatus: doc.availabilityStatus });
  }
  console.log(JSON.stringify({ categories: categories.map(item => ({ name: item[0], slug: item[1], parentSlug: item[2] })), products: imported }, null, 2));
  await mongoose.disconnect();
}
main().catch(error => { console.error(error); process.exitCode = 1; });
