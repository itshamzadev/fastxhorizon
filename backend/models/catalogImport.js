const mongoose = require('mongoose');

const CatalogImportSchema = new mongoose.Schema(
  {
    supplier: { type: String, required: true, trim: true, maxlength: 80 },
    filename: { type: String, required: true, trim: true, maxlength: 255 },
    uploadedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true },
    status: { type: String, enum: ['PREVIEWED', 'COMPLETED', 'FAILED'], required: true },
    mode: { type: String, enum: ['new_only', 'update_existing', 'skip_existing'], default: 'new_only' },
    previewExpiresAt: { type: Date, required: true },
    confirmedAt: { type: Date, default: null },
    // Parsed supplier rows are private staging data and are never returned by history APIs.
    stagedProducts: { type: [mongoose.Schema.Types.Mixed], select: false, default: [] },
    summary: {
      rows: { type: Number, default: 0 },
      uniqueProducts: { type: Number, default: 0 },
      imageRows: { type: Number, default: 0 },
      newProducts: { type: Number, default: 0 },
      existingProducts: { type: Number, default: 0 },
      skippedProducts: { type: Number, default: 0 },
      failedProducts: { type: Number, default: 0 },
      invalidRows: { type: Number, default: 0 },
      missingPrices: { type: Number, default: 0 },
      missingImages: { type: Number, default: 0 },
      duplicateSkus: { type: Number, default: 0 },
      selectedProducts: { type: Number, default: 0 },
    },
    errors: { type: [mongoose.Schema.Types.Mixed], default: [] },
  },
  { timestamps: true },
);

CatalogImportSchema.index({ supplier: 1, createdAt: -1 });

module.exports = mongoose.model('CatalogImport', CatalogImportSchema);
