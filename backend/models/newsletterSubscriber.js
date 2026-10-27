const mongoose = require('mongoose');

const newsletterSubscriberSchema = new mongoose.Schema(
  {
    email: { type: String, required: true, unique: true, lowercase: true, trim: true, index: true },
    status: { type: String, enum: ['pending', 'confirmed', 'unsubscribed'], default: 'pending', index: true },
    source: { type: String, default: 'footer', maxlength: 40 },
    consentAt: { type: Date, required: true },
    confirmedAt: { type: Date, default: null },
    unsubscribedAt: { type: Date, default: null },
    confirmationTokenHash: { type: String, default: null, select: false, index: true },
    confirmationTokenExpiresAt: { type: Date, default: null, select: false },
  },
  { timestamps: true },
);

module.exports = mongoose.model('NewsletterSubscriber', newsletterSubscriberSchema);
