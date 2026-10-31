const express = require('express');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Order = require('../models/order');
const User = require('../models/user');
const AdminAuditLog = require('../models/adminAuditLog');
const { requireAdminSession, requireAdminPermission } = require('../middleware/adminAuth');
const { publicAdmin } = require('../services/adminSecurity');
const StorefrontSetting = require('../models/storefrontSetting');
const NewsletterSubscriber = require('../models/newsletterSubscriber');
const Refund = require('../models/refund');
const Category = require('../models/category');
const CatalogImport = require('../models/catalogImport');
const PaymentConfiguration = require('../models/paymentConfiguration');
const { SUPPLIER, safeFilename, buildEntries, makeProductDocument } = require('../services/catalogImport');
const { SETTING_KEY, DEFAULT_MESSAGE, publicAnnouncement } = require('./storefront');

const router = express.Router();
const MAX_PAGE_SIZE = 50;
const PRODUCT_STATUSES = new Set(['active', 'draft', 'archived']);
const ORDER_STATUSES = new Set(['pending', 'processing', 'shipped', 'delivered', 'cancelled']);

router.use(requireAdminSession);

function requireSuperAdmin(req, res, next) {
  if (req.admin?.role !== 'super_admin') return res.status(403).json({ code: 'admin_forbidden', message: 'Super Admin access is required to change online ordering.' });
  return next();
}

function pageQuery(req) {
  const page = Math.max(1, Number.parseInt(req.query.page, 10) || 1);
  const pageSize = Math.min(MAX_PAGE_SIZE, Math.max(1, Number.parseInt(req.query.pageSize, 10) || 10));
  return { page, pageSize, skip: (page - 1) * pageSize };
}

function sortQuery(value, allowed, fallback = { createdAt: -1 }) {
  const raw = String(value || '');
  const descending = raw.startsWith('-');
  const field = descending ? raw.slice(1) : raw;
  return allowed.has(field) ? { [field]: descending ? -1 : 1 } : fallback;
}

function validImageValue(value) {
  if (!value) return true;
  if (typeof value !== 'string' || value.length > 3000000) return false;
  return /^(https?:\/\/|data:image\/(png|jpeg|jpg|webp|gif);base64,)/i.test(value);
}

function validateVariants(attributes, variants) {
  if (attributes === undefined && variants === undefined) return null;
  if (!Array.isArray(attributes) || attributes.length > 10 || !Array.isArray(variants) || variants.length > 200) return 'Invalid variant configuration.';
  const keys = new Set(); const skus = new Set();
  for (const attribute of attributes) {
    if (!attribute || typeof attribute.name !== 'string' || !attribute.name.trim() || !Array.isArray(attribute.values) || attribute.values.length > 50) return 'Invalid variant attribute.';
  }
  for (const variant of variants) {
    if (!variant || typeof variant.key !== 'string' || !variant.key.trim() || keys.has(variant.key) || !Number.isFinite(Number(variant.price)) || Number(variant.price) < 0) return 'Invalid or duplicate variant combination.';
    if (variant.sku && (skus.has(String(variant.sku).toUpperCase()))) return 'Variant SKUs must be unique.';
    keys.add(variant.key); if (variant.sku) skus.add(String(variant.sku).toUpperCase());
  }
  return null;
}

function validateFlexibleFields(body) {
  const productType = body.productType === undefined ? null : String(body.productType);
  if (productType && !['PHYSICAL', 'DIGITAL'].includes(productType)) return 'Product type must be PHYSICAL or DIGITAL.';
  for (const field of ['specifications', 'attributes']) {
    if (body[field] !== undefined && (!Array.isArray(body[field]) || body[field].length > 100)) return `${field} must be an array with at most 100 entries.`;
  }
  if (Array.isArray(body.specifications)) {
    for (const item of body.specifications) {
      if (!item || typeof item.key !== 'string' || !item.key.trim() || typeof item.label !== 'string' || !item.label.trim() || typeof item.value !== 'string' || item.value.length > 500) return 'Each specification needs a key, label and value.';
    }
  }
  if (Array.isArray(body.attributes)) {
    const keys = new Set();
    for (const item of body.attributes) {
      const key = String(item?.key || '').trim().toLowerCase();
      if (!key || keys.has(key) || typeof item.label !== 'string' || item.label.trim().length === 0 || !Array.isArray(item.allowedValues) || item.allowedValues.length > 50) return 'Attributes must have unique keys, labels and at most 50 allowed values.';
      keys.add(key);
    }
  }
  if (body.digitalConfiguration !== undefined && body.digitalConfiguration !== null && typeof body.digitalConfiguration !== 'object') return 'Invalid digital configuration.';
  if (body.physicalConfiguration !== undefined && body.physicalConfiguration !== null && typeof body.physicalConfiguration !== 'object') return 'Invalid physical configuration.';
  if (body.seo !== undefined && body.seo !== null && typeof body.seo !== 'object') return 'Invalid SEO configuration.';
  if (productType === 'DIGITAL' && body.physicalConfiguration !== undefined && body.physicalConfiguration !== null) return 'Physical configuration is not valid for a digital product.';
  return null;
}

function publicFlexibleFields(product) {
  const specifications = Array.isArray(product.specifications) ? product.specifications.filter(item => item.public !== false).map(({ key, label, value, type, unit, order }) => ({ key, label, value, type, unit, order })) : [];
  const attributes = Array.isArray(product.attributes) ? product.attributes.filter(item => item.public !== false).map(({ key, label, type, required, allowedValues, defaultValue, unit, variant, order }) => ({ key, label, type, required, allowedValues, defaultValue, unit, variant, order })) : [];
  const physical = product.productType === 'DIGITAL' ? null : product.physicalConfiguration ? { weight: product.physicalConfiguration.weight, weightUnit: product.physicalConfiguration.weightUnit, dimensions: product.physicalConfiguration.dimensions, warranty: product.physicalConfiguration.warranty, condition: product.physicalConfiguration.condition, shippingInfo: product.physicalConfiguration.shippingInfo, ownershipMode: product.physicalConfiguration.ownershipMode } : null;
  const digital = product.productType === 'DIGITAL' && product.digitalConfiguration ? { deliveryType: product.digitalConfiguration.deliveryType, version: product.digitalConfiguration.version, releaseNotes: product.digitalConfiguration.releaseNotes, supportedPlatforms: product.digitalConfiguration.supportedPlatforms, systemRequirements: product.digitalConfiguration.systemRequirements, licenseType: product.digitalConfiguration.licenseType, downloadLimit: product.digitalConfiguration.downloadLimit, accessDurationDays: product.digitalConfiguration.accessDurationDays, updatePolicy: product.digitalConfiguration.updatePolicy, instructions: product.digitalConfiguration.instructions, documentationUrl: product.digitalConfiguration.documentationUrl } : null;
  return { productType: product.productType || 'PHYSICAL', specifications, attributes, physicalConfiguration: physical, digitalConfiguration: digital, seo: product.seo || null };
}

