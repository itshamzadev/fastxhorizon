/*
 * Seed the reviewed ARTO Mall jewellery starter catalog into local MongoDB.
 * This script is intentionally loopback-only and idempotent.
 */
require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');

const LOCAL_URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const ALLOWED_HOSTS = new Set(['127.0.0.1', 'localhost', '::1']);
const SUPPLIER = 'ARTO Mall';
const SUPPLIER_CODE = 'AT';

const products = [
  ['Golden Baroque Pearl Ring - Adjustable Open Cuff Textured Gold Ring for Women with Free Gift Box', 1110, 'Rings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-171.png?fit=1254%2C1254&quality=92&ssl=1', ['ring', 'pearl', 'gold', 'women', 'artificial jewellery'], 'Elegant, modern and effortlessly chic, this golden ring features a lustrous baroque-style pearl in a textured gold-tone setting. Adjustable open cuff design with a free gift box.'],
  ['Mint Green Kundan Pearl Jewellery Set – Choker, Chandbali Earrings & Maang Tikka', 2540, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-173.png?fit=1215%2C1295&quality=92&ssl=1', ['jewellery set', 'kundan', 'pearl', 'choker', 'bridal'], 'Mint green kundan pearl jewellery set featuring a choker, chandbali earrings and maang tikka.'],
  ['Emerald Green Stone Jewellery Set - Gold Necklace, Drop Earrings & Maang Tikka for Women', 2410, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-174.png?fit=1312%2C1199&quality=92&ssl=1', ['jewellery set', 'emerald green', 'necklace', 'earrings'], 'Emerald green stone jewellery set with a gold necklace, drop earrings and maang tikka.'],
  ['Pink Stone Silver Zircon Bridal Set – Necklace, Earrings & Maang Tikka', 3190, 'Bridal Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-175.png?fit=1312%2C1199&quality=92&ssl=1', ['bridal', 'zircon', 'pink stone', 'necklace', 'earrings'], 'Pink stone silver zircon bridal set with necklace, earrings and maang tikka.'],
  ['Silver Crystal Choker Jewellery Set – Chandelier Earrings & Maang Tikka', 2580, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-177.png?fit=1254%2C1254&quality=92&ssl=1', ['jewellery set', 'crystal', 'choker', 'chandelier earrings'], 'Silver crystal choker jewellery set with chandelier earrings and maang tikka.'],
  ['White Crystal Floral Bridal Set – Silver Necklace, Teardrop Earrings & Maang Tikka', 3190, 'Bridal Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-176.png?fit=1312%2C1199&quality=92&ssl=1', ['bridal', 'white crystal', 'floral', 'necklace', 'earrings'], 'White crystal floral bridal set with a silver necklace, teardrop earrings and maang tikka.'],
  ['Silver Floral Pendant Crystal Bead Mala Necklace Set with Matching Earrings for Women', 2580, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-178.png?fit=1254%2C1254&quality=92&ssl=1', ['jewellery set', 'mala', 'necklace', 'earrings', 'crystal'], 'Silver floral pendant crystal bead mala necklace set with matching earrings.'],
  ['Pink Crystal Bridal Set – Silver Choker, Jhumka Earrings, Maang Tikka & Jhoomar', 9690, 'Bridal Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-170.png?fit=1254%2C1254&quality=92&ssl=1', ['bridal', 'pink crystal', 'choker', 'jhumka', 'jhoomar'], 'Pink crystal bridal set with silver choker, jhumka earrings, maang tikka and jhoomar.'],
  ['Silver Crystal Bridal Set – Choker Necklace, Jhumka Earrings & Maang Tikka', 7670, 'Bridal Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-169.png?fit=1254%2C1254&quality=92&ssl=1', ['bridal', 'silver crystal', 'choker', 'jhumka'], 'Silver crystal bridal set with choker necklace, jhumka earrings and maang tikka.'],
  ['Pink Kundan Bridal Set – Silver Choker, Jhumka Earrings, Maang Tikka & Jhoomar', 9880, 'Bridal Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-167.png?fit=1289%2C1221&quality=92&ssl=1', ['bridal', 'pink kundan', 'choker', 'jhumka', 'jhoomar'], 'Pink kundan bridal set with silver choker, jhumka earrings, maang tikka and jhoomar.'],
  ['Golden Love Knot Snake Chain Bracelet – Double Strand Infinity Knot Bracelet for Women', 1210, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-166.png?fit=1024%2C1024&quality=92&ssl=1', ['bracelet', 'love knot', 'gold', 'women'], 'Golden love knot snake chain bracelet with a double-strand infinity knot design.'],
  ['Golden Flower Coin Charm Bracelet – Vintage Embossed Floral Medallion Bead Chain Bracelet for Women', 1340, 'Bracelets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-164.png?fit=768%2C768&quality=92&ssl=1', ['bracelet', 'charm', 'floral', 'gold', 'women'], 'Golden flower coin charm bracelet with vintage embossed floral medallion bead chain detailing.'],
  ['Golden Puffy Heart Pendant Necklace – Minimal Polished Heart Charm Chain for Women', 1340, 'Necklaces', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-160.png?fit=1168%2C1036&quality=92&ssl=1', ['necklace', 'heart', 'pendant', 'gold', 'women'], 'Golden puffy heart pendant necklace with a minimal polished heart charm chain.'],
  ['Golden Rose Medallion Necklace & Bracelet Set – Vintage Oval Rose Pendant and Charm Bracelet for Women', 1340, 'Necklace & Bracelet Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-157.png?fit=1100%2C1100&quality=92&ssl=1', ['jewellery set', 'necklace', 'bracelet', 'rose', 'gold'], 'Golden rose medallion necklace and bracelet set with a vintage oval rose pendant and charm bracelet.'],
  ['Golden Vintage Oval Pearl Stud Earrings – Bold Cushion Frame Pearl Statement Studs for Women', 1080, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-156.png?fit=480%2C639&quality=92&ssl=1', ['earrings', 'pearl', 'studs', 'gold', 'women'], 'Golden vintage oval pearl stud earrings with bold cushion-frame pearl statement styling.'],
  ['Silver Emerald Green Bow Stud Earrings – Round Green Zircon Ribbon Bow Studs for Women', 1210, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-155.png?fit=1086%2C1033&quality=92&ssl=1', ['earrings', 'emerald green', 'zircon', 'bow', 'studs'], 'Silver emerald green bow stud earrings with round green zircon ribbon-bow detailing.'],
  ['Golden Green Four Leaf Clover Jewellery Set – Necklace, Bracelet, Earrings & Ring for Women', 1860, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-154.png?fit=554%2C554&quality=92&ssl=1', ['jewellery set', 'clover', 'necklace', 'bracelet', 'ring'], 'Golden green four leaf clover jewellery set including necklace, bracelet, earrings and ring.'],
  ['Golden Filigree Twist Bangle – Openwork Diagonal Pattern Kara Bangle for Women', 1390, 'Bangles', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-144.png?fit=800%2C800&quality=92&ssl=1', ['bangle', 'filigree', 'twist', 'gold', 'women'], 'Golden filigree twist bangle with an openwork diagonal pattern kara design.'],
  ['Golden Phoenix Bird Pendant & Earrings Set – Zircon Wing Necklace with Hoop Drop Earrings for Women', 1340, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-145.png?fit=1280%2C1280&quality=92&ssl=1', ['jewellery set', 'phoenix', 'necklace', 'earrings', 'zircon'], 'Golden phoenix bird pendant and earrings set with zircon wing necklace and hoop drop earrings.'],
  ['Golden Textured Monstera Leaf Drop Earrings – Crescent Stud Tropical Leaf Statement Earrings for Women', 1680, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-146.png?fit=1200%2C1200&quality=92&ssl=1', ['earrings', 'monstera', 'leaf', 'gold', 'statement'], 'Golden textured monstera leaf drop earrings with crescent stud tropical-leaf styling.'],
  ['Golden Double Circle Pearl Stud Earrings – Modern Orbit Ring Pearl Studs for Women', 1080, 'Earrings', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-148.png?fit=1254%2C1254&quality=92&ssl=1', ['earrings', 'pearl', 'studs', 'orbit', 'gold'], 'Golden double circle pearl stud earrings with modern orbit-ring pearl styling.'],
  ['Golden Ruby Red Rose Pendant & Earrings Set – Red Zircon Rose Necklace with Drop Earrings for Women', 1210, 'Jewellery Sets', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/10/image-149.png?fit=1000%2C1000&quality=92&ssl=1', ['jewellery set', 'rose', 'ruby red', 'necklace', 'earrings'], 'Golden ruby red rose pendant and earrings set with red zircon rose necklace and drop earrings.'],
];

const subcategories = ['Rings', 'Jewellery Sets', 'Bridal Sets', 'Bracelets', 'Necklaces', 'Earrings', 'Bangles', 'Necklace & Bracelet Sets'];

function slugify(value) {
  return String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
}

function sellingPrice(sourcePrice) {
  const examples = { 1080: 1299, 1110: 1299, 1210: 1399, 1340: 1499, 1390: 1599, 1680: 1899, 1860: 1999, 2410: 2599, 2540: 2699, 2580: 2799, 3190: 3399, 7670: 9999, 9690: 11999, 9880: 11999 };
  if (examples[sourcePrice]) return examples[sourcePrice];
  if (sourcePrice > 7000) return Math.ceil((sourcePrice + 2000) / 1000) * 1000 - 1;
  return Math.ceil((sourcePrice + 100) / 100) * 100 + 99;
}

function assertLocalTarget(uri) {
  const parsed = new URL(uri);
  if (!ALLOWED_HOSTS.has(parsed.hostname) || parsed.pathname !== '/fastxhorizon_local') {
    throw new Error(`Refusing non-local database target: ${parsed.hostname}/${parsed.pathname}`);
  }
}

async function nextSku(used) {
  let number = 1;
  while (used.has(`FXH-FXH-${SUPPLIER_CODE}-${String(number).padStart(4, '0')}`)) number += 1;
  const sku = `FXH-FXH-${SUPPLIER_CODE}-${String(number).padStart(4, '0')}`;
  used.add(sku);
  return sku;
}

async function main() {
  assertLocalTarget(LOCAL_URI);
  console.log(`Verified local database target: ${new URL(LOCAL_URI).hostname}/${new URL(LOCAL_URI).pathname.slice(1)}`);
  await mongoose.connect(LOCAL_URI, { serverSelectionTimeoutMS: 5000 });

  const categoryDocs = {};
  const existingCategoryNames = [];
  for (const [name, parentSlug] of [['Jewellery', null], ...subcategories.map(name => [name, 'jewellery'])]) {
    const slug = slugify(name);
    const existing = await Category.findOne({ slug, parentSlug });
    const doc = existing || await Category.create({ name, slug, parentSlug, active: true });
    categoryDocs[name] = doc;
    existingCategoryNames.push({ name, slug, parentSlug, action: existing ? 'reused' : 'created' });
  }

  const existingProducts = await Product.find({ $or: [
    { supplierKey: { $regex: '^ARTO Mall:' } },
    { sku: { $regex: '^FXH-FXH-AT-' } },
    { name: { $in: products.map(item => item[0]) } },
  ] }).select('+supplierImageSources name sku supplierKey images image').lean();
  const usedSkus = new Set((await Product.find({ sku: /^FXH-FXH-AT-/ }).select('sku').lean()).map(item => item.sku));
  const existingNames = new Set(existingProducts.map(item => item.name.toLowerCase()));
  const existingKeys = new Set(existingProducts.map(item => item.supplierKey).filter(Boolean));
  const existingImages = new Set(existingProducts.flatMap(item => [item.image, ...(item.images || []), ...((item.supplierImageSources || []).map(source => source.url))]).filter(Boolean));
  const report = { sourceProducts: products.length, inserted: [], duplicates: [], failures: [], categories: existingCategoryNames };
  const docs = [];

  for (const [name, sourcePrice, subcategory, image, tags, description] of products) {
    const supplierKey = `${SUPPLIER}:${slugify(name)}`;
    const imageUrl = new URL(image);
    if (imageUrl.protocol !== 'https:' || !['artomall.com', 'i0.wp.com'].includes(imageUrl.hostname)) {
      report.failures.push({ name, reason: 'Unsupported source image host or protocol' });
      continue;
    }
    if (!name || !Number.isFinite(sourcePrice) || sourcePrice <= 0 || !image) {
      report.failures.push({ name, reason: 'Missing required name, source price or image' });
      continue;
    }
    if (existingNames.has(name.toLowerCase()) || existingKeys.has(supplierKey) || existingImages.has(image)) {
      report.duplicates.push({ name, supplierKey, reason: 'Existing name, supplier key or image match' });
      continue;
    }
    const sku = await nextSku(usedSkus);
    let slug = slugify(name);
    let suffix = 2;
    while (await Product.exists({ slug })) slug = `${slugify(name)}-${suffix++}`;
    const price = sellingPrice(sourcePrice);
    docs.push({
      name,
      description,
      shortDescription: description,
      longDescription: description,
      price,
      currency: 'PKR',
      category: 'Jewellery',
      subcategory,
      categoryPath: ['Jewellery', subcategory],
      productType: 'PHYSICAL',
      tags,
      slug,
      sku,
      storeName: 'FASTXHORIZON',
      brand: '',
      image,
      images: [image],
      stock: 0,
      inventoryMode: 'SUPPLIER_BASED',
      availabilityStatus: 'AVAILABLE_TO_ORDER',
      supplierKey,
      supplierName: SUPPLIER,
      supplierSKU: '',
      sourceFormat: 'ARTO_MALL_LOCAL_SEED',
      sourcePublished: '0',
      sourceInStock: false,
      sourceUrl: '',
      supplierImageSources: [{ url: image, mediaPath: image }],
      sourcePrice,
      confirmedSupplierCost: null,
      sellingPrice: price,
      draftSellingPrice: price,
      pricingStatus: 'REVIEW_REQUIRED',
      status: 'draft',
      featured: false,
      rating: 0,
      numReviews: 0,
      createdAt: new Date(),
      updatedAt: new Date(),
    });
    existingNames.add(name.toLowerCase());
    existingKeys.add(supplierKey);
    existingImages.add(image);
  }

  if (docs.length) await Product.insertMany(docs, { ordered: true });
  report.inserted = docs.map(doc => ({ name: doc.name, sku: doc.sku, sourcePrice: doc.sourcePrice, sellingPrice: doc.sellingPrice, category: doc.category, subcategory: doc.subcategory, status: doc.status }));
  report.totalDuplicatesSkipped = report.duplicates.length;
  report.totalValidationFailures = report.failures.length;
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => {
  console.error(error.stack || error.message);
  await mongoose.disconnect().catch(() => {});
  process.exitCode = 1;
});

