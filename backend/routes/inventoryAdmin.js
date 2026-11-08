const express = require('express');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const Product = require('../models/product');
const OfflineSale = require('../models/offlineSale');
const InventoryTransaction = require('../models/inventoryTransaction');
const ArrangementRequest = require('../models/arrangementRequest');
const { calculateArrangementAmounts } = require('../utils/arrangementFinance');
const { requireAdminSession, requireAdminPermission } = require('../middleware/adminAuth');

const router = express.Router();
router.use(requireAdminSession);
const evidenceRoot = path.resolve(process.env.PRIVATE_EVIDENCE_STORAGE_ROOT || path.join(process.cwd(), 'data', 'private-payment-evidence'));
const eventId = value => String(value || '').trim() || `offline-${crypto.randomUUID()}`;
const saleNumber = () => `FXH-OFF-${Date.now()}-${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`;
const requestView = item => ({ id: String(item._id), requestNumber: item.requestNumber, product: item.productId, quantity: item.quantity, customer: item.customer, status: item.status, agreedUnitPrice: item.agreedUnitPrice, subtotal: item.subtotal, advanceAmount: item.advanceAmount, remainingAmount: item.remainingAmount, deliveryCharge: item.deliveryCharge, estimatedWindow: item.estimatedWindow, payment: item.payment, statusHistory: item.statusHistory, createdAt: item.createdAt });

router.get('/products', async (req, res) => {
  const search = String(req.query.search || '').trim();
  const filter = search ? { $or: [{ name: { $regex: search, $options: 'i' } }, { sku: { $regex: search, $options: 'i' } }, { brand: { $regex: search, $options: 'i' } }] } : {};
  const products = await Product.find(filter).select('name sku brand stock inventoryMode availabilityStatus price confirmedSupplierCost variants').sort({ updatedAt: -1 }).limit(50).lean();
  return res.json({ data: products });
});

router.get('/summary', requireAdminPermission('manage_inventory'), async (req, res) => {
  const [numeric, physical, lowStock, offlineSales] = await Promise.all([
    Product.countDocuments({ inventoryMode: 'NUMERIC', status: { $ne: 'archived' } }),
    Product.countDocuments({ inventoryMode: 'NUMERIC', stock: { $gt: 0 }, status: { $ne: 'archived' } }),
    Product.countDocuments({ inventoryMode: 'NUMERIC', stock: { $gt: 0, $lte: 2 }, status: { $ne: 'archived' } }),
    OfflineSale.countDocuments({ channel: 'OFFLINE' }),
  ]);
  return res.json({ data: { totalPhysicalProducts: numeric, inStockProducts: physical, soldOutProducts: numeric - physical, lowStockProducts: lowStock, offlineSales } });
});

router.patch('/products/:productId/stock', requireAdminPermission('manage_inventory'), async (req, res) => {
  const { variantId, reason, action } = req.body || {};
  const delta = Number(req.body.quantity);
  if ((action !== 'SOLD_OUT' && (!Number.isInteger(delta) || delta === 0)) || !String(reason || '').trim()) return res.status(400).json({ message: 'A reason and valid adjustment quantity are required.' });
  if (!['ADJUST', 'SOLD_OUT', 'TOGGLE_ARRANGE'].includes(action)) return res.status(400).json({ message: 'Invalid stock action.' });
  const product = await Product.findOne({ _id: req.params.productId, status: { $ne: 'archived' } });
  if (!product) return res.status(404).json({ message: 'Product not found.' });
  if (action === 'TOGGLE_ARRANGE') { product.allowArrangeOnRequest = req.body.enabled === true; await product.save(); return res.json({ data: { allowArrangeOnRequest: product.allowArrangeOnRequest } }); }
  const isVariant = variantId && product.variants.id(variantId);
  if (variantId && !isVariant) return res.status(404).json({ message: 'Variant not found.' });
  if (isVariant && isVariant.inventoryMode !== 'NUMERIC') return res.status(409).json({ message: 'Only numeric variants support physical stock controls.' });
  if (action === 'SOLD_OUT' && !isVariant && product.inventoryMode !== 'NUMERIC') return res.status(409).json({ message: 'Supplier-based products must use their supplier availability state.' });
  const before = isVariant ? Number(isVariant.stock || 0) : Number(product.stock || 0);
  const next = action === 'SOLD_OUT' ? 0 : before + delta;
  if (next < 0) return res.status(409).json({ message: 'Stock cannot become negative.' });
  if (!isVariant && product.inventoryMode !== 'NUMERIC') return res.status(409).json({ message: 'Only numeric inventory supports physical stock adjustments.' });
  if (isVariant) { isVariant.stock = next; isVariant.availabilityStatus = next > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK'; } else { product.stock = next; product.availabilityStatus = next > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK'; }
  await product.save();
  await InventoryTransaction.create({ eventId: `adjust-${crypto.randomUUID()}`, productId: product._id, variantId: isVariant?._id || null, type: 'ADJUSTMENT', quantity: next - before, stockBefore: before, stockAfter: next, referenceId: String(product._id), reason: String(reason).trim().slice(0, 500), actorId: req.admin._id });
  return res.json({ data: { stock: product.stock, variantId: isVariant?._id || null, variantStock: isVariant?.stock, availabilityStatus: isVariant?.availabilityStatus || product.availabilityStatus } });
});

