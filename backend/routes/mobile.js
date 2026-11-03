const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const express = require('express');
const { check, validationResult } = require('express-validator');
const Admin = require('../models/admin');
const AdminDeviceSession = require('../models/adminDeviceSession');
const AdminDevice = require('../models/adminDevice');
const AdminNotification = require('../models/adminNotification');
const Order = require('../models/order');
const AdminAuditLog = require('../models/adminAuditLog');
const { publicAdmin, decryptSecret, verifyTotp } = require('../services/adminSecurity');
const { hashToken, hasAdminPermission } = require('../middleware/adminAuth');
const { requireMobileSession, requireMobilePermission } = require('../middleware/mobileAuth');

const router = express.Router();
const accessSecret = () => process.env.MOBILE_ACCESS_TOKEN_SECRET || process.env.JWT_SECRET;
const refreshDays = 30;
const accessMinutes = 15;

function issueAccess(admin, sid) { return jwt.sign({ sub: String(admin._id), sid: String(sid), type: 'mobile_access' }, accessSecret(), { expiresIn: `${accessMinutes}m` }); }
function orderStatus(order) {
  if (order.statusIndex < 0) return 'cancelled';
  const code = order.statusHistory?.[Number(order.statusIndex || 0)]?.code;
  if (code === 'DELIVERED' || code === 'DELIVERY_CONFIRMED' || Number(order.statusIndex) >= 9) return 'delivered';
  if (['IN_TRANSIT', 'OUT_FOR_DELIVERY', 'HANDOFF_TO_CARRIER'].includes(code) || Number(order.statusIndex) >= 5) return 'shipped';
  return Number(order.statusIndex) > 0 ? 'processing' : 'pending';
}
function orderView(order) { return { id: String(order._id), number: order.orderNumber, customerName: order.name, customerEmail: order.email, phone: order.phone || '', shippingAddress: order.shippingAddress || '', items: (order.items || []).map((item) => ({ productId: String(item.productId), name: item.name, quantity: item.quantity, unitPrice: item.price, image: item.image || null })), total: order.total, currency: order.currency || 'PKR', status: orderStatus(order), paymentMethod: order.paymentMethod, paymentStatus: order.paymentStatus, paymentLifecycle: order.paymentLifecycle, availabilityStatus: order.availabilityStatus, placedAt: order.createdAt, updatedAt: order.updatedAt, statusHistory: order.statusHistory || [] }; }
function issueRefresh() { return crypto.randomBytes(48).toString('base64url'); }
function validationError(req, res) { const errors = validationResult(req); if (!errors.isEmpty()) { res.status(422).json({ code: 'validation_error', errors: errors.array() }); return true; } return false; }

router.post('/auth/login', [check('email').isEmail(), check('password').isString().isLength({ min: 8, max: 200 }), check('deviceId').isString().isLength({ min: 8, max: 160 }), check('mfaCode').optional().isString(), check('recoveryCode').optional().isString()], async (req, res) => {
  if (validationError(req, res)) return;
  if (!accessSecret()) return res.status(503).json({ code: 'mobile_auth_unconfigured', message: 'Mobile authentication is not configured.' });
  const email = String(req.body.email).trim().toLowerCase();
  try {
    const admin = await Admin.findOne({ email }).select('+passwordHash +mfaSecretEncrypted +recoveryCodeHashes');
    if (!admin || admin.status !== 'active' || !(await bcrypt.compare(req.body.password, admin.passwordHash))) return res.status(401).json({ code: 'invalid_credentials', message: 'Invalid administrator credentials.' });
    if (!admin.mfaSecretEncrypted) return res.status(403).json({ code: 'mfa_required', message: 'Enroll MFA on the admin panel before using the mobile app.' });
    let mfaOk = false;
    if (req.body.mfaCode) { try { mfaOk = verifyTotp(decryptSecret(admin.mfaSecretEncrypted), String(req.body.mfaCode)); } catch {} }
    if (!mfaOk && req.body.recoveryCode) { const candidate = String(req.body.recoveryCode).trim().toUpperCase(); for (const hash of admin.recoveryCodeHashes || []) if (await bcrypt.compare(candidate, hash)) { mfaOk = true; break; } }
    if (!mfaOk) return res.status(401).json({ code: 'invalid_mfa', message: 'A valid MFA code or recovery code is required.' });
    const refreshToken = issueRefresh();
    const session = await AdminDeviceSession.findOneAndUpdate({ adminId: admin._id, deviceId: req.body.deviceId }, { $set: { deviceName: String(req.body.deviceName || 'Android device').slice(0, 160), refreshTokenHash: hashToken(refreshToken), expiresAt: new Date(Date.now() + refreshDays * 86400000), lastUsedAt: new Date(), revokedAt: null } }, { upsert: true, new: true, setDefaultsOnInsert: true });
    await AdminAuditLog.create({ adminId: admin._id, email: admin.email, action: 'mobile_login', success: true, metadata: { deviceId: req.body.deviceId } });
    return res.json({ accessToken: issueAccess(admin, session._id), refreshToken, expiresIn: accessMinutes * 60, user: publicAdmin(admin) });
  } catch (error) { console.error('Mobile login failed:', error.message); return res.status(500).json({ code: 'internal_error', message: 'Unable to sign in.' }); }
});

