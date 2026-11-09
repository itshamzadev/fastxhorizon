const express = require('express');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const multer = require('multer');
const Product = require('../models/product');
const StockManagerIdentity = require('../models/stockManagerIdentity');
const { requireStockManagerDevice } = require('../middleware/stockManagerAuth');

const router = express.Router();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: Number(process.env.STOCK_MANAGER_MEDIA_MAX_BYTES || 5242880), files: 10 } });
const text = (value, max = 5000) => String(value ?? '').trim().slice(0, max);
const publicImage = value => /^https?:\/\//i.test(String(value || '')) ? String(value) : '';
const mediaRoot = () => { const root = process.env.MEDIA_STORAGE_ROOT || ''; if (!root || !path.isAbsolute(root)) throw new Error('MEDIA_STORAGE_ROOT is not configured as an absolute persistent path.'); return root; };

router.use(requireStockManagerDevice);

// Read-only catalog feed used by the desktop Import Existing Website Products
// workflow.  It deliberately returns public catalog data and operational stock
// only; supplier costs and other private accounting fields never leave the API.
router.get('/products', async (req, res) => {
  const page = Math.max(1, Number.parseInt(String(req.query.page || '1'), 10) || 1);
  const limit = Math.min(100, Math.max(1, Number.parseInt(String(req.query.limit || '50'), 10) || 50));
  const query = {};
  const [total, products] = await Promise.all([
    Product.countDocuments(query),
    Product.find(query).sort({ createdAt: 1, _id: 1 }).skip((page - 1) * limit).limit(limit).lean(),
  ]);
  const data = products.map(product => ({
    remoteProductId: String(product._id),
    remoteVersion: Number(product.__v || 0),
    localProductId: String(product.supplierKey || product.sourceHandle || ''),
    sku: product.sku || '',
    name: product.name,
    description: product.description || product.longDescription || '',
    shortDescription: product.shortDescription || '',
    category: product.category || '',
    subcategory: product.subcategory || '',
    brandName: product.brand || '',
    fabricMaterial: product.fabricMaterial || '',
    clothingType: product.clothingType || '',
    status: product.status || 'draft',
    inventoryMode: product.inventoryMode || 'NUMERIC',
    stock: Number(product.stock || 0),
    availabilityStatus: product.availabilityStatus || 'OUT_OF_STOCK',
    images: Array.isArray(product.images) ? product.images : (product.image ? [product.image] : []),
    variants: (Array.isArray(product.variants) ? product.variants : []).map(variant => ({
      remoteVariantId: String(variant._id),
      id: String(variant._id),
      sku: variant.sku || '',
      color: variant.options?.color || '',
      size: variant.options?.size || '',
      quantity: Number(variant.stock || 0),
      reservedQuantity: 0,
      damagedQuantity: 0,
      sellingPrice: Number(variant.price || 0),
      compareAtPrice: variant.compareAtPrice == null ? null : Number(variant.compareAtPrice),
      images: Array.isArray(variant.images) ? variant.images : [],
      inventoryMode: variant.inventoryMode || product.inventoryMode || 'NUMERIC',
      availabilityStatus: variant.availabilityStatus || 'OUT_OF_STOCK',
      allowArrangeOnRequest: Boolean(variant.allowArrangeOnRequest),
      enabled: variant.enabled !== false,
    })),
  }));
  return res.json({ data: { products: data, total, page, limit, totalPages: Math.ceil(total / limit), hasMore: page * limit < total } });
});

function validateProduct(input) {
  if (!input || !text(input.localProductId, 200) || !Number.isInteger(Number(input.localRevision)) || Number(input.localRevision) < 1 || !text(input.sku, 120) || !text(input.name, 300)) return 'localProductId, localRevision, sku and name are required.';
  if (!Array.isArray(input.variants) || input.variants.length < 1 || input.variants.length > 500) return 'At least one variant is required.';
  for (const variant of input.variants) if (!text(variant.id, 200) || !text(variant.sku, 120) || Number(variant.sellingPrice) < 0 || Number(variant.quantity) < 0) return 'Each variant requires identity, SKU and non-negative values.';
  return null;
}

