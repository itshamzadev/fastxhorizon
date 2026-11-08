const mongoose = require('mongoose');

const StockSyncEventSchema = new mongoose.Schema({
  eventId: { type: String, required: true, unique: true, index: true },
  idempotencyKey: { type: String, required: true, unique: true, index: true },
  source: { type: String, enum: ['DESKTOP', 'WEBSITE', 'ADMIN'], required: true },
  sourceDeviceId: { type: String, default: null, index: true },
  eventType: { type: String, required: true, maxlength: 80 },
  entityType: { type: String, required: true, maxlength: 80 },
  entityId: { type: String, required: true, maxlength: 200 },
  payload: { type: mongoose.Schema.Types.Mixed, required: true },
  status: { type: String, enum: ['APPLIED', 'FAILED', 'PENDING', 'DISMISSED'], default: 'APPLIED', index: true },
  error: { type: String, default: '' },
  acknowledgedBy: { type: [String], default: [] },
}, { timestamps: true });

module.exports = mongoose.model('StockSyncEvent', StockSyncEventSchema);