function productView(product) {
  const id = String(product._id);
  return { id, name: product.name, sku: product.sku || `FXH-${id.slice(-8).toUpperCase()}`, description: product.description || null, shortDescription: product.shortDescription || null, longDescription: product.longDescription || product.description || null, category: product.category, subcategory: product.subcategory || null, categoryPath: product.categoryPath || [], tags: product.tags || [], slug: product.slug || null, price: product.price, currency: product.currency || null, inventory: product.stock || 0, inventoryMode: product.inventoryMode === 'SUPPLIER_BASED' ? 'SUPPLIER_BASED' : (['AVAILABILITY_BASED', 'AVAILABILITY'].includes(product.inventoryMode) ? 'AVAILABILITY_BASED' : 'NUMERIC'), availabilityStatus: product.availabilityStatus || (product.stock > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK'), status: product.status || 'active', image: product.image || null, images: product.images || [], variantsEnabled: Boolean(product.variantsEnabled), variantAttributes: product.variantAttributes || [], variants: product.variants || [], brand: product.brand || null, storeName: product.storeName || 'FASTXHORIZON', ...publicFlexibleFields(product), supplierName: product.supplierName || null, supplierSKU: product.supplierSKU || null, sourcePrice: product.sourcePrice ?? null, sellingPrice: product.sellingPrice ?? null, draftSellingPrice: product.draftSellingPrice ?? null, pricingStatus: product.pricingStatus || null, featured: Boolean(product.featured), createdAt: product.createdAt, updatedAt: product.updatedAt || product.createdAt };
}

function pricingView(product) {
  return {
    id: String(product._id), name: product.name, image: product.image || null,
    sku: product.sku || product.supplierSKU || null, category: product.category,
    supplierName: product.supplierName || null, supplierSKU: product.supplierSKU || null,
    sourcePrice: product.sourcePrice ?? null, confirmedSupplierCost: product.confirmedSupplierCost ?? null,
    operationalCostEstimate: product.operationalCostEstimate ?? null, draftSellingPrice: product.draftSellingPrice ?? null,
    sellingPrice: product.sellingPrice ?? null, targetProfit: product.targetProfit ?? null,
    estimatedNetProfit: product.estimatedNetProfit ?? null, pricingStatus: product.pricingStatus || 'REVIEW_REQUIRED',
    status: product.status || 'draft', currency: product.currency || 'PKR', updatedAt: product.updatedAt || product.createdAt,
  };
}

function pricingNumber(value, name, { nullable = true } = {}) {
  if (value === null || value === undefined || value === '') return nullable ? null : NaN;
  const number = Number(value);
  return Number.isFinite(number) && number >= 0 ? number : NaN;
}

function orderStatus(order) {
  if (order.statusIndex < 0) return 'cancelled';
  const code = order.statusHistory?.[order.statusIndex || 0]?.code;
  if (code === 'DELIVERED' || code === 'DELIVERY_CONFIRMED') return 'delivered';
  if (code === 'IN_TRANSIT' || code === 'OUT_FOR_DELIVERY' || code === 'HANDOFF_TO_CARRIER') return 'shipped';
  if ((order.statusIndex || 0) > 0) return 'processing';
  return 'pending';
}

function orderView(order) {
  return { id: String(order._id), number: order.orderNumber, customerName: order.name, customerEmail: order.email, items: (order.items || []).map((item) => ({ productId: String(item.productId), name: item.name, quantity: item.quantity, unitPrice: item.price })), subtotal: order.total, shipping: 0, tax: 0, total: order.total, currency: order.currency || null, status: orderStatus(order), paymentStatus: order.paymentStatus || 'pending', paymentLifecycle: order.paymentLifecycle || 'PENDING_PAYMENT_VERIFICATION', availabilityStatus: order.availabilityStatus || 'CONFIRMED', paymentMethod: order.paymentMethod || 'cod', placedAt: order.createdAt, updatedAt: order.updatedAt, shippingAddress: order.shippingAddress, statusHistory: order.statusHistory || [] };
}

async function audit(req, action, success = true, metadata = {}) {
  try {
    await AdminAuditLog.create({ adminId: req.admin._id, email: req.admin.email, action, success, ip: req.ip, userAgent: req.get('user-agent'), metadata });
  } catch (error) { console.error('Admin audit write failed:', error.message); }
}

router.get('/me', (req, res) => res.json({ user: publicAdmin(req.admin) }));

router.get('/storefront/announcement', requireAdminPermission('settings.manage'), async (req, res, next) => {
  try {
    const setting = await StorefrontSetting.findOne({ key: SETTING_KEY }).lean();
    return res.json({ data: publicAnnouncement(setting) });
  } catch (error) { return next(error); }
});

router.get('/settings/store-operations', requireSuperAdmin, async (req, res, next) => {
  try {
    const setting = await PaymentConfiguration.findOneAndUpdate({ key: 'v1' }, { $setOnInsert: { key: 'v1', ordersEnabled: false, automaticEnabled: false, manualEnabled: false, codEnabled: false, manualMethods: [] } }, { upsert: true, new: true, setDefaultsOnInsert: true }).populate('ordersUpdatedBy', 'email name').lean();
    return res.json({ data: { onlineOrderingEnabled: setting.ordersEnabled === true, message: setting.orderingDisabledMessage || 'Online ordering is temporarily unavailable.', updatedAt: setting.ordersUpdatedAt || setting.updatedAt || null, updatedBy: setting.ordersUpdatedBy ? { id: String(setting.ordersUpdatedBy._id), email: setting.ordersUpdatedBy.email, name: setting.ordersUpdatedBy.name } : null, reason: setting.ordersUpdateReason || '' } });
  } catch (error) { return next(error); }
});

router.patch('/settings/store-operations', requireSuperAdmin, async (req, res, next) => {
  try {
    if (typeof req.body?.onlineOrderingEnabled !== 'boolean') return res.status(400).json({ code: 'validation_error', message: 'onlineOrderingEnabled must be a boolean.' });
    const message = typeof req.body.message === 'string' && req.body.message.trim() ? req.body.message.trim().slice(0, 240) : 'Online ordering is temporarily unavailable.';
    if (/[<>]/.test(message)) return res.status(400).json({ code: 'validation_error', message: 'The customer-facing message must be plain text.' });
    const reason = String(req.body.reason || '').trim().slice(0, 500);
    const current = await PaymentConfiguration.findOne({ key: 'v1' }).select('ordersEnabled').lean();
    const previous = current?.ordersEnabled === true;
    const enabled = req.body.onlineOrderingEnabled === true;
    const saved = await PaymentConfiguration.findOneAndUpdate({ key: 'v1' }, { $set: { ordersEnabled: enabled, orderingDisabledMessage: message, ordersUpdatedBy: req.admin._id, ordersUpdatedAt: new Date(), ordersUpdateReason: reason } }, { upsert: true, new: true, setDefaultsOnInsert: true, runValidators: true }).lean();
    await audit(req, enabled ? 'online_ordering_enabled' : 'online_ordering_disabled', true, { previous, next: enabled, reason });
    return res.json({ data: { onlineOrderingEnabled: saved.ordersEnabled === true, message: saved.orderingDisabledMessage, updatedAt: saved.ordersUpdatedAt, updatedBy: { id: String(req.admin._id), email: req.admin.email, name: req.admin.name }, reason: saved.ordersUpdateReason || '' } });
  } catch (error) { return next(error); }
});

router.put('/storefront/announcement', requireAdminPermission('settings.manage'), async (req, res, next) => {
  try {
    const enabled = req.body?.enabled;
    const message = typeof req.body?.message === 'string' ? req.body.message.trim() : '';
    if (typeof enabled !== 'boolean' || message.length < 1 || message.length > 180 || /[<>]/.test(message)) {
      return res.status(400).json({ code: 'validation_error', message: 'Announcement enabled state and plain-text message (1–180 characters) are required.' });
    }
    const setting = await StorefrontSetting.findOneAndUpdate(
      { key: SETTING_KEY },
      { $set: { announcementEnabled: enabled, announcementMessage: message } },
      { new: true, upsert: true, setDefaultsOnInsert: true, runValidators: true }
    ).lean();
    await audit(req, 'storefront_announcement_updated', true, { enabled, messageLength: message.length });
    return res.json({ data: publicAnnouncement(setting) });
  } catch (error) { return next(error); }
});

router.get('/newsletter/subscribers', requireAdminPermission('newsletter.read'), async (req, res, next) => {
  try {
    const { page, pageSize, skip } = pageQuery(req);
    const status = ['pending', 'confirmed', 'unsubscribed'].includes(String(req.query.status)) ? String(req.query.status) : null;
    const search = String(req.query.search || '').trim().slice(0, 100);
    const filter = {};
    if (status) filter.status = status;
    if (search) filter.email = { $regex: search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), $options: 'i' };
    const [items, total] = await Promise.all([
      NewsletterSubscriber.find(filter).select('email status source consentAt confirmedAt unsubscribedAt createdAt').sort({ createdAt: -1 }).skip(skip).limit(pageSize).lean(),
      NewsletterSubscriber.countDocuments(filter),
    ]);
    return res.json({ data: items.map(item => ({ id: String(item._id), ...item })), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } });
  } catch (error) { return next(error); }
});