router.post('/auth/refresh', [check('refreshToken').isString().isLength({ min: 20 }), check('deviceId').isString().isLength({ min: 8, max: 160 })], async (req, res) => {
  if (validationError(req, res)) return;
  try {
    const session = await AdminDeviceSession.findOne({ deviceId: req.body.deviceId, refreshTokenHash: hashToken(req.body.refreshToken), revokedAt: null, expiresAt: { $gt: new Date() } });
    if (!session) return res.status(401).json({ code: 'refresh_invalid', message: 'Mobile session expired.' });
    const admin = await Admin.findById(session.adminId).select('+passwordHash');
    if (!admin || admin.status !== 'active') return res.status(401).json({ code: 'refresh_invalid', message: 'Administrator session is no longer active.' });
    const refreshToken = issueRefresh(); session.refreshTokenHash = hashToken(refreshToken); session.lastUsedAt = new Date(); session.expiresAt = new Date(Date.now() + refreshDays * 86400000); await session.save();
    return res.json({ accessToken: issueAccess(admin, session._id), refreshToken, expiresIn: accessMinutes * 60, user: publicAdmin(admin) });
  } catch (error) { return res.status(500).json({ code: 'internal_error', message: 'Unable to refresh mobile session.' }); }
});

router.post('/auth/logout', async (req, res) => { const token = String(req.body?.refreshToken || ''); if (token) await AdminDeviceSession.updateOne({ refreshTokenHash: hashToken(token) }, { $set: { revokedAt: new Date() } }); return res.status(204).send(); });
router.get('/auth/me', requireMobileSession, (req, res) => res.json({ user: publicAdmin(req.admin) }));

router.get('/orders', requireMobileSession, requireMobilePermission('orders.read'), async (req, res, next) => { try { const page = Math.max(1, Number(req.query.page) || 1); const pageSize = Math.min(50, Math.max(1, Number(req.query.pageSize) || 20)); const search = String(req.query.search || '').trim().slice(0, 80); const filter = search ? { $or: [{ orderNumber: { $regex: search, $options: 'i' } }, { name: { $regex: search, $options: 'i' } }, { email: { $regex: search, $options: 'i' } }] } : {}; const [rows, total] = await Promise.all([Order.find(filter).sort({ createdAt: -1 }).skip((page - 1) * pageSize).limit(pageSize).lean(), Order.countDocuments(filter)]); return res.json({ data: rows.map(orderView), meta: { page, pageSize, total, totalPages: Math.ceil(total / pageSize) } }); } catch (error) { return next(error); } });
router.get('/orders/stats', requireMobileSession, requireMobilePermission('orders.read'), async (req, res, next) => { try { const [total, pending, processing, shipped, delivered, cancelled] = await Promise.all([Order.countDocuments(), Order.countDocuments({ statusIndex: 0 }), Order.countDocuments({ statusIndex: { $gt: 0, $lt: 5 } }), Order.countDocuments({ statusIndex: { $gte: 5, $lt: 9 } }), Order.countDocuments({ statusIndex: { $gte: 9 } }), Order.countDocuments({ statusIndex: -1 })]); return res.json({ data: { total, pending, processing, shipped, delivered, cancelled } }); } catch (error) { return next(error); } });
router.get('/orders/:id', requireMobileSession, requireMobilePermission('orders.read'), async (req, res, next) => { try { const order = await Order.findById(req.params.id).lean(); return order ? res.json({ data: orderView(order) }) : res.status(404).json({ code: 'not_found', message: 'Order not found.' }); } catch (error) { return next(error); } });
router.patch('/orders/:id', requireMobileSession, requireMobilePermission('orders.manage'), async (req, res, next) => { try { const allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled']; if (!allowed.includes(req.body?.status)) return res.status(422).json({ code: 'validation_error', message: 'Invalid order status.' }); const order = await Order.findById(req.params.id); if (!order) return res.status(404).json({ code: 'not_found', message: 'Order not found.' }); const current = orderStatus(order); const ranks = { pending: 0, processing: 1, shipped: 2, delivered: 3 }; if (req.body.status !== 'cancelled' && current !== 'cancelled' && ranks[req.body.status] < ranks[current]) return res.status(409).json({ code: 'conflict', message: 'Order status can only move forward.' }); order.statusIndex = req.body.status === 'cancelled' ? -1 : req.body.status === 'pending' ? 0 : req.body.status === 'processing' ? Math.max(order.statusIndex, 1) : req.body.status === 'shipped' ? Math.max(order.statusIndex, 6) : Math.max(order.statusIndex, 9); await order.save(); return res.json({ data: orderView(order) }); } catch (error) { return next(error); } });

router.post('/devices/register', requireMobileSession, [check('deviceId').isString().isLength({ min: 8, max: 160 }), check('fcmToken').isString().isLength({ min: 20 })], async (req, res) => { if (validationError(req, res)) return; const device = await AdminDevice.findOneAndUpdate({ adminId: req.admin._id, deviceId: req.body.deviceId }, { $set: { fcmToken: req.body.fcmToken, deviceName: String(req.body.deviceName || 'Android device').slice(0, 160), platform: 'android', enabled: true, lastSeenAt: new Date() } }, { upsert: true, new: true, setDefaultsOnInsert: true }); return res.json({ data: { id: String(device._id), enabled: device.enabled } }); });
router.delete('/devices/:deviceId', requireMobileSession, async (req, res) => { await AdminDevice.deleteOne({ adminId: req.admin._id, deviceId: req.params.deviceId }); return res.status(204).send(); });
router.get('/notifications', requireMobileSession, async (req, res) => { const items = await AdminNotification.find({ adminId: req.admin._id }).sort({ createdAt: -1 }).limit(100).lean(); return res.json({ data: items.map((item) => ({ id: String(item._id), type: item.type, title: item.title, body: item.body, orderId: item.orderId ? String(item.orderId) : null, data: item.data, read: Boolean(item.readAt), createdAt: item.createdAt })) }); });
router.post('/notifications/:id/read', requireMobileSession, async (req, res) => { await AdminNotification.updateOne({ _id: req.params.id, adminId: req.admin._id }, { $set: { readAt: new Date() } }); return res.status(204).send(); });

module.exports = router;
