const express = require('express');
const crypto = require('crypto');
const Product = require('../models/product');
const InventoryTransaction = require('../models/inventoryTransaction');
const StockManagerDevice = require('../models/stockManagerDevice');
const StockSyncEvent = require('../models/stockSyncEvent');
const StockManagerIdentity = require('../models/stockManagerIdentity');
const { digest, requireStockManagerDevice } = require('../middleware/stockManagerAuth');

const router = express.Router();
const safeText = (value, max = 200) => String(value || '').trim().slice(0, max);

router.post('/devices/register', async (req, res) => {
  const suppliedSecret = String(req.get('x-fxh-registration-secret') || req.body?.registrationSecret || '');
  const configuredSecret = String(process.env.STOCK_MANAGER_REGISTRATION_SECRET || '');
  if (!configuredSecret || !suppliedSecret || suppliedSecret !== configuredSecret) return res.status(401).json({ message: 'Device registration is not authorized.' });
  const deviceId = safeText(req.body?.deviceId, 120);
  const deviceName = safeText(req.body?.deviceName, 120);
  if (!deviceId || !deviceName) return res.status(400).json({ message: 'deviceId and deviceName are required.' });
  const token = crypto.randomBytes(32).toString('base64url');
  const device = await StockManagerDevice.findOneAndUpdate({ deviceId }, { $set: { deviceName, tokenHash: digest(token), status: 'active' } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  return res.status(201).json({ data: { deviceId: device.deviceId, deviceName: device.deviceName, token, warning: 'Store this token securely. It is returned only during registration.' } });
});

router.use(requireStockManagerDevice);

function validateEvent(event) {
  if (!event || !safeText(event.eventId, 160) || !safeText(event.idempotencyKey || event.eventId, 160) || !safeText(event.eventType, 80) || !safeText(event.entityType, 80) || !safeText(event.entityId, 200)) return 'eventId, eventType, entityType and entityId are required.';
  if (JSON.stringify(event.payload || {}).length > 200000) return 'Event payload is too large.';
  return null;
}

async function applyStockEvent(event, device) {
  const payload = event.payload || {};
  const localProductId = safeText(payload.localProductId || payload.productId, 200);
  if (event.eventType === 'PRODUCT_CREATE') {
    if (!localProductId) throw new Error('Product create event has no local product identity.');
    const supplierKey = `FXH_STOCK_MANAGER:${localProductId}`;
    const existing = await Product.findOne({ supplierKey }).lean();
    if (existing) return { applied: false, idempotent: true, productId: String(existing._id) };
    const sourceVariants = Array.isArray(payload.variants) ? payload.variants : [];
    const imageSources = sourceVariants.flatMap(item => Array.isArray(item.image_paths) ? item.image_paths.filter(Boolean).map(mediaPath => ({ mediaPath: String(mediaPath) })) : []);
    const product = await Product.create({ name: safeText(payload.name, 300) || 'Stock Manager product', description: safeText(payload.description, 5000), category: safeText(payload.category, 120) || 'Uncategorized', brand: safeText(payload.brandName, 120), fabricMaterial: safeText(payload.fabricMaterial, 120), clothingType: safeText(payload.clothingType, 120), images: Array.isArray(payload.imageUrls) ? payload.imageUrls.filter(value => /^https?:\/\//i.test(String(value))) : [], supplierImageSources: imageSources, price: Number(sourceVariants[0]?.selling_price || 0), compareAtPrice: Number(sourceVariants[0]?.original_price || 0) || null, sku: `FXH-SM-${crypto.createHash('sha1').update(localProductId).digest('hex').slice(0, 12).toUpperCase()}`, supplierKey, supplierName: safeText(payload.brandName, 120), sourceHandle: localProductId, sourceFormat: 'FXH_STOCK_MANAGER_SYNC', status: 'draft', inventoryMode: 'NUMERIC', stock: sourceVariants.reduce((sum, item) => sum + Math.max(0, Number(item.quantity_on_hand) || 0), 0), availabilityStatus: sourceVariants.some(item => Number(item.quantity_on_hand) > 0) ? 'IN_STOCK' : 'OUT_OF_STOCK', variants: sourceVariants.map((item, index) => ({ key: String(item.color || item.sku || item.id || `variant-${index + 1}`), options: { color: item.color || '', size: item.size || '' }, sku: `FXH-SM-V-${crypto.createHash('sha1').update(String(item.id || item.sku || `${localProductId}:${index}`)).digest('hex').slice(0, 12).toUpperCase()}`, images: Array.isArray(item.imageUrls) ? item.imageUrls.filter(value => /^https?:\/\//i.test(String(value))) : [], price: Math.max(0, Number(item.selling_price) || 0), compareAtPrice: Math.max(0, Number(item.original_price) || 0) || null, stock: Math.max(0, Number(item.quantity_on_hand) || 0), availabilityStatus: Number(item.quantity_on_hand) > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK', inventoryMode: 'NUMERIC', enabled: true })) });
    await StockManagerIdentity.create({ localEntityType: 'product', localEntityId: localProductId, remoteEntityType: 'product', remoteEntityId: String(product._id) });
    for (let index = 0; index < sourceVariants.length; index += 1) if (sourceVariants[index].id && product.variants[index]) await StockManagerIdentity.create({ localEntityType: 'variant', localEntityId: String(sourceVariants[index].id), remoteEntityType: 'variant', remoteEntityId: String(product.variants[index]._id) });
    return { applied: true, productId: String(product._id) };
  }
  if (event.eventType === 'PRODUCT_UPDATE') {
    if (!localProductId) throw new Error('Product update event has no local product identity.');
    const mapping = await StockManagerIdentity.findOne({ localEntityType: 'product', localEntityId: localProductId }).lean();
    const product = mapping ? await Product.findById(mapping.remoteEntityId) : await Product.findOne({ supplierKey: `FXH_STOCK_MANAGER:${localProductId}` });
    if (!product) throw new Error('Remote product mapping was not found.');
    product.name = safeText(payload.name, 300) || product.name;
    product.brand = safeText(payload.brandName, 120);
    product.category = safeText(payload.category, 120) || product.category;
    product.subcategory = safeText(payload.subcategory, 120);
    if (payload.description !== undefined) product.description = safeText(payload.description, 5000) || product.description;
    product.clothingType = safeText(payload.clothingType, 120);
    product.fabricMaterial = safeText(payload.fabricMaterial, 120);
    if (Array.isArray(payload.variants)) {
      for (const incoming of payload.variants) {
        const variantMapping = await StockManagerIdentity.findOne({ localEntityType: 'variant', localEntityId: safeText(incoming.id, 200), state: 'ACTIVE' }).lean();
        const target = variantMapping ? product.variants.id(variantMapping.remoteEntityId) : product.variants.find(item => item.sku === safeText(incoming.sku, 120));
        if (target) { target.options = { ...(target.options || {}), color: incoming.color || '', size: incoming.size || '' }; target.price = Math.max(0, Number(incoming.selling_price) || 0); target.compareAtPrice = Math.max(0, Number(incoming.original_price) || 0) || null; target.images = Array.isArray(incoming.image_paths) ? incoming.image_paths.filter(Boolean) : target.images; }
      }
    }
    product.updatedAt = new Date(); await product.save();
    return { applied: true, productId: String(product._id) };
  }
  if (event.eventType === 'PRODUCT_ARCHIVED') {
    if (!localProductId) throw new Error('Product archive event has no local product identity.');
    const mapping = await StockManagerIdentity.findOne({ localEntityType: 'product', localEntityId: localProductId }).lean();
    const product = mapping ? await Product.findByIdAndUpdate(mapping.remoteEntityId, { $set: { status: 'archived', updatedAt: new Date() } }, { new: true }) : await Product.findOneAndUpdate({ supplierKey: `FXH_STOCK_MANAGER:${localProductId}` }, { $set: { status: 'archived', updatedAt: new Date() } }, { new: true });
    if (!product) throw new Error('Remote product mapping was not found.');
    return { applied: true, productId: String(product._id) };
  }
  let remoteProductId = safeText(payload.remoteProductId || payload.productId, 100);
  let remoteVariantId = safeText(payload.remoteVariantId || payload.variantId, 100);
  if (!remoteVariantId && payload.localVariantId) {
    const mapping = await StockManagerIdentity.findOne({ localEntityType: 'variant', localEntityId: safeText(payload.localVariantId, 200), state: 'ACTIVE' }).lean();
    if (mapping) remoteVariantId = String(mapping.remoteEntityId);
  }
  if (!remoteProductId && payload.localProductId) {
    const mapping = await StockManagerIdentity.findOne({ localEntityType: 'product', localEntityId: safeText(payload.localProductId, 200), state: 'ACTIVE' }).lean();
    if (mapping) remoteProductId = String(mapping.remoteEntityId);
  }
  if (remoteVariantId && !remoteProductId) {
    const product = await Product.findOne({ 'variants._id': remoteVariantId }).select('_id').lean();
    if (product) remoteProductId = String(product._id);
  }
  const quantity = Number(payload.quantity);
  if (!remoteProductId || !Number.isInteger(quantity) || quantity === 0) throw new Error('A remote product identity and non-zero integer quantity are required.');
  if (!['OFFLINE_SALE', 'ONLINE_SALE', 'STOCK_RECEIPT', 'STOCK_ADJUSTMENT'].includes(event.eventType)) return { applied: false };
  const delta = ['OFFLINE_SALE', 'ONLINE_SALE'].includes(event.eventType) ? -Math.abs(quantity) : quantity;
  let updated;
  if (remoteVariantId) {
    const query = { _id: remoteProductId, status: { $ne: 'archived' }, variants: { $elemMatch: { _id: remoteVariantId, inventoryMode: 'NUMERIC', stock: { $gte: delta < 0 ? Math.abs(delta) : 0 } } } };
    const update = { $inc: { 'variants.$.stock': delta }, $set: { updatedAt: new Date() } };
    updated = await Product.findOneAndUpdate(query, update, { new: true }).lean();
    if (updated) {
      const variant = updated.variants.find(item => String(item._id) === remoteVariantId);
      if (variant) await Product.updateOne({ _id: updated._id, 'variants._id': remoteVariantId }, { $set: { 'variants.$.availabilityStatus': Number(variant.stock) > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK' } });
    }
  } else {
    const query = { _id: remoteProductId, inventoryMode: 'NUMERIC', status: { $ne: 'archived' }, ...(delta < 0 ? { stock: { $gte: Math.abs(delta) } } : {}) };
    updated = await Product.findOneAndUpdate(query, { $inc: { stock: delta }, $set: { updatedAt: new Date() } }, { new: true }).lean();
    if (updated) await Product.updateOne({ _id: updated._id }, { $set: { availabilityStatus: Number(updated.stock) > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK' } });
  }
  if (!updated) throw new Error('Product or variant was not found, is archived, or lacks sufficient numeric stock.');
  const variant = remoteVariantId ? updated.variants.find(item => String(item._id) === remoteVariantId) : null;
  const stockAfter = Number(variant ? variant.stock : updated.stock);
  const stockBefore = stockAfter - delta;
  await InventoryTransaction.create({ eventId: event.eventId, productId: updated._id, variantId: remoteVariantId || null, type: event.eventType === 'OFFLINE_SALE' ? 'OFFLINE_SALE' : 'ADJUSTMENT', quantity: delta, stockBefore, stockAfter, referenceId: event.entityId, reason: safeText(payload.reason || 'Stock Manager synchronization', 500), metadata: { deviceId: device.deviceId, sourceEventType: event.eventType, localEntityId: event.entityId } });
  return { applied: true, productId: String(updated._id), variantId: remoteVariantId || null, stockAfter };
}

router.post('/sync/push', async (req, res) => {
  const events = Array.isArray(req.body?.events) ? req.body.events : [];
  if (!events.length || events.length > 100) return res.status(400).json({ message: 'Provide between 1 and 100 events.' });
  const results = [];
  for (const raw of events) {
    const error = validateEvent(raw);
    if (error) { results.push({ eventId: raw?.eventId || null, status: 'FAILED', error }); continue; }
    const event = { ...raw, eventId: safeText(raw.eventId, 160), idempotencyKey: safeText(raw.idempotencyKey || raw.eventId, 160), eventType: safeText(raw.eventType, 80), entityType: safeText(raw.entityType, 80), entityId: safeText(raw.entityId, 200) };
    const existing = await StockSyncEvent.findOne({ $or: [{ eventId: event.eventId }, { idempotencyKey: event.idempotencyKey }] }).lean();
    if (existing) { results.push({ eventId: event.eventId, status: existing.status, idempotent: true, error: existing.error || undefined }); continue; }
    const record = await StockSyncEvent.create({ ...event, source: 'DESKTOP', sourceDeviceId: req.stockManagerDevice.deviceId, status: 'PENDING' });
    try {
      const applied = await applyStockEvent(event, req.stockManagerDevice);
      record.status = 'APPLIED';
      record.payload = { ...event.payload, result: applied };
      await record.save();
      results.push({ eventId: event.eventId, status: 'APPLIED', result: applied });
    } catch (applyError) {
      record.status = 'FAILED';
      record.error = safeText(applyError.message, 500);
      await record.save();
      results.push({ eventId: event.eventId, status: 'FAILED', error: record.error });
    }
  }
  return res.json({ data: { results } });
});

router.get('/sync/pull', async (req, res) => {
  const limit = Math.min(Math.max(Number(req.query.limit) || 100, 1), 100);
  const events = await StockSyncEvent.find({ sourceDeviceId: { $ne: req.stockManagerDevice.deviceId }, acknowledgedBy: { $ne: req.stockManagerDevice.deviceId }, status: 'APPLIED' }).sort({ createdAt: 1, _id: 1 }).limit(limit).lean();
  return res.json({ data: { events, hasMore: events.length === limit } });
});

router.post('/sync/ack', async (req, res) => {
  const eventIds = Array.isArray(req.body?.eventIds) ? req.body.eventIds.map(item => safeText(item, 160)).filter(Boolean).slice(0, 200) : [];
  if (!eventIds.length) return res.status(400).json({ message: 'eventIds are required.' });
  await StockSyncEvent.updateMany({ eventId: { $in: eventIds } }, { $addToSet: { acknowledgedBy: req.stockManagerDevice.deviceId } });
  return res.json({ data: { acknowledged: eventIds.length } });
});

router.get('/reconciliation', async (req, res) => {
  const [failedEvents, conflicts] = await Promise.all([StockSyncEvent.find({ sourceDeviceId: req.stockManagerDevice.deviceId, status: 'FAILED' }).sort({ createdAt: -1 }).limit(100).lean(), StockManagerIdentity.find({ state: 'CONFLICT' }).sort({ updatedAt: -1 }).limit(100).lean()]);
  return res.json({ data: { failedEvents, conflicts } });
});

router.post('/reconciliation/resolve', async (req, res) => {
  const eventId = safeText(req.body?.eventId, 160);
  const action = safeText(req.body?.action, 20).toUpperCase();
  if (!eventId || !['RETRY', 'DISMISS'].includes(action)) return res.status(400).json({ message: 'eventId and action RETRY or DISMISS are required.' });
  const event = await StockSyncEvent.findOne({ eventId, sourceDeviceId: req.stockManagerDevice.deviceId });
  if (!event) return res.status(404).json({ message: 'Synchronization event was not found for this device.' });
  if (action === 'RETRY') { event.status = 'PENDING'; event.error = ''; event.acknowledgedBy = []; await event.save(); }
  else { event.status = 'DISMISSED'; event.acknowledgedBy = [req.stockManagerDevice.deviceId]; await event.save(); }
  return res.json({ data: { eventId, action, status: event.status } });
});

router.post('/identity', async (req, res) => {
  const body = req.body || {};
  if (!['product', 'variant'].includes(body.localEntityType) || !['product', 'variant'].includes(body.remoteEntityType) || !safeText(body.localEntityId) || !safeText(body.remoteEntityId)) return res.status(400).json({ message: 'Valid local and remote entity identities are required.' });
  const mapping = await StockManagerIdentity.findOneAndUpdate({ localEntityType: body.localEntityType, localEntityId: safeText(body.localEntityId) }, { $set: { remoteEntityType: body.remoteEntityType, remoteEntityId: safeText(body.remoteEntityId), state: 'ACTIVE' } }, { upsert: true, new: true, setDefaultsOnInsert: true });
  return res.json({ data: mapping });
});

module.exports = router;
