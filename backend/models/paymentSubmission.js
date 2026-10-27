const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  orderId: { type: mongoose.Schema.Types.ObjectId, ref: 'Order', required: true, index: true },
  orderNumber: { type: String, required: true, index: true },
  email: { type: String, required: true, lowercase: true, index: true },
  methodKey: { type: String, required: true },
  amount: { type: Number, required: true, min: 0 },
  currency: { type: String, enum: ['PKR'], default: 'PKR' },
  state: { type: String, enum: ['PENDING_SUBMISSION', 'PENDING_VERIFICATION', 'VERIFIED', 'REJECTED', 'CANCELLED'], default: 'PENDING_VERIFICATION', index: true },
  methodSnapshot: { type: mongoose.Schema.Types.Mixed, required: true },
  values: { type: mongoose.Schema.Types.Mixed, default: {} },
  evidence: [{ name: String, mimeType: String, size: Number, data: { type: String, select: false } }],
  referenceFingerprint: { type: String, index: true, sparse: true },
  rejectionReason: { type: String, maxlength: 500, default: '' },
  decidedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
  decidedAt: { type: Date, default: null },
}, { timestamps: true });

schema.index({ orderId: 1, state: 1 });

module.exports = mongoose.model('PaymentSubmission', schema);
