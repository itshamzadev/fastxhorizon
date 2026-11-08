const mongoose = require('mongoose');

const OfflineSaleSchema = new mongoose.Schema({
  saleNumber: { type: String, required: true, unique: true, index: true },
  productId: { type: mongoose.Schema.Types.ObjectId, ref: 'Product', required: true },
  variantId: { type: mongoose.Schema.Types.ObjectId, default: null },
  quantity: { type: Number, required: true, min: 1 },
  sellingPrice: { type: Number, required: true, min: 0 },
  discount: { type: Number, min: 0, default: 0 },
  saleDate: { type: Date, default: Date.now },
  channel: { type: String, enum: ['OFFLINE', 'WEBSITE', 'OTHER'], default: 'OFFLINE' },
  customer: { name: { type: String, default: '' }, phone: { type: String, default: '' }, paymentMethod: { type: String, default: '' } },
  notes: { type: String, default: '' },
  recordedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true },
  inventoryEventId: { type: String, required: true, unique: true },
}, { timestamps: true });

module.exports = mongoose.model('OfflineSale', OfflineSaleSchema);
