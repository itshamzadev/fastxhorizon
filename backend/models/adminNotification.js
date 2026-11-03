const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  adminId: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true, index: true },
  type: { type: String, required: true, trim: true },
  title: { type: String, required: true, maxlength: 120 },
  body: { type: String, required: true, maxlength: 240 },
  orderId: { type: mongoose.Schema.Types.ObjectId, ref: 'Order', default: null },
  data: { type: mongoose.Schema.Types.Mixed, default: {} },
  dedupeKey: { type: String, required: true, unique: true, index: true },
  readAt: { type: Date, default: null },
}, { timestamps: true });

module.exports = mongoose.model('AdminNotification', schema);