router.post('/preview', async (req, res) => {
  const products = Array.isArray(req.body?.products) ? req.body.products.slice(0, 500) : [];
  const changes = [];
  for (const item of products) {
    const error = validateProduct(item);
    if (error) changes.push({ localProductId: item?.localProductId || null, action: 'INVALID', error });
    else {
      const existing = await Product.findOne({ supplierKey: `FXH_STOCK_MANAGER:${item.localProductId}` }).select('_id __v status name sku').lean();
      changes.push({ localProductId: item.localProductId, action: existing ? (existing.__v === Number(item.remoteVersion ?? existing.__v) ? 'UPDATE' : 'CONFLICT') : 'CREATE', remoteProductId: existing?._id || null, remoteVersion: existing?.__v ?? null, name: item.name, sku: item.sku, status: item.status });
    }
  }
  return res.json({ data: { changes, counts: { total: changes.length, create: changes.filter(x => x.action === 'CREATE').length, update: changes.filter(x => x.action === 'UPDATE').length, conflict: changes.filter(x => x.action === 'CONFLICT').length, invalid: changes.filter(x => x.action === 'INVALID').length } } });
});

router.post('/upsert', async (req, res) => {
  const input = req.body?.product;
  const error = validateProduct(input);
  if (error) return res.status(400).json({ message: error });
  const supplierKey = `FXH_STOCK_MANAGER:${input.localProductId}`;
  let product = await Product.findOne({ supplierKey });
  // Imported records already have a Mongo identity. Reuse it on later local
  // publications instead of creating a second supplier-key record.
  if (!product && input.remoteProductId) {
    product = await Product.findById(String(input.remoteProductId));
    if (product && product.sku && String(product.sku).toUpperCase() !== String(input.sku).toUpperCase()) return res.status(409).json({ message: 'Remote product identity and SKU do not match.' });
  }
  if (product && input.remoteVersion !== undefined && Number(product.__v) !== Number(input.remoteVersion)) return res.status(409).json({ message: 'Catalog revision conflict.', data: { remoteProductId: String(product._id), remoteVersion: product.__v, name: product.name, sku: product.sku } });
  const incomingImages = Array.isArray(input.images) ? input.images.map(publicImage).filter(Boolean) : [];
  if (!product) {
    product = new Product({ name: text(input.name, 300), description: text(input.description), category: text(input.category, 120) || 'Uncategorized', brand: text(input.brandName, 120), fabricMaterial: text(input.fabricMaterial, 120), clothingType: text(input.clothingType, 120), price: Number(input.variants[0].sellingPrice), compareAtPrice: Number(input.variants[0].compareAtPrice) || null, sku: text(input.sku, 120), supplierKey, sourceHandle: text(input.localProductId, 200), sourceFormat: 'FXH_STOCK_MANAGER_CATALOG', status: input.status === 'archived' ? 'archived' : input.status === 'active' ? 'active' : 'draft', inventoryMode: 'NUMERIC', images: incomingImages });
  } else {
    if (!product.supplierKey) product.supplierKey = supplierKey;
    product.name = text(input.name, 300); product.description = text(input.description); product.category = text(input.category, 120) || product.category; product.brand = text(input.brandName, 120); product.fabricMaterial = text(input.fabricMaterial, 120); product.clothingType = text(input.clothingType, 120); product.images = incomingImages.length ? incomingImages : product.images; if (['active', 'draft', 'archived'].includes(input.status)) product.status = input.status;
  }
  const byLocal = new Map();
  for (const variant of product.variants) { const mapping = await StockManagerIdentity.findOne({ remoteEntityType: 'variant', remoteEntityId: String(variant._id) }).lean(); if (mapping) byLocal.set(mapping.localEntityId, variant); }
  for (const incoming of input.variants) {
    let target = byLocal.get(String(incoming.id));
    if (!target) target = product.variants.find(item => item.sku === text(incoming.sku, 120));
    const images = Array.isArray(incoming.imageUrls) ? incoming.imageUrls.map(publicImage).filter(Boolean) : [];
    if (!target) { target = { key: `${incoming.color || ''}-${incoming.size || ''}`.replace(/^-|-$/g, '') || incoming.sku, options: { color: text(incoming.color, 80), size: text(incoming.size, 80) }, sku: text(incoming.sku, 120), price: Number(incoming.sellingPrice), compareAtPrice: Number(incoming.compareAtPrice) || null, images, inventoryMode: 'NUMERIC', stock: Number(incoming.quantity) || 0, availabilityStatus: Number(incoming.quantity) > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK', enabled: true }; product.variants.push(target); }
    else { target.options = { color: text(incoming.color, 80), size: text(incoming.size, 80) }; target.price = Number(incoming.sellingPrice); target.compareAtPrice = Number(incoming.compareAtPrice) || null; if (images.length) target.images = images; }
  }
  product.variantsEnabled = true;
  if (product.isNew) product.stock = product.variants.reduce((sum, variant) => sum + Number(variant.stock || 0), 0);
  await product.save();
  await StockManagerIdentity.findOneAndUpdate({ localEntityType: 'product', localEntityId: input.localProductId }, { $set: { remoteEntityType: 'product', remoteEntityId: String(product._id), state: 'ACTIVE' } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  for (const incoming of input.variants) { const target = product.variants.find(item => item.sku === text(incoming.sku, 120)); if (target) await StockManagerIdentity.findOneAndUpdate({ localEntityType: 'variant', localEntityId: String(incoming.id) }, { $set: { remoteEntityType: 'variant', remoteEntityId: String(target._id), state: 'ACTIVE' } }, { upsert: true, new: true, setDefaultsOnInsert: true }); }
  return res.json({ data: { localProductId: input.localProductId, remoteProductId: String(product._id), remoteVersion: product.__v, status: product.status, stock: product.stock } });
});

router.post('/archive', async (req, res) => {
  const localProductId = text(req.body?.localProductId, 200);
  if (!localProductId) return res.status(400).json({ message: 'localProductId is required.' });
  const product = await Product.findOne({ supplierKey: `FXH_STOCK_MANAGER:${localProductId}` });
  if (!product) return res.status(404).json({ message: 'Published product mapping was not found.' });
  if (req.body.remoteVersion !== undefined && Number(product.__v) !== Number(req.body.remoteVersion)) return res.status(409).json({ message: 'Catalog revision conflict.', data: { remoteProductId: String(product._id), remoteVersion: product.__v } });
  product.status = 'archived'; await product.save();
  return res.json({ data: { localProductId, remoteProductId: String(product._id), remoteVersion: product.__v, status: product.status } });
});

router.post('/media', upload.array('files', 10), async (req, res) => {
  const files = req.files || []; if (!files.length) return res.status(400).json({ message: 'At least one image file is required.' });
  const root = mediaRoot(); const targetDir = path.join(root, 'products'); await fs.promises.mkdir(targetDir, { recursive: true }); const accepted = [];
  for (const file of files) { const b = file.buffer; const isPng = b.subarray(0, 8).equals(Buffer.from([137,80,78,71,13,10,26,10])); const isJpeg = b.subarray(0, 3).equals(Buffer.from([255,216,255])); const isWebp = b.subarray(0, 4).toString('ascii') === 'RIFF' && b.subarray(8, 12).toString('ascii') === 'WEBP'; if (!(isPng || isJpeg || isWebp)) return res.status(415).json({ message: `Unsupported image content for ${file.originalname}.` }); const hash = crypto.createHash('sha256').update(b).digest('hex'); const ext = isPng ? '.png' : isJpeg ? '.jpg' : '.webp'; const filename = `${hash}${ext}`; const target = path.join(targetDir, filename); if (!fs.existsSync(target)) await fs.promises.writeFile(target, b, { flag: 'wx' }); accepted.push({ filename, sha256: hash, url: `/media/products/${filename}` }); }
  return res.json({ data: { files: accepted } });
});

module.exports = router;
