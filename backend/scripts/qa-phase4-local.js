require('dotenv').config();
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Admin = require('../models/admin');
const AdminSession = require('../models/adminSession');
const OfflineSale = require('../models/offlineSale');
const InventoryTransaction = require('../models/inventoryTransaction');
const ArrangementRequest = require('../models/arrangementRequest');

const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
if (!/localhost|127\.0\.0\.1|::1/.test(uri) || !/fastxhorizon_local/.test(uri)) throw new Error('QA script refuses non-local or non-fastxhorizon_local MongoDB.');
const base = 'http://127.0.0.1:5000';
const hash = token => crypto.createHash('sha256').update(token).digest('hex');
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const evidenceRoot = path.resolve(process.env.PRIVATE_EVIDENCE_STORAGE_ROOT || path.join(process.cwd(), 'data', 'private-payment-evidence'));
async function call(path, options = {}) { const headers = { ...(options.headers || {}) }; if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json'; const response = await fetch(`${base}${path}`, { ...options, headers }); const text = await response.text(); let body = {}; try { body = text ? JSON.parse(text) : {}; } catch {} return { response, body }; }

(async () => {
  await mongoose.connect(uri);
  const suffix = `qa-${Date.now()}-${crypto.randomBytes(3).toString('hex')}`;
  const product = await Product.create({ name: `QA Phase 4 ${suffix}`, description: 'Temporary QA fixture', price: 5000, compareAtPrice: 6000, category: 'Clothing', image: '/media/products/qa.png', images: ['/media/products/qa.png'], sku: `QA-${suffix}`.toUpperCase(), stock: 1, inventoryMode: 'NUMERIC', availabilityStatus: 'IN_STOCK', status: 'active', allowArrangeOnRequest: true });
  const concurrentProduct = await Product.create({ name: `QA Concurrent ${suffix}`, description: 'Temporary QA fixture', price: 1000, category: 'Clothing', image: '/media/products/qa.png', sku: `QAC-${suffix}`.toUpperCase(), stock: 1, inventoryMode: 'NUMERIC', availabilityStatus: 'IN_STOCK', status: 'active' });
  const variantProduct = await Product.create({ name: `QA Variant ${suffix}`, description: 'Temporary QA fixture', price: 1000, category: 'Clothing', image: '/media/products/qa.png', sku: `QAV-${suffix}`.toUpperCase(), stock: 9, inventoryMode: 'NUMERIC', availabilityStatus: 'IN_STOCK', status: 'active', variants: [{ key: 'Black', options: { color: 'Black' }, sku: `QAV-V-${suffix}`.toUpperCase(), price: 1000, stock: 1, inventoryMode: 'NUMERIC', availabilityStatus: 'IN_STOCK' }] });
  const admin = await Admin.create({ name: `QA ${suffix}`, email: `${suffix}@example.invalid`, passwordHash: 'not-used', role: 'super_admin', permissions: ['manage_inventory', 'reviews.manage'], status: 'active' });
  const sessionToken = crypto.randomBytes(32).toString('hex');
  const session = await AdminSession.create({ tokenHash: hash(sessionToken), adminId: admin._id, state: 'authenticated', expiresAt: new Date(Date.now() + 60 * 60 * 1000) });
  const cookie = `fxh_admin_session=${sessionToken}`;
  let arrangementId = null;
  let evidenceStorageName = null;
  try {
    let result = await call('/api/admin/inventory/offline-sales', { method: 'POST', headers: { Cookie: cookie }, body: JSON.stringify({ productId: product._id, quantity: 1, sellingPrice: 5000, eventId: `event-${suffix}` }) });
    assert(result.response.status === 201, `offline sale failed: ${result.response.status}`);
    result = await call('/api/admin/inventory/offline-sales', { method: 'POST', headers: { Cookie: cookie }, body: JSON.stringify({ productId: product._id, quantity: 1, sellingPrice: 5000, eventId: `event-${suffix}` }) });
    assert(result.response.status === 200 && result.body.data.idempotent === true, 'offline sale retry was not idempotent');
    result = await call('/api/admin/inventory/offline-sales', { method: 'POST', headers: { Cookie: cookie }, body: JSON.stringify({ productId: product._id, quantity: 1, sellingPrice: 5000, eventId: `event-negative-${suffix}` }) });
    assert(result.response.status === 409, 'negative stock sale was not rejected');
    assert((await Product.findById(product._id)).stock === 0, 'stock did not reach zero');
    result = await call(`/api/admin/inventory/products/${product._id}/stock`, { method: 'PATCH', headers: { Cookie: cookie }, body: JSON.stringify({ action: 'ADJUST', quantity: 1, reason: 'QA restore' }) });
    assert(result.response.status === 200, `restore failed: ${result.response.status}`); assert((await Product.findById(product._id)).stock === 1, 'restore did not increase stock');
    await Product.updateOne({ _id: product._id }, { $set: { stock: 0, availabilityStatus: 'OUT_OF_STOCK' } });
    result = await call('/api/arrangements', { method: 'POST', body: JSON.stringify({ productId: product._id, quantity: 1, customer: { name: 'QA Customer', phone: '03000000000', city: 'Lahore', address: 'QA address' } }) });
    assert(result.response.status === 201, `arrangement request failed: ${result.response.status} ${JSON.stringify(result.body)}`); const requestNumber = result.body.data.requestNumber; const trackingToken = result.body.data.trackingToken; const arrangement = await ArrangementRequest.findOne({ requestNumber }); arrangementId = arrangement._id;
    result = await call(`/api/admin/inventory/arrangements/${arrangement._id}`, { method: 'PATCH', headers: { Cookie: cookie }, body: JSON.stringify({ status: 'AVAILABLE_CONFIRMED', agreedUnitPrice: 5000 }) }); assert(result.response.status === 200, 'availability confirmation failed');
    result = await call(`/api/admin/inventory/arrangements/${arrangement._id}`, { method: 'PATCH', headers: { Cookie: cookie }, body: JSON.stringify({ status: 'ADVANCE_PENDING', agreedUnitPrice: 5000 }) }); assert(result.response.status === 200 && result.body.data.advanceAmount === 3000, '60 percent advance calculation failed');
    const invalidForm = new FormData(); invalidForm.append('trackingToken', trackingToken); invalidForm.append('reference', 'QA-BAD'); invalidForm.append('evidence', new Blob([Buffer.from('not really a pdf')], { type: 'application/pdf' }), 'bad.pdf');
    result = await call(`/api/arrangements/${requestNumber}/advance/evidence`, { method: 'POST', body: invalidForm }); assert(result.response.status === 400, 'invalid file content was accepted');
    const evidenceForm = new FormData(); evidenceForm.append('trackingToken', trackingToken); evidenceForm.append('reference', 'QA-TID'); evidenceForm.append('evidence', new Blob([Buffer.from('%PDF-1.4 QA evidence')], { type: 'application/pdf' }), 'qa-evidence.pdf');
    result = await call(`/api/arrangements/${requestNumber}/advance/evidence`, { method: 'POST', body: evidenceForm }); assert(result.response.status === 201, `advance evidence submission failed: ${result.response.status} ${JSON.stringify(result.body)}`); const stored = await ArrangementRequest.findOne({ _id: arrangement._id }).select('+payment.evidenceFiles').lean(); evidenceStorageName = stored.payment.evidenceFiles[0].storageName;
    const duplicateForm = new FormData(); duplicateForm.append('trackingToken', trackingToken); duplicateForm.append('reference', 'QA-TID-2'); duplicateForm.append('evidence', new Blob([Buffer.from('%PDF-1.4 duplicate')], { type: 'application/pdf' }), 'duplicate.pdf');
    result = await call(`/api/arrangements/${requestNumber}/advance/evidence`, { method: 'POST', body: duplicateForm }); assert(result.response.status === 409, 'duplicate evidence submission was accepted');
    const wrongTokenForm = new FormData(); wrongTokenForm.append('trackingToken', 'wrong-token'); wrongTokenForm.append('reference', 'QA-TID-3'); wrongTokenForm.append('evidence', new Blob([Buffer.from('%PDF-1.4 unauthorized')], { type: 'application/pdf' }), 'unauthorized.pdf');
    result = await call(`/api/arrangements/${requestNumber}/advance/evidence`, { method: 'POST', body: wrongTokenForm }); assert(result.response.status === 404, 'unauthorized evidence upload was accepted');
    result = await call(`/api/admin/inventory/arrangements/${arrangement._id}`, { method: 'PATCH', headers: { Cookie: cookie }, body: JSON.stringify({ status: 'ADVANCE_VERIFIED', paymentVerified: true }) }); assert(result.response.status === 200, 'advance verification failed');
    const final = await ArrangementRequest.findOne({ requestNumber }).lean(); assert(final.status === 'ADVANCE_VERIFIED' && final.payment.state === 'VERIFIED', 'payment was not verified through admin workflow');
    const concurrent = await Promise.all([1, 2].map(index => call('/api/admin/inventory/offline-sales', { method: 'POST', headers: { Cookie: cookie }, body: JSON.stringify({ productId: concurrentProduct._id, quantity: 1, sellingPrice: 1000, eventId: `concurrent-${suffix}-${index}` }) })));
    assert(concurrent.filter(item => item.response.status === 201).length === 1 && concurrent.filter(item => item.response.status === 409).length === 1, 'concurrent final-stock sale did not produce exactly one winner');
    assert((await Product.findById(concurrentProduct._id)).stock === 0, 'concurrent sale stock is incorrect');
    const variantId = variantProduct.variants[0]._id;
    result = await call('/api/admin/inventory/offline-sales', { method: 'POST', headers: { Cookie: cookie }, body: JSON.stringify({ productId: variantProduct._id, variantId, quantity: 1, sellingPrice: 1000, eventId: `variant-${suffix}` }) }); assert(result.response.status === 201, 'variant offline sale failed');
    const variantAfter = await Product.findById(variantProduct._id).lean(); assert(variantAfter.stock === 9 && variantAfter.variants[0].stock === 0, 'variant sale changed the wrong stock field');
    console.log(JSON.stringify({ passed: true, offlineSaleStock: 0, restoredStock: 1, concurrentWinners: 1, variantParentStock: variantAfter.stock, variantStock: variantAfter.variants[0].stock, advance: final.advanceAmount, remaining: final.remainingAmount, arrangementStatus: final.status }));
  } finally {
    if (evidenceStorageName) await fs.promises.rm(path.join(evidenceRoot, evidenceStorageName), { force: true }).catch(() => {});
    if (arrangementId) await ArrangementRequest.deleteOne({ _id: arrangementId });
    await Product.deleteMany({ _id: { $in: [product._id, concurrentProduct._id, variantProduct._id] } }); await AdminSession.deleteOne({ _id: session._id }); await Admin.deleteOne({ _id: admin._id }); await OfflineSale.deleteMany({ inventoryEventId: { $regex: suffix } }); await InventoryTransaction.deleteMany({ eventId: { $regex: suffix } });
  }
  await mongoose.disconnect();
})().catch(async error => { console.error(error.stack || error); await mongoose.disconnect(); process.exitCode = 1; });
