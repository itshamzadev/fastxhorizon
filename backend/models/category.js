const mongoose = require('mongoose');

const schema = new mongoose.Schema({
  name: { type: String, required: true, trim: true },
  slug: { type: String, required: true, lowercase: true, trim: true },
  parentSlug: { type: String, default: null, index: true },
  active: { type: Boolean, default: true },
}, { timestamps: true });
schema.index({ slug: 1, parentSlug: 1 }, { unique: true });
module.exports = mongoose.model('Category', schema);
