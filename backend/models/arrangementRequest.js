const mongoose = require('mongoose');

const ARRANGEMENT_STATUSES = ['PENDING_REVIEW', 'CHECKING_AVAILABILITY', 'AVAILABLE_AWAITING_ADVANCE', 'ADVANCE_SUBMITTED', 'ADVANCE_VERIFIED', 'ORDERED_FROM_SUPPLIER', 'PRODUCT_ARRIVED', 'READY_FOR_DELIVERY', 'SHIPPED', 'DELIVERED', 'COMPLETED', 'NOT_AVAILABLE', 'REJECTED', 'CANCELLED', 'REFUND_PENDING', 'REFUNDED',
  // Kept for backwards compatibility with requests created by the earlier MVP.
  'AVAILABLE_CONFIRMED', 'ADVANCE_PENDING', 'SOURCING', 'READY_TO_DISPATCH', 'UNAVAILABLE'];
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
  customer: { name: { type: String, required: true }, phone: { type: String, required: true }, email: { type: String, default: '' }, city: { type: String, required: true }, address: { type: String, required: true }, notes: { type: String, default: '' } },
  status: { type: String, enum: ARRANGEMENT_STATUSES, default: 'PENDING_REVIEW', index: true },
  agreedUnitPrice: { type: Number, min: 0, default: null },
  subtotal: { type: Number, min: 0, default: null },
  advanceAmount: { type: Number, min: 0, default: null },
  remainingAmount: { type: Number, min: 0, default: null },
  advancePaid: { type: Number, min: 0, default: 0 },
  currency: { type: String, default: 'PKR' },
  productSnapshot: { name: String, image: String, variantLabel: String },
  deliveryCharge: { type: Number, min: 0, default: 0 },
  estimatedWindow: { type: String, default: '' },
  expectedDeliveryDate: { type: Date, default: null },
  supplierAvailabilityNote: { type: String, default: '' },
  supplierOrderReference: { type: String, default: '' },
  deliveryProgress: { type: String, default: '' },
  allowCashOnDelivery: { type: Boolean, default: false },
  adminNotes: { type: String, default: '' },
  payment: {
    reference: { type: String, default: '' },
    evidence: { type: String, default: '' },
    evidenceFiles: { type: [EvidenceFileSchema], default: [], select: false },
    state: { type: String, enum: ['NOT_REQUIRED', 'AWAITING_ADVANCE_PAYMENT', 'PAYMENT_SUBMITTED', 'PENDING_VERIFICATION', 'ADVANCE_VERIFIED', 'VERIFIED', 'PAYMENT_REJECTED', 'REJECTED', 'REFUNDED'], default: 'NOT_REQUIRED' },
    submittedAt: { type: Date, default: null },
    verifiedAt: { type: Date, default: null },
    verifiedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
    rejectedAt: { type: Date, default: null },
    rejectionReason: { type: String, default: '' },
  },
  finalPayment: { amount: { type: Number, default: 0 }, state: { type: String, enum: ['NOT_DUE', 'DUE', 'SUBMITTED', 'VERIFIED', 'COD_ALLOWED'], default: 'NOT_DUE' }, method: { type: String, default: '' }, reference: { type: String, default: '' } },
  refund: { amount: { type: Number, default: 0 }, state: { type: String, enum: ['NONE', 'PENDING', 'COMPLETED'], default: 'NONE' }, method: { type: String, default: '' }, reference: { type: String, default: '' }, processedAt: Date, processedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null } },
  statusHistory: [{ status: String, note: String, changedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin' }, changedAt: { type: Date, default: Date.now } }],
}, { timestamps: true });

ArrangementRequestSchema.statics.STATUSES = ARRANGEMENT_STATUSES;
ArrangementRequestSchema.index({ status: 1, createdAt: -1 });
ArrangementRequestSchema.index({ 'customer.phone': 1, createdAt: -1 });
module.exports = mongoose.model('ArrangementRequest', ArrangementRequestSchema);