router.post('/offline-sales', requireAdminPermission('manage_inventory'), async (req, res) => {
  const { productId, variantId, quantity, sellingPrice, discount, saleDate, channel, customer, notes, eventId: suppliedEventId } = req.body || {};
  const qty = Number(quantity);
  if (!productId || !Number.isInteger(qty) || qty < 1 || !Number.isFinite(Number(sellingPrice)) || Number(sellingPrice) < 0) return res.status(400).json({ message: 'Product, whole quantity and selling price are required.' });
  const inventoryEventId = eventId(suppliedEventId);
  const existing = await OfflineSale.findOne({ inventoryEventId }).lean();
  if (existing) return res.json({ data: { id: String(existing._id), idempotent: true, saleNumber: existing.saleNumber } });
  const updated = variantId
    ? await Product.findOneAndUpdate({ _id: productId, status: { $ne: 'archived' }, variants: { $elemMatch: { _id: variantId, inventoryMode: 'NUMERIC', stock: { $gte: qty } } } }, { $inc: { 'variants.$.stock': -qty }, $set: { 'variants.$.availabilityStatus': 'IN_STOCK', updatedAt: new Date() } }, { new: true }).lean()
    : await Product.findOneAndUpdate({ _id: productId, inventoryMode: 'NUMERIC', stock: { $gte: qty }, status: { $ne: 'archived' } }, { $inc: { stock: -qty }, $set: { availabilityStatus: 'IN_STOCK', updatedAt: new Date() } }, { new: true }).lean();
  if (!updated) return res.status(409).json({ message: 'Insufficient numeric stock or product is not eligible for an offline stock sale.' });
  const updatedVariant = variantId ? updated.variants.find(item => String(item._id) === String(variantId)) : null;
  const stockAfter = updatedVariant ? Number(updatedVariant.stock) : Number(updated.stock);
  const stockBefore = stockAfter + qty;
  if (stockAfter === 0) {
    if (updatedVariant) await Product.updateOne({ _id: updated._id, 'variants._id': variantId }, { $set: { 'variants.$.availabilityStatus': 'OUT_OF_STOCK' } });
    else await Product.updateOne({ _id: updated._id }, { $set: { availabilityStatus: 'OUT_OF_STOCK' } });
  }
  let transaction;
  try {
    transaction = await InventoryTransaction.create({ eventId: inventoryEventId, productId, variantId: variantId || null, type: 'OFFLINE_SALE', quantity: -qty, stockBefore, stockAfter, referenceId: inventoryEventId, reason: 'Offline sale recorded by administrator', metadata: { sellingPrice: Number(sellingPrice), discount: Number(discount) || 0, channel: channel || 'OFFLINE' }, actorId: req.admin._id });
    const sale = await OfflineSale.create({ saleNumber: saleNumber(), productId, variantId: variantId || null, quantity: qty, sellingPrice: Number(sellingPrice), discount: Number(discount) || 0, saleDate: saleDate || new Date(), channel: ['OFFLINE', 'WEBSITE', 'OTHER'].includes(channel) ? channel : 'OFFLINE', customer: customer || {}, notes: String(notes || '').slice(0, 1000), recordedBy: req.admin._id, inventoryEventId: transaction.eventId });
    return res.status(201).json({ data: { id: String(sale._id), saleNumber: sale.saleNumber, inventory: { stock: stockAfter, availabilityStatus: stockAfter > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK' } } });
  } catch (error) {
    await InventoryTransaction.deleteOne({ _id: transaction?._id }).catch(() => {});
    const restore = variantId ? { $inc: { 'variants.$.stock': qty }, $set: { 'variants.$.availabilityStatus': 'IN_STOCK', updatedAt: new Date() } } : { $inc: { stock: qty }, $set: { availabilityStatus: 'IN_STOCK', updatedAt: new Date() } };
    await Product.updateOne(variantId ? { _id: updated._id, 'variants._id': variantId } : { _id: updated._id }, restore).catch(() => {});
    if (error?.code === 11000) return res.json({ data: { idempotent: true, eventId: inventoryEventId } });
    return res.status(500).json({ message: 'Offline sale could not be recorded; inventory was restored.' });
  }
});

router.get('/products/:productId/history', requireAdminPermission('manage_inventory'), async (req, res) => {
  const items = await InventoryTransaction.find({ productId: req.params.productId }).sort({ createdAt: -1 }).limit(100).lean();
  return res.json({ data: items });
});

router.get('/arrangements', async (req, res) => {
  const filter = req.query.status ? { status: String(req.query.status) } : {};
  const items = await ArrangementRequest.find(filter).populate('productId', 'name sku image price brand fabricMaterial stock inventoryMode').sort({ createdAt: -1 }).limit(100).lean();
  return res.json({ data: items.map(requestView) });
});

router.get('/arrangements/:id/evidence/:fileId', requireAdminPermission('manage_inventory'), async (req, res) => {
  const request = await ArrangementRequest.findById(req.params.id).select('+payment.evidenceFiles').lean();
  const file = request?.payment?.evidenceFiles?.find(item => item.fileId === req.params.fileId);
  if (!file) return res.status(404).json({ message: 'Evidence not found.' });
  const target = path.resolve(evidenceRoot, file.storageName);
  if (!target.startsWith(`${evidenceRoot}${path.sep}`)) return res.status(400).json({ message: 'Invalid evidence path.' });
  try { await fs.promises.access(target, fs.constants.R_OK); } catch { return res.status(404).json({ message: 'Evidence file is unavailable.' }); }
  res.setHeader('Content-Type', file.mimeType);
  res.setHeader('Content-Disposition', 'attachment; filename="payment-evidence"');
  return res.sendFile(target);
});

router.patch('/arrangements/:id', requireAdminPermission('manage_inventory'), async (req, res) => {
  const request = await ArrangementRequest.findById(req.params.id);
  if (!request) return res.status(404).json({ message: 'Arrangement request not found.' });
  const nextStatus = String(req.body.status || '');
  if (!ArrangementRequest.STATUSES.includes(nextStatus)) return res.status(400).json({ message: 'Invalid arrangement status.' });
  const allowed = { PENDING_REVIEW: ['AVAILABLE_CONFIRMED', 'UNAVAILABLE', 'CANCELLED'], AVAILABLE_CONFIRMED: ['ADVANCE_PENDING', 'UNAVAILABLE', 'CANCELLED'], ADVANCE_PENDING: ['ADVANCE_SUBMITTED', 'CANCELLED'], ADVANCE_SUBMITTED: ['ADVANCE_VERIFIED', 'CANCELLED'], ADVANCE_VERIFIED: ['SOURCING', 'CANCELLED'], SOURCING: ['READY_TO_DISPATCH', 'UNAVAILABLE', 'CANCELLED'], READY_TO_DISPATCH: ['SHIPPED'], SHIPPED: ['DELIVERED'], REFUND_PENDING: ['REFUNDED'] };
  if (!(allowed[request.status] || []).includes(nextStatus)) return res.status(409).json({ message: `Cannot move ${request.status} to ${nextStatus}.` });
  if (nextStatus === 'ADVANCE_VERIFIED' && req.body.paymentVerified !== true) return res.status(400).json({ message: 'Explicit payment verification is required.' });
  if (['AVAILABLE_CONFIRMED', 'ADVANCE_PENDING'].includes(nextStatus)) {
    const unitPrice = Number(req.body.agreedUnitPrice);
    if (!Number.isFinite(unitPrice) || unitPrice <= 0) return res.status(400).json({ message: 'Confirmed selling price is required.' });
    const amounts = calculateArrangementAmounts(unitPrice, request.quantity);
    request.agreedUnitPrice = Math.round(unitPrice * 100) / 100;
    request.subtotal = amounts.subtotal;
    request.advanceAmount = amounts.advanceAmount;
    request.remainingAmount = amounts.remainingAmount;
    request.estimatedWindow = String(req.body.estimatedWindow || '').slice(0, 160);
  }
  request.status = nextStatus;
  if (nextStatus === 'ADVANCE_VERIFIED') {
    if (request.payment?.state !== 'PENDING_VERIFICATION') return res.status(409).json({ message: 'No pending advance evidence is available.' });
    request.payment.state = 'VERIFIED';
    request.payment.verifiedAt = new Date();
    request.payment.verifiedBy = req.admin._id;
  }
  request.adminNotes = String(req.body.adminNotes || request.adminNotes || '').slice(0, 1000);
  request.statusHistory.push({ status: nextStatus, note: String(req.body.note || '').slice(0, 500), changedBy: req.admin._id });
  await request.save();
  return res.json({ data: requestView(request.toObject()) });
});

module.exports = router;
