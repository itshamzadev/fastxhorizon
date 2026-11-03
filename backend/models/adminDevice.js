const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  adminId: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true, index: true },
  deviceId: { type: String, required: true, trim: true, maxlength: 160 },
  deviceName: { type: String, trim: true, maxlength: 160, default: 'Android device' },
  platform: { type: String, enum: ['android'], default: 'android' },
  fcmToken: { type: String, required: true, trim: true },
  enabled: { type: Boolean, default: true },
  lastSeenAt: { type: Date, default: Date.now },
}, { timestamps: true });

schema.index({ adminId: 1, deviceId: 1 }, { unique: true });
schema.index({ fcmToken: 1 }, { unique: true });

module.exports = mongoose.model('AdminDevice', schema);
