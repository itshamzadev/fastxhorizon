const mongoose = require('mongoose');

const AdminSessionSchema = new mongoose.Schema(
  {
    tokenHash: { type: String, required: true, unique: true, index: true },
    adminId: { type: mongoose.Schema.Types.ObjectId, ref: 'Admin', required: true, index: true },
    state: { type: String, enum: ['pre_mfa', 'authenticated'], required: true },
    pendingMfaSecretEncrypted: { type: String, default: null },
    pendingRecoveryCodeHashes: { type: [String], default: [] },
    expiresAt: { type: Date, required: true },
  },
  { timestamps: true },
);

AdminSessionSchema.index({ expiresAt: 1 }, { expireAfterSeconds: 0 });

module.exports = mongoose.model('AdminSession', AdminSessionSchema);
