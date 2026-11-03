const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  adminId: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true, index: true },
  deviceId: { type: String, required: true, trim: true, maxlength: 160 },
  deviceName: { type: String, trim: true, maxlength: 160, default: 'Android device' },
  refreshTokenHash: { type: String, required: true, unique: true, index: true },
  createdAt: { type: Date, default: Date.now },
  lastUsedAt: { type: Date, default: Date.now },
  expiresAt: { type: Date, required: true },
  revokedAt: { type: Date, default: null },
}, { timestamps: true });

schema.index({ adminId: 1, deviceId: 1 }, { unique: true });
schema.index({ expiresAt: 1 }, { expireAfterSeconds: 0 });

module.exports = mongoose.model('AdminDeviceSession', schema);