router.get('/newsletter/overview', requireAdminPermission('newsletter.read'), async (req, res, next) => {
  try {
    const [total, confirmed, pending, unsubscribed] = await Promise.all([
      NewsletterSubscriber.countDocuments(),
      NewsletterSubscriber.countDocuments({ status: 'confirmed' }),
      NewsletterSubscriber.countDocuments({ status: 'pending' }),
      NewsletterSubscriber.countDocuments({ status: 'unsubscribed' }),
    ]);
    return res.json({ data: { total, confirmed, pending, unsubscribed } });
  } catch (error) { return next(error); }
});

router.get('/newsletter/subscribers/export', requireAdminPermission('newsletter.export'), async (req, res, next) => {
  try {
    const items = await NewsletterSubscriber.find().select('email status source consentAt confirmedAt unsubscribedAt createdAt').sort({ createdAt: -1 }).lean();
    const csvValue = value => `"${String(value || '').replace(/"/g, '""')}"`;
    const lines = ['email,status,source,consentAt,confirmedAt,unsubscribedAt,createdAt', ...items.map(item => [item.email, item.status, item.source, item.consentAt, item.confirmedAt, item.unsubscribedAt, item.createdAt].map(csvValue).join(','))];
    await audit(req, 'newsletter_exported', true, { count: items.length });
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader('Content-Disposition', 'attachment; filename="newsletter-subscribers.csv"');
    return res.send(lines.join('\n'));
  } catch (error) { return next(error); }
});

router.get('/dashboard/overview', requireAdminPermission('dashboard.read'), async (req, res, next) => {
  try {
    const [customers, activeCustomers, products, activeProducts, outOfStock, orders, pendingOrders, completedOrders, cancelledOrders, gross, recentOrders, recentCustomers] = await Promise.all([
      User.countDocuments(), User.countDocuments({ status: { $ne: 'suspended' } }), Product.countDocuments(), Product.countDocuments({ $or: [{ status: 'active' }, { status: { $exists: false } }] }), Product.countDocuments({ stock: { $lte: 0 } }), Order.countDocuments(), Order.countDocuments({ statusIndex: 0 }), Order.countDocuments({ statusIndex: { $gte: 9 } }), Order.countDocuments({ statusIndex: -1 }), Order.aggregate([{ $match: { statusIndex: { $gte: 0 } } }, { $group: { _id: null, value: { $sum: '$total' } } }]), Order.find().sort({ createdAt: -1 }).limit(5).lean(), User.find().select('name email date status').sort({ date: -1 }).limit(5).lean(),
    ]);
    return res.json({ data: { totalCustomers: customers, activeCustomers, totalSellers: 0, totalProducts: products, activeProducts, outOfStockProducts: outOfStock, totalOrders: orders, pendingOrders, completedOrders, cancelledOrders, grossMerchandiseValue: gross[0]?.value || 0, confirmedCollectedRevenue: 0, pendingManualPayments: 0, recentOrders: recentOrders.map(orderView), recentCustomers: recentCustomers.map((user) => ({ id: String(user._id), name: user.name, email: user.email, createdAt: user.date, status: user.status || 'active' })), topSellingProducts: [], revenueByMonth: [], orderCountByMonth: [] } });
  } catch (error) { return next(error); }
});

