const mongoose = require('mongoose');

const AdminSchema = new mongoose.Schema(
  {
    name: { type: String, required: true, trim: true, maxlength: 120 },
    email: { type: String, required: true, unique: true, lowercase: true, trim: true },
    passwordHash: { type: String, required: true, select: false },
    role: { type: String, enum: ['super_admin', 'admin'], default: 'admin' },
    permissions: { type: [String], default: [] },
    status: { type: String, enum: ['active', 'suspended'], default: 'active' },
    mfaSecretEncrypted: { type: String, default: null, select: false },
    mfaEnrolledAt: { type: Date, default: null },
    recoveryCodeHashes: { type: [String], default: [], select: false },
    lastLoginAt: { type: Date, default: null },
  },
  { timestamps: true },
);

module.exports = mongoose.model('Admin', AdminSchema);
