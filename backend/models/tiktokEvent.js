const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  eventId: { type: String, required: true, unique: true, index: true, maxlength: 180 },
  eventName: { type: String, required: true, maxlength: 64 },
  status: { type: String, enum: ['sending', 'sent', 'failed'], default: 'sending', index: true },
  attempts: { type: Number, default: 0 },
  lastAttemptAt: { type: Date, default: Date.now },
  sentAt: { type: Date, default: null },
  lastError: { type: String, default: '', maxlength: 300 },
}, { timestamps: true, versionKey: false });

module.exports = mongoose.model('TikTokEvent', schema);
