const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  orderId: { type: mongoose.Schema.Types.ObjectId, ref: 'Order', required: true, index: true },
  amount: { type: Number, required: true, min: 0 },
  currency: { type: String, enum: ['PKR'], default: 'PKR' },
  reason: { type: String, required: true, maxlength: 500 },
  method: { type: String, enum: ['MANUAL_REPAYMENT', 'PAYMENT_REVERSAL'], required: true },
  reference: { type: String, maxlength: 160, default: '' },
  state: { type: String, enum: ['REFUND_PENDING', 'REFUND_PROCESSING', 'REFUNDED', 'REFUND_FAILED'], default: 'REFUND_PENDING', index: true },
  initiatedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true },
  completedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
  initiatedAt: { type: Date, default: Date.now },
  completedAt: { type: Date, default: null },
}, { timestamps: true });

schema.index({ orderId: 1, state: 1 });

module.exports = mongoose.model('Refund', schema);
