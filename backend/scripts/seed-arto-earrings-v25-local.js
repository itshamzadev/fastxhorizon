/* Add the reviewed V25 ARTO Mall earrings batch to local MongoDB only. */
require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');
const { ingestSupplierImages } = require('../services/supplierImageIngestion');

const LOCAL_URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const SUPPLIER = 'ARTO Mall';
const ALLOWED_HOSTS = new Set(['artomall.com', 'i0.wp.com']);

const records = [
  ['Sapphire Blue Zircon Bow Pearl Drop Earrings – Golden Double Chain Pearl Dangle Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-104.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Blossom Paperclip Tassel Earrings – Flower Stud Long Chain Fringe Drop Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-105.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Double Heart Pearl Tassel Drop Earrings – Long Chain Teardrop Pearl Statement Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-106.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Songbird Crystal Drop Stud Earrings – Bird Stud with Teardrop Zircon Charm for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-107.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Wavy Molten Hoop Earrings – Abstract Organic Shape Statement Hoops for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-108.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Honeybee Heart Hoop Earrings – Open Heart Bee Charm Hoops for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-109.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Triple Oval Pearl Cluster Stud Earrings – Vintage Bold Pearl Statement Studs for Women', 1150, 1299, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-110.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Butterfly Paperclip Chain Drop Earrings – Long Oval Link Dangle Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-111.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Heart Petal Flower Crystal Tassel Earrings – Arch Chain Fringe Drop Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-112.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Art Deco Chevron Fringe Earrings – Long Chain Tassel Statement Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-113.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Asymmetric Open Heart Hoop Earrings – Chunky Polished Heart Hoops for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-114.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Puffy Heart Stud Earrings – Beaded Border Chunky Heart Studs for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-115.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Chunky Teardrop Stud Earrings – Bold Puffy Water Drop Statement Studs for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-96.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Antique Golden Pearl Engraved Hoop Drop Earrings – Vintage Filigree Pearl Statement Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-97.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Tapered Half Hoop Earrings – Sleek C-Shape Claw Hoop Studs for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-98.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Twisted Rope Heart Hoop Earrings – Braided Love Heart Hoops for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-99.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Swirl Twisted Hoop Earrings – Chunky Spiral Ribbed Hoops for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-100.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Silver Emerald Green Leaf Wreath Stud Earrings – Zircon Laurel Statement Studs for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-101.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Black Flower Zircon Cluster Stud Earrings – Sparkling Floral Statement Studs for Women', 1160, 1299, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-102.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Baroque Pearl Drop Earrings – Hammered Rectangle Pearl Statement Earrings for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-94.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Ribbed Croissant Hoop Earrings – Chunky Textured Mini Hoops for Women', 1020, 1199, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-95.png?fit=1312%2C1199&quality=92&ssl=1'],
  ['Golden Chunky Crescent Hoop Earrings – Bold Puffy Moon Hoops for Women', 1160, 1299, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/146da8aa-image.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Horseshoe Tassel Drop Earrings – Long Chain Fringe Statement Earrings for Women', 1680, 1849, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-93.png?fit=1254%2C1254&quality=92&ssl=1'],
  ['Golden Layered Triangle Drop Earrings – Sculptural Open Statement Studs for Women', 1160, 1299, 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/e818b545-image.png?fit=1254%2C1254&quality=92&ssl=1'],
];

const slugify = value => String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
const normalizeName = value => String(value).toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
const normalizeImage = value => { try { const url = new URL(value); url.search = ''; url.hash = ''; return url.toString().toLowerCase(); } catch { return String(value).toLowerCase(); } };

function assertLocal(uri) {
  const target = new URL(uri);
  if (!['127.0.0.1', 'localhost', '::1'].includes(target.hostname) || target.pathname !== '/fastxhorizon_local') throw new Error('Refusing non-local database target.');
  return target;
}

async function main() {
  const target = assertLocal(LOCAL_URI);
  console.log(`Verified local database target: ${target.hostname}/${target.pathname.slice(1)}`);
  await mongoose.connect(LOCAL_URI, { serverSelectionTimeoutMS: 5000 });
  const beforeCount = await Product.countDocuments();
  const parent = await Category.findOne({ slug: 'jewellery', parentSlug: null }) || await Category.create({ name: 'Jewellery', slug: 'jewellery', parentSlug: null, active: true });
  const child = await Category.findOne({ slug: 'earrings', parentSlug: parent.slug }) || await Category.create({ name: 'Earrings', slug: 'earrings', parentSlug: parent.slug, active: true });

  const existing = await Product.find({ supplierName: SUPPLIER }).select('+supplierImageSources name sourceHandle slug sku supplierKey image images').lean();
  const names = new Set(existing.flatMap(item => [item.name, item.sourceHandle]).filter(Boolean).map(normalizeName));
  const keys = new Set(existing.map(item => item.supplierKey).filter(Boolean));
  const images = new Set(existing.flatMap(item => [item.image, ...(item.images || []), ...((item.supplierImageSources || []).map(source => source.url))]).filter(value => /^https?:\/\//i.test(value || '')).map(normalizeImage));
  const skus = await Product.find({ sku: /^FXH-FXH-AT-/ }).select('sku').lean();
  let skuNumber = skus.reduce((max, item) => Math.max(max, Number(String(item.sku).match(/(\d{4})$/)?.[1] || 0)), 0);
  const usedSlugs = new Set((await Product.find({ slug: { $exists: true, $ne: '' } }).select('slug').lean()).map(item => item.slug));
  const report = { sourceProducts: records.length, beforeCount, inserted: [], duplicates: [], failures: [], category: { parent: parent.name, subcategory: child.name, action: parent.isNew || child.isNew ? 'created_or_reused' : 'reused' } };
  const docs = [];

  for (const [name, sourcePrice, sellingPrice, image] of records) {
    const parsed = new URL(image);
    const supplierKey = `${SUPPLIER}:${slugify(name)}`;
    if (parsed.protocol !== 'https:' || !ALLOWED_HOSTS.has(parsed.hostname)) {
      report.failures.push({ name, reason: 'Image host or protocol is not approved.' });
      continue;
    }
    if (names.has(normalizeName(name)) || keys.has(supplierKey) || images.has(normalizeImage(image))) {
      report.duplicates.push({ name, reason: 'Existing title, supplier key or normalized source image match.' });
      continue;
    }
    try {
      const media = await ingestSupplierImages([image], { supplier: 'artomall' });
      skuNumber += 1;
      const sku = `FXH-FXH-AT-${String(skuNumber).padStart(4, '0')}`;
      let slug = slugify(name); let suffix = 2;
      while (usedSlugs.has(slug)) slug = `${slugify(name)}-${suffix++}`;
      usedSlugs.add(slug);
      const description = `${name}.`;
      docs.push({ name, description, shortDescription: description, longDescription: description, price: sellingPrice, currency: 'PKR', category: 'Jewellery', subcategory: 'Earrings', categoryPath: ['Jewellery', 'Earrings'], productType: 'PHYSICAL', tags: ['jewellery', 'earrings', 'women', 'artificial jewellery'], slug, sku, storeName: 'FASTXHORIZON', brand: '', image: media[0].mediaPath, images: media.map(item => item.mediaPath), stock: 0, inventoryMode: 'SUPPLIER_BASED', availabilityStatus: 'AVAILABLE_TO_ORDER', supplierKey, supplierName: SUPPLIER, supplierSKU: '', sourceFormat: 'ARTO_MALL_V25_LOCAL_SEED', sourcePublished: '0', sourceInStock: false, sourcePrice, sellingPrice, draftSellingPrice: sellingPrice, confirmedSupplierCost: null, pricingStatus: 'REVIEW_REQUIRED', supplierImageSources: media, status: 'draft', featured: false, rating: 0, numReviews: 0, createdAt: new Date(), updatedAt: new Date() });
      names.add(normalizeName(name)); keys.add(supplierKey); images.add(normalizeImage(image));
    } catch (error) {
      report.failures.push({ name, reason: error.message });
    }
  }
  if (docs.length) await Product.insertMany(docs, { ordered: true });
  report.inserted = docs.map(doc => ({ name: doc.name, sku: doc.sku, sourcePrice: doc.sourcePrice, sellingPrice: doc.sellingPrice, category: doc.category, subcategory: doc.subcategory, status: doc.status, image: doc.image }));
  report.afterCount = await Product.countDocuments();
  report.totalDuplicatesSkipped = report.duplicates.length;
  report.totalValidationFailures = report.failures.length;
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => { console.error(error.stack || error.message); await mongoose.disconnect().catch(() => {}); process.exitCode = 1; });