router.get('/dashboard/analytics', requireAdminPermission('dashboard.read'), async (req, res, next) => {
  try {
    const allowedRanges = new Set(['today', 'yesterday', '7d', '30d', '90d', 'month', 'year']);
    const range = allowedRanges.has(String(req.query.range)) ? String(req.query.range) : '30d';
    const now = new Date();
    const start = new Date(now);
    if (range === 'today') start.setHours(0, 0, 0, 0);
    else if (range === 'yesterday') { start.setDate(start.getDate() - 1); start.setHours(0, 0, 0, 0); }
    else if (range === 'month') { start.setDate(1); start.setHours(0, 0, 0, 0); }
    else if (range === 'year') { start.setMonth(0, 1); start.setHours(0, 0, 0, 0); }
    else start.setDate(start.getDate() - (range === '7d' ? 7 : range === '90d' ? 90 : 30));
    const end = now;
    const dateFilter = { createdAt: { $gte: start, $lte: end } };
    const [orderTotals, paymentTotals, statusCounts, dailySales, topProducts, newCustomers, catalog] = await Promise.all([
      Order.aggregate([{ $match: dateFilter }, { $group: { _id: null, orders: { $sum: 1 }, gmv: { $sum: { $cond: [{ $gte: ['$statusIndex', 0] }, '$total', 0] } }, cancelled: { $sum: { $cond: [{ $lt: ['$statusIndex', 0] }, '$total', 0] } } } }]),
      Order.aggregate([{ $match: dateFilter }, { $group: { _id: '$paymentStatus', amount: { $sum: '$total' }, count: { $sum: 1 } } }]),
      Order.aggregate([{ $match: dateFilter }, { $group: { _id: '$statusIndex', count: { $sum: 1 }, amount: { $sum: '$total' } } }, { $sort: { _id: 1 } }]),
      Order.aggregate([{ $match: { ...dateFilter, statusIndex: { $gte: 0 } } }, { $group: { _id: { $dateToString: { format: '%Y-%m-%d', date: '$createdAt', timezone: 'Asia/Karachi' } }, value: { $sum: '$total' }, orders: { $sum: 1 } } }, { $sort: { _id: 1 } }]),
      Order.aggregate([{ $match: { ...dateFilter, statusIndex: { $gte: 0 } } }, { $unwind: '$items' }, { $group: { _id: '$items.productId', name: { $first: '$items.name' }, quantity: { $sum: '$items.quantity' }, sales: { $sum: { $multiply: ['$items.price', '$items.quantity'] } } } }, { $sort: { sales: -1 } }, { $limit: 10 }]),
      User.countDocuments({ date: { $gte: start, $lte: end } }),
      Promise.all([Product.countDocuments(), Product.countDocuments({ status: 'active' }), Product.countDocuments({ status: 'draft' }), Product.countDocuments({ status: 'archived' }), Product.countDocuments({ productType: 'DIGITAL' }), Product.countDocuments({ productType: { $ne: 'DIGITAL' }, stock: { $lte: 0 }, inventoryMode: 'NUMERIC' })]),
    ]);
    const payments = Object.fromEntries(paymentTotals.map(item => [item._id || 'unknown', { amount: item.amount, count: item.count }]));
    const statusLabels = { '-1': 'cancelled', '0': 'pending', '1': 'processing', '2': 'shipped', '9': 'delivered' };
    return res.json({ data: { range, start, end, timezone: 'Asia/Karachi', orderTotals: orderTotals[0] || { orders: 0, gmv: 0, cancelled: 0 }, payments, statusCounts: statusCounts.map(item => ({ status: statusLabels[String(item._id)] || `status_${item._id}`, count: item.count, amount: item.amount })), dailySales: dailySales.map(item => ({ label: item._id, value: item.value, orders: item.orders })), topProducts: topProducts.map(item => ({ id: String(item._id), name: item.name, quantity: item.quantity, sales: item.sales })), newCustomers, catalog: { total: catalog[0], published: catalog[1], drafts: catalog[2], archived: catalog[3], digital: catalog[4], numericOutOfStock: catalog[5] }, limitations: ['Gross profit, net sales, refunds, supplier returns, commissions and traffic conversion are unavailable until their underlying records and accounting rules are implemented.'] } });
  } catch (error) { return next(error); }
});

router.get('/products', requireAdminPermission('products.read'), async (req, res, next) => {
  try {
    const { page, pageSize, skip } = pageQuery(req); const filter = {}; const search = String(req.query.search || '').trim();
    if (search) filter.$or = [{ name: { $regex: search.slice(0, 80), $options: 'i' } }, { sku: { $regex: search.slice(0, 40), $options: 'i' } }];
    if (req.query.category && req.query.category !== 'all') filter.category = String(req.query.category);
    if (req.query.status && req.query.status !== 'all' && PRODUCT_STATUSES.has(String(req.query.status))) filter.status = String(req.query.status);
    const [items, total] = await Promise.all([Product.find(filter).sort(sortQuery(req.query.sort, new Set(['name', 'category', 'price', 'stock', 'createdAt']))).skip(skip).limit(pageSize).lean(), Product.countDocuments(filter)]);
    return res.json({ data: items.map(productView), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } });
  } catch (error) { return next(error); }
});

// ---------------------------------------------------------------------------
// Supplier catalog import and pricing approval
// ---------------------------------------------------------------------------

router.post('/product-imports/preview', express.text({ type: ['text/csv', 'text/plain', 'application/octet-stream'], limit: '10mb' }), requireAdminPermission('products.import'), async (req, res, next) => {
  try {
    const { rows, entries } = buildEntries(req.body);
    const keys = entries.map((entry) => entry.supplierKey);
    const existing = await Product.find({ supplierKey: { $in: keys } }).select('supplierKey').lean();
    const existingKeys = new Set(existing.map((item) => item.supplierKey));
    const valid = entries.filter((entry) => entry.preview.validation === 'valid');
    const summary = {
      rows: rows.length, uniqueProducts: entries.length,
      imageRows: entries.reduce((sum, entry) => sum + entry.item.images.length, 0),
      newProducts: valid.filter((entry) => !existingKeys.has(entry.supplierKey)).length,
      existingProducts: existingKeys.size, skippedProducts: 0, failedProducts: 0,
      invalidRows: entries.length - valid.length,
      missingPrices: entries.filter((entry) => entry.item.sourcePrice === null).length,
      missingImages: entries.filter((entry) => entry.item.images.length === 0).length,
    };
    const stagedBytes = Buffer.byteLength(JSON.stringify(entries), 'utf8');
    if (stagedBytes > 12 * 1024 * 1024) return res.status(400).json({ code: 'validation_error', message: 'CSV preview is too large to stage securely. Split the file into smaller imports.' });
    const preview = await CatalogImport.create({ supplier: SUPPLIER, filename: safeFilename(req.headers['x-source-filename']), uploadedBy: req.admin._id, status: 'PREVIEWED', previewExpiresAt: new Date(Date.now() + 30 * 60 * 1000), summary, stagedProducts: entries });
    return res.json({ data: { id: String(preview._id), expiresAt: preview.previewExpiresAt, supplier: SUPPLIER, filename: preview.filename, summary, products: entries.slice(0, 1000).map((entry) => entry.preview) } });
  } catch (error) { return res.status(400).json({ code: 'validation_error', message: error.message || 'The CSV could not be validated.' }); }
});

