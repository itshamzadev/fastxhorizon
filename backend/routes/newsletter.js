const crypto = require('crypto');
const express = require('express');
const NewsletterSubscriber = require('../models/newsletterSubscriber');

const router = express.Router();
const attempts = new Map();
const EMAIL_PATTERN = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
const WINDOW_MS = 15 * 60 * 1000;
const MAX_ATTEMPTS = 5;

function rateLimitKey(req, email) {
  return `${req.ip}:${email}`;
}

function isRateLimited(req, email) {
  const key = rateLimitKey(req, email);
  const now = Date.now();
  const current = attempts.get(key);
  if (!current || current.resetAt <= now) {
    attempts.set(key, { count: 1, resetAt: now + WINDOW_MS });
    return false;
  }
  current.count += 1;
  return current.count > MAX_ATTEMPTS;
}

function hashToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex');
}

function normalizeEmail(value) {
  return String(value || '').trim().toLowerCase();
}

router.post('/subscribe', async (req, res, next) => {
  try {
    const email = normalizeEmail(req.body?.email);
    const source = String(req.body?.source || 'footer').trim().slice(0, 40) || 'footer';
    if (!EMAIL_PATTERN.test(email) || email.length > 254) return res.status(400).json({ code: 'validation_error', message: 'Enter a valid email address.' });
    if (isRateLimited(req, email)) return res.status(429).json({ code: 'rate_limited', message: 'Too many subscription attempts. Please try again later.' });

    const existing = await NewsletterSubscriber.findOne({ email }).select('+confirmationTokenHash +confirmationTokenExpiresAt');
    if (existing?.status === 'confirmed') return res.status(409).json({ code: 'already_subscribed', message: 'This email is already subscribed.' });

    const token = crypto.randomBytes(32).toString('hex');
    const update = {
      source,
      consentAt: new Date(),
      status: 'pending',
      confirmedAt: null,
      unsubscribedAt: null,
      confirmationTokenHash: hashToken(token),
      confirmationTokenExpiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000),
    };
    await NewsletterSubscriber.findOneAndUpdate({ email }, { $set: update, $setOnInsert: { email } }, { upsert: true, new: true, setDefaultsOnInsert: true });

    // No email provider is configured in this local workspace. Never mark a subscriber verified.
    return res.status(202).json({ data: { status: 'pending', deliveryAvailable: false }, message: 'Subscription saved. Confirmation delivery is not configured yet.' });
  } catch (error) {
    if (error.code === 11000) return res.status(409).json({ code: 'already_subscribed', message: 'This email is already subscribed.' });
    return next(error);
  }
});

router.get('/confirm', async (req, res, next) => {
  try {
    const token = String(req.query.token || '');
    if (!token || token.length > 128) return res.status(400).json({ code: 'invalid_token', message: 'Invalid confirmation token.' });
    const subscriber = await NewsletterSubscriber.findOne({ confirmationTokenHash: hashToken(token), confirmationTokenExpiresAt: { $gt: new Date() } }).select('+confirmationTokenHash +confirmationTokenExpiresAt');
    if (!subscriber || subscriber.status === 'unsubscribed') return res.status(400).json({ code: 'invalid_token', message: 'This confirmation link is invalid or expired.' });
    await NewsletterSubscriber.updateOne({ _id: subscriber._id }, { $set: { status: 'confirmed', confirmedAt: new Date() }, $unset: { confirmationTokenHash: 1, confirmationTokenExpiresAt: 1 } });
    return res.json({ data: { status: 'confirmed' } });
  } catch (error) { return next(error); }
});

router.get('/unsubscribe', async (req, res, next) => {
  try {
    const token = String(req.query.token || '');
    if (!token || token.length > 128) return res.status(400).json({ code: 'invalid_token', message: 'Invalid unsubscribe token.' });
    const subscriber = await NewsletterSubscriber.findOne({ confirmationTokenHash: hashToken(token) }).select('+confirmationTokenHash');
    if (!subscriber) return res.status(400).json({ code: 'invalid_token', message: 'Invalid unsubscribe token.' });
    await NewsletterSubscriber.updateOne({ _id: subscriber._id }, { $set: { status: 'unsubscribed', unsubscribedAt: new Date() }, $unset: { confirmationTokenHash: 1, confirmationTokenExpiresAt: 1 } });
    return res.json({ data: { status: 'unsubscribed' } });
  } catch (error) { return next(error); }
});

module.exports = router;
