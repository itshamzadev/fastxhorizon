const mongoose = require('mongoose');

const StockManagerIdentitySchema = new mongoose.Schema({
  localEntityType: { type: String, enum: ['product', 'variant'], required: true },
  localEntityId: { type: String, required: true },
  remoteEntityType: { type: String, enum: ['product', 'variant'], required: true },
  remoteEntityId: { type: String, required: true },
  remoteVersion: { type: Number, default: 0 },
  state: { type: String, enum: ['ACTIVE', 'CONFLICT', 'ARCHIVED'], default: 'ACTIVE' },
}, { timestamps: true });

StockManagerIdentitySchema.index({ localEntityType: 1, localEntityId: 1 }, { unique: true });
StockManagerIdentitySchema.index({ remoteEntityType: 1, remoteEntityId: 1 }, { unique: true });

module.exports = mongoose.model('StockManagerIdentity', StockManagerIdentitySchema);
