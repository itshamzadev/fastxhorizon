const mongoose = require('mongoose');

const StockManagerDeviceSchema = new mongoose.Schema({
  deviceId: { type: String, required: true, unique: true, index: true, trim: true, maxlength: 120 },
  deviceName: { type: String, required: true, trim: true, maxlength: 120 },
  tokenHash: { type: String, required: true, select: false },
  status: { type: String, enum: ['active', 'revoked'], default: 'active', index: true },
  lastSeenAt: { type: Date, default: null },
}, { timestamps: true });

module.exports = mongoose.model('StockManagerDevice', StockManagerDeviceSchema);
