const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  dedupeKey: { type: String, required: true, unique: true, index: true },
  type: { type: String, required: true },
  title: { type: String, required: true, maxlength: 120 },
  body: { type: String, required: true, maxlength: 240 },
  orderId: { type: mongoose.Schema.Types.ObjectId, ref: 'Order', default: null },
  data: { type: mongoose.Schema.Types.Mixed, default: {} },
  status: { type: String, enum: ['pending', 'processing', 'sent', 'failed'], default: 'pending', index: true },
  attempts: { type: Number, default: 0 },
  lastError: { type: String, maxlength: 500, default: '' },
  nextAttemptAt: { type: Date, default: Date.now, index: true },
  deliveredAt: { type: Date, default: null },
}, { timestamps: true });

module.exports = mongoose.model('AdminNotificationOutbox', schema);
