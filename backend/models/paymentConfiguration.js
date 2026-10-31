const mongoose = require('mongoose');

const FieldSchema = new mongoose.Schema({
  id: { type: String, required: true, match: /^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/ },
  label: { type: String, required: true, maxlength: 120 },
  type: { type: String, enum: ['text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'file'], required: true },
  required: { type: Boolean, default: false },
  placeholder: { type: String, maxlength: 240, default: '' },
  helpText: { type: String, maxlength: 500, default: '' },
  order: { type: Number, min: 0, max: 100, default: 0 },
  enabled: { type: Boolean, default: true },
  options: { type: [String], default: [] },
  acceptedMimeTypes: { type: [String], default: [] },
  maxFileSizeBytes: { type: Number, min: 1, max: 5242880, default: 2097152 },
}, { _id: false });

const MethodSchema = new mongoose.Schema({
  key: { type: String, required: true, match: /^[a-z0-9][a-z0-9_-]{1,39}$/ },
  name: { type: String, required: true, maxlength: 80 },
  enabled: { type: Boolean, default: false },
  instructions: { type: String, maxlength: 2000, default: '' },
  recipientDetails: { type: String, maxlength: 1000, default: '' },
  order: { type: Number, min: 0, max: 100, default: 0 },
  version: { type: Number, min: 1, default: 1 },
  fields: { type: [FieldSchema], default: [] },
}, { _id: true });

const schema = new mongoose.Schema({
  key: { type: String, unique: true, default: 'v1' },
  automaticEnabled: { type: Boolean, default: false },
  manualEnabled: { type: Boolean, default: true },
  codEnabled: { type: Boolean, default: false },
  ordersEnabled: { type: Boolean, default: false },
  orderingDisabledMessage: { type: String, default: 'Online ordering is temporarily unavailable.', maxlength: 240 },
  ordersUpdatedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', default: null },
  ordersUpdatedAt: { type: Date, default: null },
  ordersUpdateReason: { type: String, trim: true, maxlength: 500, default: '' },
  manualMethods: { type: [MethodSchema], default: [] },
}, { timestamps: true });

module.exports = mongoose.model('PaymentConfiguration', schema);
