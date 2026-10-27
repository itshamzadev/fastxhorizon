const mongoose = require('mongoose');

const storefrontSettingSchema = new mongoose.Schema(
  {
    key: { type: String, required: true, unique: true, immutable: true },
    announcementEnabled: { type: Boolean, required: true, default: true },
    announcementMessage: { type: String, required: true, trim: true, maxlength: 180, default: 'Coming Soon — Discover a New Shopping Experience with FastXHorizon' },
  },
  { timestamps: true }
);

module.exports = mongoose.model('StorefrontSetting', storefrontSettingSchema);
