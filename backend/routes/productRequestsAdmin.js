const express = require('express');
const fs = require('fs');
const path = require('path');
const ArrangementRequest = require('../models/arrangementRequest');
const AdminAuditLog = require('../models/adminAuditLog');
const { requireAdminSession, requireAdminPermission } = require('../middleware/adminAuth');
const { calculateArrangementAmounts } = require('../utils/arrangementFinance');
const { canTransition } = require('../utils/arrangementWorkflow');

const router = express.Router();
router.use(requireAdminSession, requireAdminPermission('manage_inventory'));
const evidenceRoot = path.resolve(process.env.PRIVATE_EVIDENCE_STORAGE_ROOT || path.join(process.cwd(), 'data', 'private-payment-evidence'));
const view = item => ({ ...item, id: String(item._id), payment: item.payment ? { ...item.payment, evidenceFiles: undefined } : item.payment });
const audit = (req, action, request, metadata = {}) => AdminAuditLog.create({ adminId: req.admin._id, email: req.admin.email, action, success: true, ip: req.ip, metadata: { requestId: String(request._id), requestNumber: request.requestNumber, ...metadata } });

router.get('/', async (req, res, next) => {
  try {
    const filter = {};
    if (req.query.status) filter.status = String(req.query.status);
    if (req.query.paymentStatus) filter['payment.state'] = String(req.query.paymentStatus);
    if (req.query.from || req.query.to) filter.createdAt = { ...(req.query.from ? { $gte: new Date(req.query.from) } : {}), ...(req.query.to ? { $lte: new Date(`${req.query.to}T23:59:59.999Z`) } : {}) };
    if (req.query.search) { const search = String(req.query.search).trim().slice(0, 80); filter.$or = [{ requestNumber: { $regex: search, $options: 'i' } }, { 'customer.name': { $regex: search, $options: 'i' } }, { 'customer.phone': { $regex: search, $options: 'i' } }]; }
    const items = await ArrangementRequest.find(filter).populate('productId', 'name sku image images price').sort({ createdAt: -1 }).limit(200).lean();
    return res.json({ data: items.map(view) });
  } catch (error) { return next(error); }
});

router.get('/:id', async (req, res, next) => {
  try { const item = await ArrangementRequest.findById(req.params.id).populate('productId', 'name sku image images price variants').lean(); if (!item) return res.status(404).json({ message: 'Product request not found.' }); return res.json({ data: view(item) }); } catch (error) { return next(error); }
});

router.get('/:id/evidence/:fileId', async (req, res, next) => {
  try {
    const item = await ArrangementRequest.findById(req.params.id).select('+payment.evidenceFiles').lean();
    const file = item?.payment?.evidenceFiles?.find(entry => entry.fileId === req.params.fileId);
    if (!file) return res.status(404).json({ message: 'Evidence not found.' });
    const target = path.resolve(evidenceRoot, file.storageName);
    if (!target.startsWith(`${evidenceRoot}${path.sep}`)) return res.status(400).json({ message: 'Invalid evidence path.' });
    await fs.promises.access(target, fs.constants.R_OK);
    res.setHeader('Content-Type', file.mimeType); res.setHeader('Content-Disposition', 'inline; filename="payment-evidence"'); return res.sendFile(target);
  } catch (error) { if (error.code === 'ENOENT') return res.status(404).json({ message: 'Evidence file is unavailable.' }); return next(error); }
});