router.post('/product-imports/confirm', requireAdminPermission('products.import'), async (req, res, next) => {
  try {
    const importId = String(req.body?.importId || '');
    if (!mongoose.Types.ObjectId.isValid(importId) || req.body?.confirm !== true) return res.status(400).json({ code: 'validation_error', message: 'A valid preview and explicit confirmation are required.' });
    const history = await CatalogImport.findOne({ _id: importId, status: 'PREVIEWED' }).select('+stagedProducts');
    if (!history) return res.status(404).json({ code: 'not_found', message: 'Import preview not found or already completed.' });
    if (history.previewExpiresAt <= new Date()) return res.status(409).json({ code: 'conflict', message: 'Import preview expired. Upload the CSV again to create a fresh preview.' });
    if (String(history.uploadedBy) !== String(req.admin._id) && req.admin.role !== 'super_admin') return res.status(403).json({ code: 'forbidden', message: 'Only the uploading administrator or a Super Admin can confirm this import.' });
    const mode = ['new_only', 'update_existing', 'skip_existing'].includes(String(req.body?.mode)) ? String(req.body.mode) : 'new_only';
    const entries = history.stagedProducts;
    const rows = history.summary.rows;
    const keys = entries.map((entry) => entry.supplierKey);
    const existing = await Product.find({ supplierKey: { $in: keys } }).select('supplierKey').lean();
    const existingKeys = new Set(existing.map((item) => item.supplierKey));
    let imported = 0; let skipped = 0; let failed = 0; const errors = [];
    const categoryCache = new Set();
    for (const entry of entries) {
      if (!entry.item.title || !entry.item.images.length || entry.item.sourcePrice === null) { skipped += 1; continue; }
      if (existingKeys.has(entry.supplierKey) && mode !== 'update_existing') { skipped += 1; continue; }
      try {
        const document = makeProductDocument(entry);
        const parentSlug = entry.category.parent.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const childSlug = entry.category.child.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        if (!categoryCache.has(parentSlug)) {
          await Category.findOneAndUpdate({ slug: parentSlug, parentSlug: null }, { $setOnInsert: { name: entry.category.parent, slug: parentSlug, parentSlug: null, active: true } }, { upsert: true, setDefaultsOnInsert: true });
          categoryCache.add(parentSlug);
        }
        if (!categoryCache.has(`${parentSlug}/${childSlug}`)) {
          await Category.findOneAndUpdate({ slug: childSlug, parentSlug }, { $setOnInsert: { name: entry.category.child, slug: childSlug, parentSlug, active: true } }, { upsert: true, setDefaultsOnInsert: true });
          categoryCache.add(`${parentSlug}/${childSlug}`);
        }
        const { status, pricingStatus, price, confirmedSupplierCost, operationalCostEstimate, sellingPrice, targetProfit, estimatedNetProfit, draftSellingPrice, lastPriceReviewAt, ...sourceRefreshable } = document;
        const update = mode === 'update_existing'
          ? { $set: sourceRefreshable }
          : { $set: sourceRefreshable, $setOnInsert: { status, pricingStatus, price, confirmedSupplierCost, operationalCostEstimate, sellingPrice, targetProfit, estimatedNetProfit, draftSellingPrice, lastPriceReviewAt } };
        await Product.findOneAndUpdate({ supplierKey: entry.supplierKey }, update, { upsert: true, new: true, setDefaultsOnInsert: true, runValidators: true });
        imported += 1;
      } catch (error) { failed += 1; errors.push({ supplierKey: entry.supplierKey, message: error.message }); }
    }
    const completed = await CatalogImport.findByIdAndUpdate(history._id, { $set: { status: failed ? 'FAILED' : 'COMPLETED', mode, confirmedAt: new Date(), summary: { rows, uniqueProducts: entries.length, imageRows: entries.reduce((sum, entry) => sum + entry.item.images.length, 0), newProducts: imported, existingProducts: existingKeys.size, skippedProducts: skipped, failedProducts: failed, invalidRows: entries.filter((entry) => entry.preview.validation !== 'valid').length, missingPrices: entries.filter((entry) => entry.item.sourcePrice === null).length, missingImages: entries.filter((entry) => entry.item.images.length === 0).length }, errors: errors.slice(0, 100), stagedProducts: [] } }, { new: true });
    await audit(req, 'supplier_catalog_imported', failed === 0, { importId: String(completed._id), supplier: SUPPLIER, imported, skipped, failed, mode });
    return res.status(201).json({ data: { id: String(completed._id), imported, skipped, failed, status: completed.status } });
  } catch (error) { return res.status(400).json({ code: 'validation_error', message: error.message }); }
});

router.get('/product-imports', requireAdminPermission('products.import'), async (req, res, next) => {
  try {
    const { page, pageSize, skip } = pageQuery(req);
    const [items, total] = await Promise.all([CatalogImport.find().sort({ createdAt: -1 }).skip(skip).limit(pageSize).lean(), CatalogImport.countDocuments()]);
    return res.json({ data: items.map((item) => ({ id: String(item._id), supplier: item.supplier, filename: item.filename, uploadedBy: String(item.uploadedBy), status: item.status, mode: item.mode, summary: item.summary, createdAt: item.createdAt })), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } });
  } catch (error) { return next(error); }
});

router.get('/pricing/products', requireAdminPermission('products.pricing.read'), async (req, res, next) => {
  try {
    const { page, pageSize, skip } = pageQuery(req); const filter = {};
    if (req.query.pricingStatus && ['REVIEW_REQUIRED', 'APPROVED', 'MANUAL_OVERRIDE'].includes(String(req.query.pricingStatus))) filter.pricingStatus = String(req.query.pricingStatus);
    if (req.query.supplier) filter.supplierName = String(req.query.supplier).slice(0, 80);
    const search = String(req.query.search || '').trim().slice(0, 80);
    if (search) filter.$or = [{ name: { $regex: search, $options: 'i' } }, { supplierSKU: { $regex: search, $options: 'i' } }, { supplierKey: { $regex: search, $options: 'i' } }];
    const [items, total] = await Promise.all([Product.find(filter).sort({ updatedAt: -1 }).skip(skip).limit(pageSize).lean(), Product.countDocuments(filter)]);
    return res.json({ data: items.map(pricingView), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } });
  } catch (error) { return next(error); }
});

