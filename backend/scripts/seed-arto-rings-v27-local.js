/* Add the supplied V27 ARTO Mall rings to the verified local database only. */
require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const Category = require('../models/category');
const { ingestSupplierImages } = require('../services/supplierImageIngestion');

const LOCAL_URI = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const SUPPLIER = 'ARTO Mall';
const SOURCE_FORMAT = 'ARTO_MALL_V27_LOCAL_SEED';
const ALLOWED_HOSTS = new Set(['artomall.com', 'i0.wp.com']);
const records = [
  ['Royal Floral Sparkle Ring', 'A stunning artificial jewellery ring featuring two sparkling floral clusters with an elegant open-band design. Intricate crystal-style detailing adds brilliance, while the silver-tone finish gives the ring a graceful appearance.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/PHOTO-2026-09-16-23-01-08.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Double Heart Crystal Ring', 'A romantic artificial jewellery ring featuring two heart-shaped motifs, one polished and one embellished with sparkling crystal-style stones. An elegant design for everyday styling and gifting.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/b6f32d4c-d363-473e-8264-aaf1fc2588e4.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Golden Wave Ring', 'A beautifully designed artificial jewellery ring featuring graceful layered waves in a golden-tone finish. Delicate rows of sparkling stones add an elegant touch.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/4a4bf429-aa00-40ff-8c28-85713d555925.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Pink & Crystal Blossom Ring', 'An elegant artificial jewellery ring combining a sparkling pink stone with a clear crystal accent. Its sleek silver-tone open-band design creates a modern, feminine look.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/1345aff4-0d56-40e4-841d-dd9e3fd7bbe9.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Pink Crystal Dual-Stone Ring', 'A delicate artificial jewellery ring featuring two pink stones in different shapes, arranged on a sleek silver-tone open band for an elegant everyday style.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/d5898142-b2ab-4fda-9750-96e7c5ed118f.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Dual-Tone Gemstone Open Ring', 'A charming artificial jewellery ring featuring two contrasting gemstone-style accents in emerald green and golden yellow. The open-band design gives it a modern appearance.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/18fe344d-dbdb-4a8f-828f-48d60ff0226d.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Emerald & Sunshine Duo Ring', 'An elegant artificial jewellery ring pairing an emerald-green stone with a sparkling golden-yellow stone in a stylish open-band arrangement.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/9b070843-73fe-4932-86cd-9a9f7edb5947.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Crystal Glow Duo Ring', 'A graceful artificial jewellery ring featuring a sparkling crystal-white stone and a radiant yellow gemstone-style accent in a modern open design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/3a197e8f-3358-48b4-b0eb-4b27319c7cf2.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Crystal & Emerald Elegance Ring', 'A beautiful artificial jewellery ring featuring a clear crystal-style stone paired with a rich emerald-green stone, creating an elegant contrasting design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/88da2e15-efe0-4b5d-a8c9-cc6b315d5b92.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Open-Cuff Crystal Ring', 'A modern artificial jewellery ring featuring a sleek open-cuff design with sparkling crystal-style details for an elegant statement look.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/bb8e6abe-4d52-442e-b062-fee3f24ce2f3.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Butterfly Crystal Ring', 'A butterfly-inspired artificial jewellery ring featuring two sparkling crystal-style stones and a graceful golden-tone finish.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/5e557523-a3e2-464e-bf63-dc4403033f0c.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Bow Crystal Ring', 'A charming bow-design artificial jewellery ring featuring a golden-tone finish and sparkling crystal-style accents. A delicate and feminine accessory.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/9e503af2-bf82-421b-b225-4fec6b8c52b3.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Floral Crystal Ring – Gold', 'A floral-inspired artificial jewellery ring featuring a golden-tone finish and delicate sparkling crystal-style details.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/b9cf22c1-ba22-422b-81a0-a5fceee78503.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Double-Band Crystal Ring', 'A stylish artificial jewellery ring featuring a golden-tone double-band open design and sparkling crystal-style accents.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/92dded74-43d7-4ab1-b34d-0d8bb761d730.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Ocean Glow Gemstone Ring', 'A stylish silver-tone artificial jewellery ring featuring aqua-green gemstone-style accents and a fresh, contemporary design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/a1e2071e-1c03-49a6-a8c3-6981ae346e2f.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Blossom Duo Crystal Ring', 'A floral-inspired artificial jewellery ring combining delicate green and pink crystal-style stones in a graceful design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/351ef04f-382e-48ee-a254-c5d84d5df098.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Dual-Tone Sparkle Ring', 'An elegant artificial jewellery ring featuring crystal-clear and soft pink stones in a modern open-band design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/99a32cbb-e636-4f2a-a697-32c6a9b41834.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Pink & Aqua Elegance Ring', 'A charming artificial jewellery ring featuring aqua-blue and soft pink gemstone-style accents in a graceful contemporary design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/5275a944-a753-4489-8cbc-1e8b75412016.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Royal Blue Crystal Butterfly Ring', 'A butterfly-inspired artificial jewellery ring combining sparkling crystal-clear and deep-blue stones in a decorative design.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/6fcef5e9-0aae-4f7e-b8f9-7fb2e4322c4f.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Infinity Bow Crystal Ring', 'An infinity-inspired bow-design artificial jewellery ring featuring sparkling stone detailing and a refined silver-tone finish.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/4dce9ed6-8e78-422a-a0c6-6a987eb26d57.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Modern Double Bar Crystal Ring', 'A contemporary artificial jewellery ring featuring a double-bar open design decorated with sparkling crystal-style accents and a sleek silver-tone finish.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/13c934d9-b6a4-43bc-8380-857e290b5bff.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Floral Double-Band Crystal Ring', 'An elegant artificial jewellery ring featuring a sophisticated double-band design with a delicate floral centrepiece and sparkling stone accents.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/watermarked_img_10575151835711732899.jpg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Interlocking Crystal Ring', 'A stylish silver-tone artificial jewellery ring featuring an interlocking criss-cross design with delicate rows of sparkling stones.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/b344ff0f-d89d-43ec-b406-acc7efc14860.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
  ['Elegant Heart Crystal Statement Ring', 'A romantic artificial jewellery ring featuring a heart-shaped open centre surrounded by sparkling stones and finished in an elegant silver tone.', 'https://i0.wp.com/artomall.com/wp-content/uploads/2026/08/ed5ae9e7-505b-4d0b-b968-37000ba7772d.jpeg?fit=1024%2C1024&quality=92&ssl=1'],
].map(([name, description, image]) => ({ name, description, image, sourcePrice: 720, sellingPrice: 849 }));

const slugify = value => String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
const normalizeName = value => String(value).toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
const normalizeImage = value => { try { const url = new URL(value); url.search = ''; url.hash = ''; return url.toString().toLowerCase(); } catch { return String(value).toLowerCase(); } };
function assertLocal(uri) { const target = new URL(uri); if (!['127.0.0.1', 'localhost', '::1'].includes(target.hostname) || target.pathname !== '/fastxhorizon_local') throw new Error('Refusing non-local database target.'); return target; }

async function main() {
  const target = assertLocal(LOCAL_URI);
  if (records.length !== 24) throw new Error(`Expected 24 V27 records, found ${records.length}.`);
  await mongoose.connect(LOCAL_URI, { serverSelectionTimeoutMS: 5000 });
  console.log(`Verified local database target: ${target.hostname}/${target.pathname.slice(1)}`);
  const beforeCount = await Product.countDocuments();
  const parent = await Category.findOne({ slug: 'jewellery', parentSlug: null });
  if (!parent) throw new Error('Jewellery parent category is missing.');
  const child = await Category.findOne({ slug: 'rings', parentSlug: parent.slug });
  if (!child) throw new Error('Jewellery/Rings subcategory is missing.');
  const existing = await Product.find({ supplierName: SUPPLIER }).select('+supplierImageSources name sourceHandle slug sku supplierKey image images').lean();
  const names = new Set(existing.flatMap(item => [item.name, item.sourceHandle]).filter(Boolean).map(normalizeName));
  const keys = new Set(existing.map(item => item.supplierKey).filter(Boolean));
  const images = new Set(existing.flatMap(item => [item.image, ...(item.images || []), ...((item.supplierImageSources || []).map(source => source.url))]).filter(value => /^https?:\/\//i.test(value || '')).map(normalizeImage));
  const skus = await Product.find({ sku: /^FXH-FXH-AT-/ }).select('sku').lean();
  let skuNumber = skus.reduce((max, item) => Math.max(max, Number(String(item.sku).match(/(\d{4})$/)?.[1] || 0)), 0);
  const usedSlugs = new Set((await Product.find({ slug: { $exists: true, $ne: '' } }).select('slug').lean()).map(item => item.slug));
  const report = { target: `${target.hostname}/${target.pathname.slice(1)}`, sourceProducts: records.length, beforeCount, inserted: [], duplicates: [], failures: [] };
  const docs = [];
  for (const item of records) {
    const supplierKey = `${SUPPLIER}:${slugify(item.name)}`;
    try {
      const parsed = new URL(item.image);
      if (parsed.protocol !== 'https:' || !ALLOWED_HOSTS.has(parsed.hostname)) throw new Error('Image host or protocol is not approved.');
      if (names.has(normalizeName(item.name)) || keys.has(supplierKey) || images.has(normalizeImage(item.image))) { report.duplicates.push({ name: item.name, reason: 'Existing title, supplier key or normalized source image match.' }); continue; }
      const media = await ingestSupplierImages([item.image], { supplier: 'artomall' });
      if (!media.length || !media[0].mediaPath) throw new Error('Image ingestion did not return internal media.');
      skuNumber += 1;
      const sku = `FXH-FXH-AT-${String(skuNumber).padStart(4, '0')}`;
      let slug = slugify(item.name); let suffix = 2;
      while (usedSlugs.has(slug)) slug = `${slugify(item.name)}-${suffix++}`;
      usedSlugs.add(slug);
      docs.push({ name: item.name, description: item.description, shortDescription: item.description.slice(0, 300), longDescription: item.description, price: item.sellingPrice, currency: 'PKR', category: 'Jewellery', subcategory: 'Rings', categoryPath: ['Jewellery', 'Rings'], productType: 'PHYSICAL', tags: ['jewellery', 'rings', 'women', 'artificial jewellery'], slug, sku, storeName: 'FASTXHORIZON', brand: '', image: media[0].mediaPath, images: media.map(entry => entry.mediaPath), stock: 0, inventoryMode: 'SUPPLIER_BASED', availabilityStatus: 'AVAILABLE_TO_ORDER', supplierKey, supplierName: SUPPLIER, supplierSKU: '', sourceFormat: SOURCE_FORMAT, sourcePublished: '0', sourceInStock: false, sourcePrice: item.sourcePrice, sellingPrice: item.sellingPrice, draftSellingPrice: item.sellingPrice, confirmedSupplierCost: null, pricingStatus: 'REVIEW_REQUIRED', supplierImageSources: media, status: 'draft', featured: false, rating: 0, numReviews: 0, createdAt: new Date(), updatedAt: new Date() });
      names.add(normalizeName(item.name)); keys.add(supplierKey); images.add(normalizeImage(item.image));
    } catch (error) { report.failures.push({ name: item.name, reason: error.message }); }
  }
  if (docs.length) await Product.insertMany(docs, { ordered: true });
  report.inserted = docs.map(doc => ({ name: doc.name, sku: doc.sku, sourcePrice: doc.sourcePrice, sellingPrice: doc.sellingPrice, category: doc.category, subcategory: doc.subcategory, status: doc.status, image: doc.image }));
  report.afterCount = await Product.countDocuments(); report.totalDuplicatesSkipped = report.duplicates.length; report.totalValidationFailures = report.failures.length;
  console.log(JSON.stringify(report, null, 2));
  await mongoose.disconnect();
}
main().catch(async error => { console.error(error.stack || error.message); await mongoose.disconnect().catch(() => {}); process.exitCode = 1; });
