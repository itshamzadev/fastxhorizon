/* Add the reviewed V24 ARTO Mall batch to the local catalog only. */
require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');
const { ingestSupplierImages } = require('../services/supplierImageIngestion');

const LOCAL_URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const SUPPLIER = 'ARTO Mall';
const SUPPLIER_CODE = 'AT';
const IMAGE_HOSTS = new Set(['artomall.com', 'i0.wp.com']);

const records = [
  ['Golden Black Four Leaf Clover Stud Earrings – Beaded Border Clover Studs for Women', 'Golden Black Four Leaf Clover Stud Earrings – Beaded Border Clover Studs for Women', 820, 949, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-151.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Nail Cuff Bangle Bracelet – Polished Hinged Nail Design Kada for Women', 'Golden Nail Cuff Bangle Bracelet – Polished Hinged Nail Design Kada for Women', 950, 1099, 'Bangles', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-153.png?fit=554%2C554&quality=92&ssl=1'],
  ['Golden Ocean Blue Oval Leaf Vine Bracelet – Blue Zircon Stone Bracelet for Women', 'Golden Ocean Blue Oval Leaf Vine Bracelet – Blue Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-137.png?fit=1080%2C1073&quality=92&ssl=1'],
  ['Golden Black Blossom Flower Vine Bracelet – Statement Black Zircon Flower Bracelet for Women', 'Golden Black Blossom Flower Vine Bracelet – Statement Black Zircon Flower Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-138.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Puffy Heart Charm Bracelet – Dainty Heart Station Chain Bracelet for Women', 'Golden Puffy Heart Charm Bracelet – Dainty Heart Station Chain Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-142.png?fit=554%2C554&quality=92&ssl=1'],
  ['Golden Layered Figaro Chain Necklace Set of 4 – Multi-Length Link Chains for Women', 'Golden Layered Figaro Chain Necklace Set of 4 – Multi-Length Link Chains for Women', 1290, 1449, 'Necklaces', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-143.png?fit=1100%2C1100&quality=92&ssl=1'],
  ['Golden Pink Rose Floral Crystal Vine Bracelet for Women', 'PINK ROSE BRACELET', 1290, 1449, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/1000069481.jpg?fit=1008%2C1008&quality=92&ssl=1'],
  ['Silver Ruby Red Leaf Vine Bracelet – Red Zircon Stone Bracelet for Women', 'Silver Ruby Red Leaf Vine Bracelet – Red Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-118.png?fit=1024%2C1024&quality=92&ssl=1'],
  ['Golden Pink Flower & Bow Vine Bracelet – Pink Zircon Stone Bracelet for Women', 'Golden Pink Flower & Bow Vine Bracelet – Pink Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-120.png?fit=1600%2C1600&quality=92&ssl=1'],
  ['Golden Ruby Red Flower & Bow Vine Bracelet – Red Zircon Stone Bracelet for Women', 'Golden Ruby Red Flower & Bow Vine Bracelet – Red Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-121.png?fit=1024%2C1024&quality=92&ssl=1'],
  ['Golden Black Flower & Bow Vine Bracelet – Adjustable Black Zircon Stone Bracelet for Women', 'Golden Black Flower & Bow Vine Bracelet – Adjustable Black Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-122.png?fit=1050%2C1400&quality=92&ssl=1'],
  ['Silver Sapphire Blue Flower & Butterfly Vine Bracelet – Adjustable Blue Zircon Stone Bracelet for Women', 'Silver Sapphire Blue Flower & Butterfly Vine Bracelet – Adjustable Blue Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-124.png?fit=495%2C619&quality=92&ssl=1'],
  ['Golden Purple Flower & Bow Vine Bracelet – Amethyst Purple Zircon Stone Bracelet for Women', 'Golden Purple Flower & Bow Vine Bracelet – Amethyst Purple Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-125.png?fit=533%2C533&quality=92&ssl=1'],
  ['Silver Purple Blossom Flower Vine Bracelet – Adjustable Amethyst Purple Zircon Bracelet for Women', 'Silver Purple Blossom Flower Vine Bracelet – Adjustable Amethyst Purple Zircon Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-127.png?fit=1000%2C1000&quality=92&ssl=1'],
  ['Silver Pink Blossom Flower Vine Bracelet – Adjustable Pink Zircon Stone Bracelet for Women', 'Silver Pink Blossom Flower Vine Bracelet – Adjustable Pink Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-128.png?fit=1163%2C1079&quality=92&ssl=1'],
  ['Golden Crystal Flower & Bow Vine Bracelet – Clear Zircon Stone Bracelet for Women', 'Golden Crystal Flower & Bow Vine Bracelet – Clear Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-129.png?fit=1024%2C1024&quality=92&ssl=1'],
  ['Golden Red Four Leaf Clover Bracelet – Adjustable 5 Clover Charm Bracelet for Women', 'Golden Red Four Leaf Clover Bracelet – Adjustable 5 Clover Charm Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-130.png?fit=1000%2C1000&quality=92&ssl=1'],
  ['Golden Flower Pendant Necklace – Five Petal Blossom Pendant with Box Chain for Women', 'Golden Flower Pendant Necklace – Five Petal Blossom Pendant with Box Chain for Women', 1290, 1449, 'Necklaces', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-132.png?fit=600%2C600&quality=92&ssl=1'],
  ['Silver Emerald Green Flower & Bow Vine Bracelet – Adjustable Green Zircon Stone Bracelet for Women', 'Silver Emerald Green Flower & Bow Vine Bracelet – Adjustable Green Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-133.png?fit=554%2C554&quality=92&ssl=1'],
  ['Golden Emerald Green Flower & Bow Vine Bracelet – Green Zircon Stone Bracelet for Women', 'Golden Emerald Green Flower & Bow Vine Bracelet – Green Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-134.png?fit=1024%2C1024&quality=92&ssl=1'],
  ['Golden Violet Flower & Bow Vine Bracelet – Adjustable Purple Zircon Stone Bracelet for Women', 'Golden Violet Flower & Bow Vine Bracelet – Adjustable Purple Zircon Stone Bracelet for Women', 1160, 1299, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-136.png?fit=1076%2C1100&quality=92&ssl=1'],
  ['Golden Multicolour Stone Filigree Kada Bangle – Traditional Bridal Cuff with Chain Charm for Women', 'Golden Multicolour Stone Filigree Kada Bangle – Traditional Bridal Cuff with Chain Charm for Women', 1680, 1849, 'Bangles', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/kada-bangle-1.jpg?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Bar Stud Chain Fringe Earrings – Minimal Long Tassel Drop Earrings for Women', 'Golden Bar Stud Chain Fringe Earrings – Minimal Long Tassel Drop Earrings for Women', 1680, 1849, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-116.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Monstera Leaf Drop Earrings – Crystal Petal Tropical Statement Earrings for Women', 'Golden Monstera Leaf Drop Earrings – Crystal Petal Tropical Statement Earrings for Women', 1680, 1849, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-103.png?fit=1254%2C1254&quality=92&ssl=1'],
];

const slugify = value => String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
const normalizedName = value => String(value).toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
const normalizedImage = value => { try { const url = new URL(value); url.search = ''; url.hash = ''; return url.toString().toLowerCase(); } catch { return String(value).toLowerCase(); } };

function assertLocal(uri) {
  const parsed = new URL(uri);
  if (!['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname) || parsed.pathname !== '/fastxhorizon_local') throw new Error('Refusing non-local database target.');
}

async function main() {
  assertLocal(LOCAL_URI);
  const parsed = new URL(LOCAL_URI);
  console.log(`Verified local database target: ${parsed.hostname}/${parsed.pathname.slice(1)}`);
  await mongoose.connect(LOCAL_URI, { serverSelectionTimeoutMS: 5000 });

  const beforeCount = await Product.countDocuments();
  const categoryMap = {};
  const categoryResults = [];
  for (const name of ['Jewellery', 'Earrings', 'Bracelets', 'Necklaces', 'Bangles']) {
    const parentSlug = name === 'Jewellery' ? null : 'jewellery';
    const slug = slugify(name);
    const existing = await Category.findOne({ slug, parentSlug });
    categoryMap[name] = existing || await Category.create({ name, slug, parentSlug, active: true });
    categoryResults.push({ name, slug, parentSlug, action: existing ? 'reused' : 'created' });
  }

  const artoProducts = await Product.find({ supplierName: SUPPLIER }).select('+supplierImageSources name slug sku supplierKey images image').lean();
  const existingNames = new Set(artoProducts.flatMap(item => [item.name, item.sourceHandle]).filter(Boolean).map(normalizedName));
  const existingKeys = new Set(artoProducts.map(item => item.supplierKey).filter(Boolean));
  const existingImages = new Set(artoProducts.flatMap(item => [item.image, ...(item.images || []), ...((item.supplierImageSources || []).map(source => source.url))]).filter(value => /^https?:\/\//i.test(value || '')).map(normalizedImage));
  const allSkus = (await Product.find({ sku: /^FXH-FXH-AT-/ }).select('sku').lean()).map(item => item.sku);
  let skuNumber = allSkus.reduce((max, value) => Math.max(max, Number(String(value).match(/(\d{4})$/)?.[1] || 0)), 0);
  const existingSlugs = new Set((await Product.find({ slug: { $exists: true, $ne: '' } }).select('slug').lean()).map(item => item.slug));
  const report = { sourceProducts: records.length, inserted: [], duplicates: [], failures: [], categories: categoryResults };
  const docs = [];

  for (const [name, sourceTitle, sourcePrice, sellingPrice, subcategory, image] of records) {
    const sourceUrl = new URL(image);
    const sourceKey = `${SUPPLIER}:${slugify(sourceTitle)}`;
    if (sourceUrl.protocol !== 'https:' || !IMAGE_HOSTS.has(sourceUrl.hostname)) {
      report.failures.push({ name, reason: 'Source image host/protocol is not approved.' });
      continue;
    }
    const duplicate = existingNames.has(normalizedName(name)) || existingNames.has(normalizedName(sourceTitle)) || existingKeys.has(sourceKey) || existingImages.has(normalizedImage(image));
    if (duplicate) {
      report.duplicates.push({ name, sourceTitle, reason: 'Existing supplier key, title or normalized source image match.' });
      continue;
    }
    try {
      const media = await ingestSupplierImages([image], { supplier: 'artomall' });
      skuNumber += 1;
      const sku = `FXH-FXH-${SUPPLIER_CODE}-${String(skuNumber).padStart(4, '0')}`;
      let slug = slugify(name); let suffix = 2;
      while (existingSlugs.has(slug)) slug = `${slugify(name)}-${suffix++}`;
      existingSlugs.add(slug);
      const description = `${name}.`;
      docs.push({ name, sourceHandle: sourceTitle, description, shortDescription: description, longDescription: description, price: sellingPrice, currency: 'PKR', category: 'Jewellery', subcategory, categoryPath: ['Jewellery', subcategory], productType: 'PHYSICAL', tags: ['jewellery', 'women', subcategory.toLowerCase(), 'artificial jewellery'], slug, sku, storeName: 'FASTXHORIZON', brand: '', image: media[0].mediaPath, images: media.map(item => item.mediaPath), stock: 0, inventoryMode: 'SUPPLIER_BASED', availabilityStatus: 'AVAILABLE_TO_ORDER', supplierKey: sourceKey, supplierName: SUPPLIER, supplierSKU: '', sourceFormat: 'ARTO_MALL_V24_LOCAL_SEED', sourcePublished: '0', sourceInStock: false, sourcePrice, sellingPrice, draftSellingPrice: sellingPrice, confirmedSupplierCost: null, pricingStatus: 'REVIEW_REQUIRED', supplierImageSources: media, status: 'draft', featured: false, rating: 0, numReviews: 0, createdAt: new Date(), updatedAt: new Date() });
      existingNames.add(normalizedName(name)); existingNames.add(normalizedName(sourceTitle)); existingKeys.add(sourceKey); existingImages.add(normalizedImage(image));
    } catch (error) {
      report.failures.push({ name, reason: error.message });
    }
  }

  if (docs.length) await Product.insertMany(docs, { ordered: true });
  report.inserted = docs.map(doc => ({ name: doc.name, sku: doc.sku, sourcePrice: doc.sourcePrice, sellingPrice: doc.sellingPrice, subcategory: doc.subcategory, status: doc.status, image: doc.image }));
  report.beforeCount = beforeCount;
  report.afterCount = await Product.countDocuments();
  report.totalDuplicatesSkipped = report.duplicates.length;
  report.totalValidationFailures = report.failures.length;
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => { console.error(error.stack || error.message); await mongoose.disconnect().catch(() => {}); process.exitCode = 1; });