router.patch('/pricing/products/:id', requireAdminPermission('products.pricing.manage'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Product not found.' });
    const product = await Product.findById(req.params.id); if (!product) return res.status(404).json({ code: 'not_found', message: 'Product not found.' });
    const body = req.body || {}; const mode = ['fixed_profit', 'percentage_markup', 'manual'].includes(body.mode) ? body.mode : null;
    if (!mode) return res.status(400).json({ code: 'validation_error', message: 'A valid pricing mode is required.' });
    const cost = pricingNumber(body.confirmedSupplierCost); const operations = pricingNumber(body.operationalCostEstimate); const targetProfit = pricingNumber(body.targetProfit); const markupPercent = pricingNumber(body.markupPercent); const manualPrice = pricingNumber(body.sellingPrice, 'sellingPrice', { nullable: false });
    if (cost === undefined || Number.isNaN(cost) || operations === undefined || Number.isNaN(operations)) return res.status(400).json({ code: 'validation_error', message: 'Supplier cost and operational cost must be valid non-negative numbers.' });
    let sellingPrice = manualPrice; let profit = null;
    if (mode === 'fixed_profit') { if (cost === null || targetProfit === null || Number.isNaN(targetProfit)) return res.status(400).json({ code: 'validation_error', message: 'Confirmed supplier cost and target profit are required.' }); sellingPrice = cost + (operations || 0) + targetProfit; profit = targetProfit; }
    if (mode === 'percentage_markup') { if (cost === null || markupPercent === null || Number.isNaN(markupPercent)) return res.status(400).json({ code: 'validation_error', message: 'Confirmed supplier cost and markup percentage are required.' }); sellingPrice = (cost + (operations || 0)) * (1 + markupPercent / 100); profit = sellingPrice - cost - (operations || 0); }
    if (!Number.isFinite(sellingPrice) || sellingPrice <= 0) return res.status(400).json({ code: 'validation_error', message: 'Selling price must be greater than zero.' });
    const updated = await Product.findByIdAndUpdate(req.params.id, { $set: { confirmedSupplierCost: cost, operationalCostEstimate: operations, targetProfit: targetProfit ?? null, sellingPrice: Math.round(sellingPrice * 100) / 100, estimatedNetProfit: profit === null ? null : Math.round(profit * 100) / 100, price: Math.round(sellingPrice * 100) / 100, pricingStatus: mode === 'manual' ? 'MANUAL_OVERRIDE' : 'REVIEW_REQUIRED', lastPriceReviewAt: new Date(), updatedAt: new Date() } }, { new: true, runValidators: true }).lean();
    await audit(req, 'product_pricing_updated', true, { productId: req.params.id, mode, pricingStatus: updated.pricingStatus });
    return res.json({ data: pricingView(updated) });
  } catch (error) { return next(error); }
});

router.post('/pricing/approve', requireAdminPermission('products.pricing.manage'), async (req, res, next) => {
  try {
    const ids = Array.isArray(req.body?.ids) ? [...new Set(req.body.ids.map(String))] : [];
    if (req.body?.confirm !== true || ids.length === 0 || ids.length > 500 || ids.some((id) => !mongoose.Types.ObjectId.isValid(id))) return res.status(400).json({ code: 'validation_error', message: 'Explicit confirmation and up to 500 valid product IDs are required.' });
    const products = await Product.find({ _id: { $in: ids } }).select('sellingPrice confirmedSupplierCost pricingStatus').lean();
    const eligible = products.filter((product) => Number(product.sellingPrice) > 0 && (product.pricingStatus === 'MANUAL_OVERRIDE' || Number.isFinite(product.confirmedSupplierCost)));
    if (eligible.length !== ids.length) return res.status(409).json({ code: 'conflict', message: 'Every selected product needs a valid selling price and confirmed supplier cost, unless manually overridden.', eligible: eligible.map((product) => String(product._id)) });
    await Product.updateMany({ _id: { $in: ids } }, { $set: { pricingStatus: 'APPROVED', lastPriceReviewAt: new Date(), updatedAt: new Date() } });
    await audit(req, 'products_pricing_approved', true, { count: ids.length, productIds: ids });
    return res.json({ data: { approved: ids.length } });
  } catch (error) { return next(error); }
});

router.post('/pricing/publish', requireAdminPermission('products.publish'), async (req, res, next) => {
  try {
    const ids = Array.isArray(req.body?.ids) ? [...new Set(req.body.ids.map(String))] : [];
    if (req.body?.confirm !== true || ids.length === 0 || ids.length > 500 || ids.some((id) => !mongoose.Types.ObjectId.isValid(id))) return res.status(400).json({ code: 'validation_error', message: 'Explicit confirmation and up to 500 valid product IDs are required.' });
    const candidates = await Product.find({ _id: { $in: ids } }).select('name category image sellingPrice pricingStatus status').lean();
    const eligible = candidates.filter((product) => Number(product.sellingPrice) > 0 && product.name && product.category && product.image && ['APPROVED', 'MANUAL_OVERRIDE'].includes(product.pricingStatus));
    if (eligible.length !== ids.length) return res.status(409).json({ code: 'conflict', message: 'Only products with approved pricing and complete public information can be published.', eligible: eligible.map((product) => String(product._id)) });
    await Product.updateMany({ _id: { $in: ids } }, [{ $set: { status: 'active', price: '$sellingPrice', updatedAt: new Date() } }]);
    await audit(req, 'products_published', true, { count: ids.length, productIds: ids });
    return res.json({ data: { published: ids.length } });
  } catch (error) { return next(error); }
});

router.get('/products/:id', requireAdminPermission('products.read'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Product not found.' });
    const product = await Product.findById(req.params.id).lean(); return product ? res.json({ data: productView(product) }) : res.status(404).json({ code: 'not_found', message: 'Product not found.' });
  } catch (error) { return next(error); }
});

router.post('/products', requireAdminPermission('products.create'), async (req, res, next) => {
  try {
    const body = req.body || {}; const flexibleError = validateFlexibleFields(body); if (flexibleError) return res.status(400).json({ code: 'validation_error', message: flexibleError });
    const name = String(body.name || '').trim(); const category = String(body.category || '').trim(); const price = Number(body.price); const stock = Number(body.inventory ?? body.stock ?? 0); const status = body.status || 'draft'; const inventoryMode = ['AVAILABILITY_BASED', 'SUPPLIER_BASED'].includes(body.inventoryMode) ? body.inventoryMode : 'NUMERIC'; const availabilityStatus = inventoryMode === 'SUPPLIER_BASED' ? 'AVAILABLE_TO_ORDER' : (body.availabilityStatus === 'IN_STOCK' ? 'IN_STOCK' : 'OUT_OF_STOCK');
    const variantError = validateVariants(body.variantAttributes, body.variants); if (variantError) return res.status(400).json({ code: 'validation_error', message: variantError });
    if (name.length < 2 || name.length > 120 || !category || !Number.isFinite(price) || price <= 0 || (inventoryMode === 'NUMERIC' && (!Number.isInteger(stock) || stock < 0)) || !PRODUCT_STATUSES.has(status) || !validImageValue(String(body.image || '').trim())) return res.status(400).json({ code: 'validation_error', message: 'Invalid product fields or image.' });
    const product = await Product.create({ name, description: String(body.description || '').trim(), category, price, stock: inventoryMode === 'NUMERIC' ? stock : 0, inventoryMode, availabilityStatus, status, productType: body.productType || 'PHYSICAL', sku: body.sku ? String(body.sku).trim().toUpperCase() : undefined, image: String(body.image || '').trim(), brand: body.brand ? String(body.brand).trim() : undefined, variantsEnabled: body.variantsEnabled === true, variantAttributes: body.variantAttributes || [], variants: body.variants || [], specifications: body.specifications || [], attributes: body.attributes || [], physicalConfiguration: body.productType === 'DIGITAL' ? undefined : (body.physicalConfiguration || undefined), digitalConfiguration: body.productType === 'DIGITAL' ? (body.digitalConfiguration || {}) : undefined, seo: body.seo || undefined });
    await audit(req, 'product_created', true, { productId: String(product._id) }); return res.status(201).json({ data: productView(product) });
  } catch (error) { if (error.code === 11000) return res.status(409).json({ code: 'conflict', message: 'A product with that name or SKU already exists.' }); return next(error); }
});

