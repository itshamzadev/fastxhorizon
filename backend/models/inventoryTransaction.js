const mongoose = require('mongoose');

const InventoryTransactionSchema = new mongoose.Schema({
  eventId: { type: String, required: true, unique: true, index: true },
  productId: { type: mongoose.Schema.Types.ObjectId, ref: 'Product', required: true, index: true },
  variantId: { type: mongoose.Schema.Types.ObjectId, default: null },
  type: { type: String, enum: ['OFFLINE_SALE', 'ADJUSTMENT', 'ARRANGEMENT_RESERVATION', 'ARRANGEMENT_RELEASE'], required: true },
  quantity: { type: Number, required: true },
  stockBefore: { type: Number, required: true, min: 0 },
  stockAfter: { type: Number, required: true, min: 0 },
  referenceId: { type: String, default: '' },
  reason: { type: String, default: '' },
  metadata: { type: mongoose.Schema.Types.Mixed, default: {} },
  actorId: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
}, { timestamps: true });

module.exports = mongoose.model('InventoryTransaction', InventoryTransactionSchema);
