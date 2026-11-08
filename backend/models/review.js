const mongoose = require('mongoose');

const ReviewSchema = new mongoose.Schema({
  productId: { type: mongoose.Schema.Types.ObjectId, ref: 'Product', required: true, index: true },
  customerId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, index: true },
  orderId: { type: mongoose.Schema.Types.ObjectId, ref: 'Order', default: null },
  rating: { type: Number, required: true, min: 1, max: 5 },
  title: { type: String, trim: true, maxlength: 120, default: '' },
  comment: { type: String, trim: true, maxlength: 2000, required: true },
  status: { type: String, enum: ['PENDING', 'APPROVED', 'REJECTED', 'HIDDEN'], default: 'PENDING', index: true },
  rejectionReason: { type: String, trim: true, maxlength: 500, default: '' },
  moderatedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
  moderatedAt: { type: Date, default: null },
  moderationHistory: [{ status: String, reason: String, changedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin' }, changedAt: { type: Date, default: Date.now } }],
}, { timestamps: true });

ReviewSchema.index({ productId: 1, customerId: 1 }, { unique: true });
module.exports = mongoose.model('Review', ReviewSchema);