router.patch('/products/:id', requireAdminPermission('products.update'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Product not found.' });
    const flexibleError = validateFlexibleFields(req.body || {}); if (flexibleError) return res.status(400).json({ code: 'validation_error', message: flexibleError });
    const allowed = ['name', 'description', 'category', 'price', 'sku', 'image', 'brand', 'featured', 'status', 'inventoryMode', 'availabilityStatus', 'variantsEnabled', 'variantAttributes', 'variants', 'productType', 'specifications', 'attributes', 'physicalConfiguration', 'digitalConfiguration', 'seo']; const update = {};
    for (const key of allowed) if (Object.prototype.hasOwnProperty.call(req.body || {}, key)) update[key] = req.body[key];
    if (update.productType === 'DIGITAL') delete update.physicalConfiguration;
    if (update.productType === 'PHYSICAL') delete update.digitalConfiguration;
    if (Object.prototype.hasOwnProperty.call(update, 'variantAttributes') || Object.prototype.hasOwnProperty.call(update, 'variants')) { const variantError = validateVariants(update.variantAttributes || [], update.variants || []); if (variantError) return res.status(400).json({ code: 'validation_error', message: variantError }); }
    if (Object.prototype.hasOwnProperty.call(req.body || {}, 'inventory')) update.stock = Number(req.body.inventory); if (Object.prototype.hasOwnProperty.call(update, 'inventoryMode') && !['NUMERIC', 'AVAILABILITY_BASED', 'SUPPLIER_BASED'].includes(update.inventoryMode)) return res.status(400).json({ code: 'validation_error', message: 'Invalid inventory mode.' }); if (Object.prototype.hasOwnProperty.call(update, 'availabilityStatus') && !['IN_STOCK', 'OUT_OF_STOCK', 'AVAILABLE_TO_ORDER'].includes(update.availabilityStatus)) return res.status(400).json({ code: 'validation_error', message: 'Invalid availability status.' }); if (Object.prototype.hasOwnProperty.call(update, 'price')) update.price = Number(update.price); if (Object.prototype.hasOwnProperty.call(update, 'image') && !validImageValue(String(update.image || '').trim())) return res.status(400).json({ code: 'validation_error', message: 'Invalid product image.' }); if (Object.prototype.hasOwnProperty.call(update, 'stock') && (!Number.isInteger(update.stock) || update.stock < 0)) return res.status(400).json({ code: 'validation_error', message: 'Inventory cannot be negative.' }); if (update.inventoryMode === 'AVAILABILITY_BASED' || update.inventoryMode === 'SUPPLIER_BASED') update.stock = 0; if (update.inventoryMode === 'SUPPLIER_BASED') update.availabilityStatus = 'AVAILABLE_TO_ORDER'; update.updatedAt = new Date();
    const current = await Product.findById(req.params.id).select('supplierName pricingStatus sellingPrice').lean(); if (!current) return res.status(404).json({ code: 'not_found', message: 'Product not found.' });
    if (update.status === 'active' && current.supplierName === 'YOURMART' && !['APPROVED', 'MANUAL_OVERRIDE'].includes(current.pricingStatus) && !['APPROVED', 'MANUAL_OVERRIDE'].includes(req.body?.pricingStatus)) return res.status(409).json({ code: 'pricing_review_required', message: 'Supplier products require approved pricing before publication.' });
    const updateOperation = { $set: update };
    if (req.body?.productType === 'DIGITAL') updateOperation.$unset = { physicalConfiguration: 1 };
    if (req.body?.productType === 'PHYSICAL') updateOperation.$unset = { digitalConfiguration: 1 };
    const product = await Product.findByIdAndUpdate(req.params.id, updateOperation, { new: true, runValidators: true }).lean();
    await audit(req, 'product_updated', true, { productId: req.params.id, fields: Object.keys(update) }); return res.json({ data: productView(product) });
  } catch (error) { if (error.code === 11000) return res.status(409).json({ code: 'conflict', message: 'A product with that name or SKU already exists.' }); return next(error); }
});

router.delete('/products/:id', requireAdminPermission('products.delete'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Product not found.' });
    if (await Order.exists({ 'items.productId': req.params.id })) return res.status(409).json({ code: 'conflict', message: 'Products referenced by orders cannot be deleted; archive them instead.' });
    const deleted = await Product.findByIdAndDelete(req.params.id); if (!deleted) return res.status(404).json({ code: 'not_found', message: 'Product not found.' }); await audit(req, 'product_deleted', true, { productId: req.params.id }); return res.status(204).send();
  } catch (error) { return next(error); }
});

router.get('/customers', requireAdminPermission('customers.read'), async (req, res, next) => {
  try {
    const { page, pageSize, skip } = pageQuery(req); const search = String(req.query.search || '').trim(); const filter = search ? { $or: [{ name: { $regex: search.slice(0, 80), $options: 'i' } }, { email: { $regex: search.slice(0, 120), $options: 'i' } }] } : {};
    const [items, total] = await Promise.all([User.find(filter).select('name email date status').sort({ date: -1 }).skip(skip).limit(pageSize).lean(), User.countDocuments(filter)]); return res.json({ data: items.map((user) => ({ id: String(user._id), name: user.name, email: user.email, role: 'customer', status: user.status || 'active', createdAt: user.date, lastLoginAt: null })), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } });
  } catch (error) { return next(error); }
});

router.get('/customers/:id', requireAdminPermission('customers.read'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Customer not found.' });
    const user = await User.findById(req.params.id).select('name email date status').lean();
    return user ? res.json({ data: { id: String(user._id), name: user.name, email: user.email, role: 'customer', status: user.status || 'active', createdAt: user.date, lastLoginAt: null } }) : res.status(404).json({ code: 'not_found', message: 'Customer not found.' });
  } catch (error) { return next(error); }
});

router.patch('/customers/:id', requireAdminPermission('customers.manage'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Customer not found.' });
    const update = {};
    if (req.body?.name !== undefined) update.name = String(req.body.name).trim();
    if (req.body?.email !== undefined) update.email = String(req.body.email).trim().toLowerCase();
    if (req.body?.status !== undefined && ['active', 'suspended'].includes(req.body.status)) update.status = req.body.status;
    const user = await User.findByIdAndUpdate(req.params.id, { $set: update }, { new: true, runValidators: true }).select('name email date status').lean();
    if (!user) return res.status(404).json({ code: 'not_found', message: 'Customer not found.' });
    await audit(req, 'customer_updated', true, { customerId: req.params.id, fields: Object.keys(update) });
    return res.json({ data: { id: String(user._id), name: user.name, email: user.email, role: 'customer', status: user.status || 'active', createdAt: user.date, lastLoginAt: null } });
  } catch (error) { if (error.code === 11000) return res.status(409).json({ code: 'conflict', message: 'That email is already registered.' }); return next(error); }
});

