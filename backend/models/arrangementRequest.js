const mongoose = require('mongoose');

const ARRANGEMENT_STATUSES = ['PENDING_REVIEW', 'AVAILABLE_CONFIRMED', 'ADVANCE_PENDING', 'ADVANCE_SUBMITTED', 'ADVANCE_VERIFIED', 'SOURCING', 'READY_TO_DISPATCH', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'UNAVAILABLE', 'REFUND_PENDING', 'REFUNDED'];
const EvidenceFileSchema = new mongoose.Schema({
  fileId: { type: String, required: true },
  originalName: { type: String, required: true, maxlength: 160 },
  mimeType: { type: String, required: true, enum: ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'] },
  size: { type: Number, required: true, min: 1 },
  storageName: { type: String, required: true, select: false },
  uploadedAt: { type: Date, default: Date.now },
}, { _id: false });

const ArrangementRequestSchema = new mongoose.Schema({
  requestNumber: { type: String, required: true, unique: true, index: true },
  trackingToken: { type: String, required: true, unique: true, index: true, select: false },
  productId: { type: mongoose.Schema.Types.ObjectId, ref: 'Product', required: true },
  variantId: { type: mongoose.Schema.Types.ObjectId, default: null },
  quantity: { type: Number, required: true, min: 1 },
  customerId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', default: null, index: true },
  customer: { name: { type: String, required: true }, phone: { type: String, required: true }, city: { type: String, required: true }, address: { type: String, required: true }, notes: { type: String, default: '' } },
  status: { type: String, enum: ARRANGEMENT_STATUSES, default: 'PENDING_REVIEW', index: true },
  agreedUnitPrice: { type: Number, min: 0, default: null },
  subtotal: { type: Number, min: 0, default: null },
  advanceAmount: { type: Number, min: 0, default: null },
  remainingAmount: { type: Number, min: 0, default: null },
  deliveryCharge: { type: Number, min: 0, default: 0 },
  estimatedWindow: { type: String, default: '' },
  adminNotes: { type: String, default: '' },
  payment: {
    reference: { type: String, default: '' },
    evidence: { type: String, default: '' },
    evidenceFiles: { type: [EvidenceFileSchema], default: [], select: false },
    state: { type: String, enum: ['NOT_REQUIRED', 'PENDING_VERIFICATION', 'VERIFIED', 'REJECTED'], default: 'NOT_REQUIRED' },
    submittedAt: { type: Date, default: null },
    verifiedAt: { type: Date, default: null },
    verifiedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
  },
  statusHistory: [{ status: String, note: String, changedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin' }, changedAt: { type: Date, default: Date.now } }],
}, { timestamps: true });

ArrangementRequestSchema.statics.STATUSES = ARRANGEMENT_STATUSES;
module.exports = mongoose.model('ArrangementRequest', ArrangementRequestSchema);
