const express = require('express');
const crypto = require('crypto');
const PaymentConfiguration = require('../models/paymentConfiguration');
const PaymentSubmission = require('../models/paymentSubmission');
const Order = require('../models/order');
const AdminAuditLog = require('../models/adminAuditLog');
const { requireAdminSession, requireAdminPermission } = require('../middleware/adminAuth');

const router = express.Router();
const STATES = new Set(['PENDING_SUBMISSION', 'PENDING_VERIFICATION', 'VERIFIED', 'REJECTED', 'CANCELLED']);
const DEFAULT_MIMES = new Set(['image/png', 'image/jpeg', 'image/webp', 'application/pdf']);

async function getConfig() {
  return PaymentConfiguration.findOneAndUpdate({ key: 'v1' }, { $setOnInsert: { key: 'v1', automaticEnabled: false, manualEnabled: false, codEnabled: false, ordersEnabled: false, manualMethods: [] } }, { upsert: true, new: true, setDefaultsOnInsert: true }).lean();
}

function publicMethod(method) {
  return { id: String(method._id), key: method.key, name: method.name, enabled: method.enabled, instructions: method.instructions, recipientDetails: method.recipientDetails, order: method.order, version: method.version, requiredAnyOf: method.requiredAnyOf || [], fields: (method.fields || []).filter(field => field.enabled).sort((a, b) => a.order - b.order).map(({ id, label, type, required, placeholder, helpText, order, options, acceptedMimeTypes, maxFileSizeBytes }) => ({ id, label, type, required, placeholder, helpText, order, options, acceptedMimeTypes, maxFileSizeBytes })) };
}

function publicConfig(config) {
  return { ordersEnabled: config.ordersEnabled === true, orderingDisabledMessage: config.orderingDisabledMessage || 'Online ordering is temporarily unavailable.', automatic: { enabled: Boolean(config.automaticEnabled) }, manual: { enabled: Boolean(config.manualEnabled), methods: (config.manualMethods || []).filter(method => method.enabled).sort((a, b) => a.order - b.order).map(publicMethod) }, cod: { enabled: Boolean(config.codEnabled) } };
}

function validateFields(method, values, evidence) {
  const fields = (method.fields || []).filter(field => field.enabled);
  const allowed = new Set(fields.map(field => field.id));
  for (const key of Object.keys(values || {})) if (!allowed.has(key)) return 'Unexpected payment form field.';
  for (const field of fields) {
    const value = values?.[field.id];
    if (field.required && (value === undefined || value === null || value === '' || (field.type === 'checkbox' && value !== true))) return `${field.label} is required.`;
    if (value === undefined || value === null || value === '') continue;
    if (field.type === 'number' && !Number.isFinite(Number(value))) return `${field.label} must be a number.`;
    if (['select', 'radio'].includes(field.type) && !field.options.includes(String(value))) return `${field.label} has an invalid option.`;
    if (field.type === 'file' && field.required && !evidence?.some(file => file.fieldId === field.id)) return `${field.label} is required.`;
  }
  for (const group of method.requiredAnyOf || []) {
    if (!Array.isArray(group) || !group.length) continue;
    const hasValue = group.some(id => {
      const field = fields.find(item => item.id === id);
      if (field?.type === 'file') return evidence?.some(file => file.fieldId === id);
      const value = values?.[id];
      return value !== undefined && value !== null && value !== '';
    });
    if (!hasValue) return `${group.join(' or ')} is required.`;
  }
  return null;
}