router.patch('/:id/workflow', async (req, res, next) => {
  try {
    const request = await ArrangementRequest.findById(req.params.id);
    if (!request) return res.status(404).json({ message: 'Product request not found.' });
    const nextStatus = String(req.body.status || '').trim();
    if (!ArrangementRequest.STATUSES.includes(nextStatus) || !canTransition(request.status, nextStatus)) return res.status(409).json({ message: `Cannot move ${request.status} to ${nextStatus}.` });
    if (['AVAILABLE_AWAITING_ADVANCE', 'AVAILABLE_CONFIRMED'].includes(nextStatus)) {
      const price = Number(req.body.confirmedUnitPrice);
      const amounts = calculateArrangementAmounts(price, request.quantity);
      if (!amounts) return res.status(400).json({ message: 'A valid confirmed selling price is required.' });
      request.agreedUnitPrice = price; request.subtotal = amounts.subtotal; request.advanceAmount = amounts.advanceAmount; request.remainingAmount = amounts.remainingAmount;
      request.payment.state = 'AWAITING_ADVANCE_PAYMENT';
    }
    if (req.body.expectedDeliveryDate) { const date = new Date(req.body.expectedDeliveryDate); if (Number.isNaN(date.valueOf())) return res.status(400).json({ message: 'Expected delivery date is invalid.' }); request.expectedDeliveryDate = date; }
    if (req.body.allowCashOnDelivery !== undefined) request.allowCashOnDelivery = req.body.allowCashOnDelivery === true;
    request.supplierAvailabilityNote = String(req.body.supplierAvailabilityNote || request.supplierAvailabilityNote || '').slice(0, 1000);
    request.supplierOrderReference = String(req.body.supplierOrderReference || request.supplierOrderReference || '').slice(0, 160);
    request.deliveryProgress = String(req.body.deliveryProgress || request.deliveryProgress || '').slice(0, 500);
    request.adminNotes = String(req.body.adminNotes || request.adminNotes || '').slice(0, 1000);
    request.status = nextStatus; request.statusHistory.push({ status: nextStatus, note: String(req.body.note || '').slice(0, 500), changedBy: req.admin._id });
    await request.save(); await audit(req, `product_request_status_${nextStatus.toLowerCase()}`, request, { status: nextStatus });
    return res.json({ data: view(request.toObject()) });
  } catch (error) { return next(error); }
});

router.post('/:id/payment/decision', async (req, res, next) => {
  try {
    const decision = String(req.body.state || '').trim();
    if (!['ADVANCE_VERIFIED', 'PAYMENT_REJECTED'].includes(decision)) return res.status(400).json({ message: 'Decision must be ADVANCE_VERIFIED or PAYMENT_REJECTED.' });
    const request = await ArrangementRequest.findOneAndUpdate({ _id: req.params.id, 'payment.state': { $in: ['PAYMENT_SUBMITTED', 'PENDING_VERIFICATION'] } }, { $set: decision === 'ADVANCE_VERIFIED' ? { 'payment.state': 'ADVANCE_VERIFIED', 'payment.verifiedAt': new Date(), 'payment.verifiedBy': req.admin._id, status: 'ADVANCE_VERIFIED', advancePaid: req.body.amount === undefined ? undefined : Number(req.body.amount) } : { 'payment.state': 'PAYMENT_REJECTED', 'payment.rejectedAt': new Date(), 'payment.rejectionReason': String(req.body.reason || '').slice(0, 500), status: 'AVAILABLE_AWAITING_ADVANCE' }, $push: { statusHistory: { status: decision === 'ADVANCE_VERIFIED' ? 'ADVANCE_VERIFIED' : 'AVAILABLE_AWAITING_ADVANCE', note: decision === 'ADVANCE_VERIFIED' ? 'Advance payment verified by administrator.' : 'Payment proof rejected; customer may submit a new proof.', changedBy: req.admin._id } } }, { new: true, runValidators: true });
    if (!request) return res.status(409).json({ message: 'Payment is no longer awaiting verification.' });
    if (decision === 'ADVANCE_VERIFIED') { request.advancePaid = request.advanceAmount; await request.save(); }
    await audit(req, `product_request_payment_${decision.toLowerCase()}`, request); return res.json({ data: view(request.toObject()) });
  } catch (error) { return next(error); }
});

router.post('/:id/refund', async (req, res, next) => {
  try {
    const reference = String(req.body.reference || '').trim(); const method = String(req.body.method || '').trim();
    if (!reference || !method) return res.status(400).json({ message: 'Actual refund reference and method are required.' });
    const request = await ArrangementRequest.findOneAndUpdate({ _id: req.params.id, status: 'REFUND_PENDING', 'refund.state': { $ne: 'COMPLETED' } }, { $set: { status: 'REFUNDED', 'refund.state': 'COMPLETED', 'refund.amount': req.body.amount || undefined, 'refund.reference': reference, 'refund.method': method, 'refund.processedAt': new Date(), 'refund.processedBy': req.admin._id, 'payment.state': 'REFUNDED' }, $push: { statusHistory: { status: 'REFUNDED', note: 'Refund recorded after payment processor confirmation.', changedBy: req.admin._id } } }, { new: true });
    if (!request) return res.status(409).json({ message: 'Request is not awaiting a refund or was already refunded.' });
    await audit(req, 'product_request_refund_completed', request, { reference, method }); return res.json({ data: view(request.toObject()) });
  } catch (error) { return next(error); }
});

module.exports = router;
