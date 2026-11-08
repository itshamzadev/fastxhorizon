const express = require('express');
const mongoose = require('mongoose');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const multer = require('multer');
const Product = require('../models/product');
const ArrangementRequest = require('../models/arrangementRequest');

const router = express.Router();
const clean = (value, max = 300) => String(value || '').trim().slice(0, max);
const nextNumber = () => `FXH-ARR-${Date.now()}-${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`;
const token = () => require('crypto').randomBytes(24).toString('hex');
const evidenceRoot = path.resolve(process.env.PRIVATE_EVIDENCE_STORAGE_ROOT || path.join(process.cwd(), 'data', 'private-payment-evidence'));
const evidenceMaxBytes = Math.max(1, Number(process.env.PRIVATE_EVIDENCE_MAX_BYTES || 5 * 1024 * 1024));
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: evidenceMaxBytes, files: 1 }, fileFilter: (req, file, callback) => callback(null, ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'].includes(file.mimetype)) });
const detectFile = buffer => {
  if (buffer.length >= 8 && buffer.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) return 'image/png';
  if (buffer.length >= 3 && buffer.subarray(0, 3).equals(Buffer.from([255, 216, 255]))) return 'image/jpeg';
  if (buffer.length >= 12 && buffer.subarray(0, 4).toString() === 'RIFF' && buffer.subarray(8, 12).toString() === 'WEBP') return 'image/webp';
  if (buffer.length >= 5 && buffer.subarray(0, 5).toString() === '%PDF-') return 'application/pdf';
  return null;
};
const safeFileName = mime => ({ 'image/png': '.png', 'image/jpeg': '.jpg', 'image/webp': '.webp', 'application/pdf': '.pdf' }[mime]);

router.post('/', async (req, res) => {
  try {
    const { productId, variantId, quantity, customer, notes } = req.body || {};
    if (!mongoose.isValidObjectId(productId)) return res.status(400).json({ message: 'A valid product is required.' });
    const qty = Number(quantity);
    if (!Number.isInteger(qty) || qty < 1 || qty > 20) return res.status(400).json({ message: 'Quantity must be a whole number from 1 to 20.' });
    const product = await Product.findById(productId).lean();
    if (!product || product.status === 'archived') return res.status(404).json({ message: 'Product not found.' });
    const supplierAvailable = product.inventoryMode === 'SUPPLIER_BASED' && product.availabilityStatus === 'AVAILABLE_TO_ORDER';
    const numericUnavailable = product.inventoryMode === 'NUMERIC' && Number(product.stock) <= 0;
    if (!product.allowArrangeOnRequest || !(numericUnavailable || supplierAvailable)) return res.status(409).json({ message: 'This product is not currently available for arrangement requests.' });
    const customerData = customer || {};
    const required = ['name', 'phone', 'city', 'address'];
    if (required.some(field => !clean(customerData[field], 180))) return res.status(400).json({ message: 'Name, mobile number, city and address are required.' });
    const request = await ArrangementRequest.create({
      requestNumber: nextNumber(), trackingToken: token(), productId, variantId: mongoose.isValidObjectId(variantId) ? variantId : null, quantity: qty,
      customerId: req.user?._id || req.user?.id || null,
      customer: { name: clean(customerData.name, 120), phone: clean(customerData.phone, 40), city: clean(customerData.city, 100), address: clean(customerData.address, 300), notes: clean(notes || customerData.notes, 500) },
      statusHistory: [{ status: 'PENDING_REVIEW', note: 'Arrangement request received.' }],
    });
    return res.status(201).json({ data: { id: String(request._id), requestNumber: request.requestNumber, trackingToken: request.trackingToken, status: request.status } });
  } catch (error) {
    return res.status(500).json({ message: 'Unable to submit arrangement request.' });
  }
});

router.get('/:requestNumber', async (req, res) => {
  const trackingToken = clean(req.query.token, 100);
  if (!trackingToken) return res.status(401).json({ message: 'A private tracking link is required.' });
  const request = await ArrangementRequest.findOne({ requestNumber: clean(req.params.requestNumber, 80), trackingToken }).select('+trackingToken').populate('productId', 'name image price brand fabricMaterial').lean();
  if (!request) return res.status(404).json({ message: 'Arrangement request not found.' });
  return res.json({ data: { requestNumber: request.requestNumber, product: request.productId, quantity: request.quantity, status: request.status, agreedUnitPrice: request.agreedUnitPrice, subtotal: request.subtotal, advanceAmount: request.advanceAmount, remainingAmount: request.remainingAmount, deliveryCharge: request.deliveryCharge, estimatedWindow: request.estimatedWindow, paymentState: request.payment?.state || 'NOT_REQUIRED', statusHistory: request.statusHistory } });
});

router.post('/:requestNumber/advance/evidence', (req, res, next) => upload.single('evidence')(req, res, error => {
  if (error) return res.status(error.code === 'LIMIT_FILE_SIZE' ? 413 : 400).json({ message: 'Only one PNG, JPEG, WebP or PDF evidence file up to the configured limit is accepted.' });
  return next();
}), async (req, res) => {
  const trackingToken = clean(req.body?.trackingToken, 100);
  const request = await ArrangementRequest.findOne({ requestNumber: clean(req.params.requestNumber, 80), trackingToken }).select('+payment.evidenceFiles');
  if (!request) return res.status(404).json({ message: 'Arrangement request not found.' });
  if (request.status !== 'ADVANCE_PENDING' || !request.advanceAmount) return res.status(409).json({ message: 'Advance payment is not currently requested.' });
  const reference = clean(req.body?.reference, 100);
  if (!reference || !req.file) return res.status(400).json({ message: 'Payment reference and a secure evidence file are required.' });
  if (request.payment?.state === 'PENDING_VERIFICATION' || request.payment?.state === 'VERIFIED') return res.status(409).json({ message: 'A payment submission already exists for this request.' });
  const actualMime = detectFile(req.file.buffer);
  if (!actualMime || actualMime !== req.file.mimetype) return res.status(400).json({ message: 'The file content does not match its declared type.' });
  const fileId = crypto.randomUUID();
  const storageName = `${fileId}${safeFileName(actualMime)}`;
  const target = path.join(evidenceRoot, storageName);
  try {
    await fs.promises.mkdir(evidenceRoot, { recursive: true, mode: 0o700 });
    await fs.promises.writeFile(target, req.file.buffer, { flag: 'wx', mode: 0o600 });
    request.payment = { reference, state: 'PENDING_VERIFICATION', submittedAt: new Date() };
    request.payment.evidenceFiles = [{ fileId, originalName: path.basename(req.file.originalname).slice(0, 160), mimeType: actualMime, size: req.file.size, storageName }];
    request.status = 'ADVANCE_SUBMITTED';
    request.statusHistory.push({ status: 'ADVANCE_SUBMITTED', note: 'Secure advance payment evidence uploaded; pending administrator verification.' });
    await request.save();
  } catch (error) {
    await fs.promises.rm(target, { force: true }).catch(() => {});
    return res.status(500).json({ message: 'Payment evidence could not be stored securely.' });
  }
  return res.status(201).json({ data: { requestNumber: request.requestNumber, status: request.status, paymentState: request.payment.state, fileId } });
});

router.post('/:requestNumber/advance', async (req, res) => res.status(400).json({ message: 'Secure evidence upload is required. Use the multipart evidence endpoint.' }));

router.get('/:requestNumber/evidence/:fileId', async (req, res) => {
  const trackingToken = clean(req.query.token, 100);
  const request = await ArrangementRequest.findOne({ requestNumber: clean(req.params.requestNumber, 80), trackingToken }).select('+trackingToken +payment.evidenceFiles').lean();
  const file = request?.payment?.evidenceFiles?.find(item => item.fileId === req.params.fileId);
  if (!file) return res.status(404).json({ message: 'Evidence not found.' });
  const target = path.resolve(evidenceRoot, file.storageName);
  if (!target.startsWith(`${evidenceRoot}${path.sep}`)) return res.status(400).json({ message: 'Invalid evidence path.' });
  try { await fs.promises.access(target, fs.constants.R_OK); } catch { return res.status(404).json({ message: 'Evidence file is unavailable.' }); }
  res.setHeader('Content-Type', file.mimeType);
  res.setHeader('Content-Disposition', 'attachment; filename="payment-evidence"');
  return res.sendFile(target);
});

module.exports = router;