router.get('/orders', requireAdminPermission('orders.read'), async (req, res, next) => {
  try {
    const { page, pageSize, skip } = pageQuery(req); const search = String(req.query.search || '').trim(); const filter = search ? { $or: [{ orderNumber: { $regex: search.slice(0, 80), $options: 'i' } }, { email: { $regex: search.slice(0, 120), $options: 'i' } }, { name: { $regex: search.slice(0, 80), $options: 'i' } }] } : {};
    const [items, total] = await Promise.all([Order.find(filter).sort({ createdAt: -1 }).skip(skip).limit(pageSize).lean(), Order.countDocuments(filter)]); return res.json({ data: items.map(orderView), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } });
  } catch (error) { return next(error); }
});

router.get('/orders/:id', requireAdminPermission('orders.read'), async (req, res, next) => {
  try { if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Order not found.' }); const order = await Order.findById(req.params.id).lean(); return order ? res.json({ data: orderView(order) }) : res.status(404).json({ code: 'not_found', message: 'Order not found.' }); } catch (error) { return next(error); }
});

router.patch('/orders/:id', requireAdminPermission('orders.manage'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id) || !ORDER_STATUSES.has(req.body?.status)) return res.status(400).json({ code: 'validation_error', message: 'A valid order status is required.' }); const order = await Order.findById(req.params.id); if (!order) return res.status(404).json({ code: 'not_found', message: 'Order not found.' }); const current = orderStatus(order); const allowed = { pending: ['processing', 'cancelled'], processing: ['shipped', 'cancelled'], shipped: ['delivered'], delivered: [], cancelled: [] }; if (req.body.status !== current && !allowed[current].includes(req.body.status)) return res.status(409).json({ code: 'conflict', message: `Cannot move an order from ${current} to ${req.body.status}.` }); const target = req.body.status; if (target === 'cancelled') order.statusIndex = -1; else if (target === 'processing') order.statusIndex = Math.max(order.statusIndex, 1); else if (target === 'shipped') order.statusIndex = Math.max(order.statusIndex, 6); else if (target === 'delivered') order.statusIndex = Math.max(order.statusIndex, 9); order.updatedAt = new Date(); await order.save(); await audit(req, 'order_status_changed', true, { orderId: req.params.id, from: current, to: target }); return res.json({ data: orderView(order) });
  } catch (error) { return next(error); }
});

router.patch('/orders/:id/availability', requireAdminPermission('orders.manage'), async (req, res, next) => {
  try {
    const status = String(req.body?.status || '');
    if (!['CONFIRMED', 'UNAVAILABLE'].includes(status) || !mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(400).json({ code: 'validation_error', message: 'A valid availability status is required.' });
    const order = await Order.findById(req.params.id);
    if (!order) return res.status(404).json({ code: 'not_found', message: 'Order not found.' });
    if (order.availabilityStatus !== 'PENDING_CONFIRMATION') return res.status(409).json({ code: 'conflict', message: 'Availability has already been confirmed.' });
    order.availabilityStatus = status;
    await order.save();
    if (status === 'UNAVAILABLE' && order.paymentStatus === 'verified') {
      const existing = await Refund.findOne({ orderId: order._id, state: { $in: ['REFUND_PENDING', 'REFUND_PROCESSING', 'REFUNDED'] } });
      if (!existing) await Refund.create({ orderId: order._id, amount: order.total, currency: order.currency || 'PKR', reason: 'Availability could not be confirmed for one or more items.', method: 'MANUAL_REPAYMENT', initiatedBy: req.admin._id });
    }
    await audit(req, 'order_availability_updated', true, { orderId: req.params.id, status });
    return res.json({ data: { id: String(order._id), availabilityStatus: order.availabilityStatus, refundWorkflowStarted: status === 'UNAVAILABLE' && order.paymentStatus === 'verified' } });
  } catch (error) { return next(error); }
});

router.post('/orders/:id/refund', requireAdminPermission('refunds.manage'), async (req, res, next) => {
  try {
    if (!mongoose.Types.ObjectId.isValid(req.params.id)) return res.status(404).json({ code: 'not_found', message: 'Order not found.' });
    const order = await Order.findById(req.params.id);
    if (!order) return res.status(404).json({ code: 'not_found', message: 'Order not found.' });
    if (order.paymentStatus !== 'verified') return res.status(409).json({ code: 'conflict', message: 'Only collected payments can enter the refund workflow.' });
    const amount = Number(req.body?.amount ?? order.total);
    if (!Number.isFinite(amount) || amount <= 0 || amount > order.total) return res.status(400).json({ code: 'validation_error', message: 'Refund amount must be greater than zero and no more than the collected amount.' });
    const existing = await Refund.findOne({ orderId: order._id, state: { $in: ['REFUND_PENDING', 'REFUND_PROCESSING', 'REFUNDED'] } });
    if (existing) return res.status(409).json({ code: 'conflict', message: 'A refund workflow already exists for this order.' });
    const refund = await Refund.create({ orderId: order._id, amount, currency: order.currency || 'PKR', reason: String(req.body?.reason || '').trim().slice(0, 500) || 'Customer refund requested.', method: req.body?.method === 'PAYMENT_REVERSAL' ? 'PAYMENT_REVERSAL' : 'MANUAL_REPAYMENT', initiatedBy: req.admin._id });
    await audit(req, 'refund_initiated', true, { orderId: String(order._id), refundId: String(refund._id), amount });
    return res.status(201).json({ data: refund });
  } catch (error) { return next(error); }
});

router.patch('/refunds/:id', requireAdminPermission('refunds.manage'), async (req, res, next) => {
  try {
    const state = String(req.body?.state || '');
    if (!['REFUND_PROCESSING', 'REFUNDED', 'REFUND_FAILED'].includes(state)) return res.status(400).json({ code: 'validation_error', message: 'Invalid refund state.' });
    const refund = await Refund.findOne({ _id: req.params.id, state: { $in: ['REFUND_PENDING', 'REFUND_PROCESSING'] } });
    if (!refund) return res.status(409).json({ code: 'conflict', message: 'Refund is already completed or unavailable.' });
    if (state === 'REFUNDED' && !String(req.body?.reference || '').trim()) return res.status(400).json({ code: 'validation_error', message: 'A real repayment or reversal reference is required to mark a refund completed.' });
    refund.state = state;
    refund.reference = String(req.body?.reference || '').trim().slice(0, 160);
    if (state === 'REFUNDED') { refund.completedBy = req.admin._id; refund.completedAt = new Date(); await Order.findByIdAndUpdate(refund.orderId, { $set: { paymentStatus: 'refunded', paymentLifecycle: 'REFUNDED', updatedAt: new Date() } }); }
    await refund.save();
    await audit(req, 'refund_state_updated', true, { refundId: req.params.id, state });
    return res.json({ data: refund });
  } catch (error) { return next(error); }
});

module.exports = router;