function validateEvidence(evidence, fields) {
  if (!Array.isArray(evidence)) return [];
  if (evidence.length > 5) throw new Error('Too many evidence files.');
  return evidence.map(file => {
    const mimeType = String(file?.mimeType || '');
    const data = String(file?.data || '');
    const fieldId = String(file?.fieldId || '');
    const field = fields.find(item => item.id === fieldId && item.type === 'file' && item.enabled);
    if (!field || !DEFAULT_MIMES.has(mimeType) || !data.startsWith(`data:${mimeType};base64,`)) throw new Error('Invalid payment evidence file.');
    const encoded = data.split(',')[1] || '';
    const size = Math.floor((encoded.length * 3) / 4);
    const max = Math.min(5242880, Number(field.maxFileSizeBytes) || 2097152);
    if (size > max) throw new Error('Payment evidence file is too large.');
    return { fieldId, name: String(file.name || 'evidence').slice(0, 120), mimeType, size, data };
  });
}

router.get('/config', async (req, res, next) => { try { return res.json({ data: publicConfig(await getConfig()) }); } catch (error) { return next(error); } });

router.post('/submit', async (req, res, next) => {
  try {
    const { orderId, methodKey, values = {}, evidence = [] } = req.body || {};
    const order = await Order.findById(orderId);
    if (!order || order.paymentMethod !== 'manual') return res.status(404).json({ code: 'not_found', message: 'Manual payment order not found.' });
    if (!['pending', 'PENDING_VERIFICATION'].includes(order.paymentStatus) || order.paymentSubmissionId) return res.status(409).json({ code: 'conflict', message: 'This order already has a payment submission.' });
    const config = await getConfig();
    const method = (config.manualMethods || []).find(item => item.key === methodKey && item.enabled);
    if (!config.manualEnabled || !method) return res.status(400).json({ code: 'payment_method_unavailable', message: 'That manual payment method is unavailable.' });
    const safeEvidence = validateEvidence(evidence, method.fields || []);
    const validationError = validateFields(method, values, safeEvidence);
    if (validationError) return res.status(400).json({ code: 'validation_error', message: validationError });
    const reference = Object.entries(values).find(([key]) => /reference|transaction|id/i.test(key))?.[1];
    const referenceFingerprint = reference ? crypto.createHash('sha256').update(String(reference).trim().toLowerCase()).digest('hex') : undefined;
    if (referenceFingerprint && await PaymentSubmission.exists({ referenceFingerprint, state: { $in: ['PENDING_VERIFICATION', 'VERIFIED'] } })) return res.status(409).json({ code: 'duplicate_reference', message: 'This payment reference has already been submitted.' });
    const snapshot = publicMethod(method);
    const submission = await PaymentSubmission.create({ orderId: order._id, orderNumber: order.orderNumber, email: order.email, methodKey, amount: order.total, currency: order.currency || 'PKR', state: 'PENDING_VERIFICATION', methodSnapshot: snapshot, values, evidence: safeEvidence, referenceFingerprint });
    order.paymentSubmissionId = submission._id;
    order.paymentStatus = 'pending';
    order.paymentLifecycle = 'PENDING_PAYMENT_VERIFICATION';
    await order.save();
    return res.status(201).json({ data: { id: String(submission._id), orderNumber: order.orderNumber, state: submission.state, paymentStatus: order.paymentStatus }, message: 'Payment evidence submitted and is pending verification.' });
  } catch (error) { return next(error); }
});

const admin = express.Router();
admin.use(requireAdminSession);
admin.get('/config', requireAdminPermission('payments.read'), async (req, res, next) => { try { return res.json({ data: await getConfig() }); } catch (error) { return next(error); } });
admin.put('/config', requireAdminPermission('payments.manage'), async (req, res, next) => {
  try {
    const body = req.body || {};
    if (typeof body.automaticEnabled !== 'boolean' || typeof body.manualEnabled !== 'boolean' || typeof body.codEnabled !== 'boolean' || !Array.isArray(body.manualMethods) || body.manualMethods.length > 20) return res.status(400).json({ code: 'validation_error', message: 'Invalid payment configuration.' });
    for (const method of body.manualMethods) { if (!/^[a-z0-9][a-z0-9_-]{1,39}$/.test(method.key || '') || !method.name || !Array.isArray(method.fields) || method.fields.length > 30 || (method.requiredAnyOf !== undefined && (!Array.isArray(method.requiredAnyOf) || method.requiredAnyOf.length > 10))) return res.status(400).json({ code: 'validation_error', message: 'Invalid manual payment method or fields.' }); for (const field of method.fields) if (!/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/.test(field.id || '') || !['text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'file'].includes(field.type)) return res.status(400).json({ code: 'validation_error', message: 'Invalid dynamic payment field.' }); for (const group of method.requiredAnyOf || []) if (!Array.isArray(group) || group.length > 10 || group.some(id => !method.fields.some(field => field.id === id))) return res.status(400).json({ code: 'validation_error', message: 'Invalid required-any payment field group.' }); }
    const saved = await PaymentConfiguration.findOneAndUpdate({ key: 'v1' }, { $set: { automaticEnabled: body.automaticEnabled, manualEnabled: body.manualEnabled, codEnabled: body.codEnabled, manualMethods: body.manualMethods } }, { new: true, upsert: true, runValidators: true });
    await AdminAuditLog.create({ adminId: req.admin._id, email: req.admin.email, action: 'payment_configuration_updated', success: true, ip: req.ip, metadata: { methodCount: body.manualMethods.length } });
    return res.json({ data: saved });
  } catch (error) { return next(error); }
});
admin.get('/reviews', requireAdminPermission('payments.read'), async (req, res, next) => { try { const filter = STATES.has(req.query.state) ? { state: req.query.state } : {}; const items = await PaymentSubmission.find(filter).sort({ createdAt: -1 }).limit(100).select('-evidence.data').lean(); return res.json({ data: items }); } catch (error) { return next(error); } });
admin.get('/reviews/:id/evidence/:index', requireAdminPermission('payments.read'), async (req, res, next) => { try { const submission = await PaymentSubmission.findById(req.params.id).select('+evidence.data').lean(); const evidence = submission?.evidence?.[Number(req.params.index)]; if (!evidence || !Number.isInteger(Number(req.params.index))) return res.status(404).json({ code: 'not_found', message: 'Evidence not found.' }); const buffer = Buffer.from(evidence.data.split(',')[1], 'base64'); res.setHeader('Content-Type', evidence.mimeType); return res.send(buffer); } catch (error) { return next(error); } });
admin.post('/reviews/:id/decision', requireAdminPermission('payments.manage'), async (req, res, next) => { try { const state = String(req.body?.state || ''); if (!['VERIFIED', 'REJECTED'].includes(state)) return res.status(400).json({ code: 'validation_error', message: 'Decision must be VERIFIED or REJECTED.' }); const submission = await PaymentSubmission.findOneAndUpdate({ _id: req.params.id, state: 'PENDING_VERIFICATION' }, { $set: { state, rejectionReason: state === 'REJECTED' ? String(req.body.reason || '').trim().slice(0, 500) : '', decidedBy: req.admin._id, decidedAt: new Date() } }, { new: true }); if (!submission) return res.status(409).json({ code: 'conflict', message: 'Payment is no longer awaiting verification.' }); const order = await Order.findOneAndUpdate({ _id: submission.orderId, paymentSubmissionId: submission._id, paymentStatus: 'pending' }, { $set: { paymentStatus: state === 'VERIFIED' ? 'verified' : 'failed', paymentLifecycle: state, updatedAt: new Date() } }, { new: true }); if (!order) return res.status(409).json({ code: 'conflict', message: 'Order payment state has already changed.' }); await AdminAuditLog.create({ adminId: req.admin._id, email: req.admin.email, action: `manual_payment_${state.toLowerCase()}`, success: true, ip: req.ip, metadata: { submissionId: String(submission._id), orderId: String(order._id) } }); return res.json({ data: { id: String(submission._id), state, orderPaymentStatus: order.paymentStatus } }); } catch (error) { return next(error); } });

module.exports = { publicRouter: router, adminRouter: admin };
