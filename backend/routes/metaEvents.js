const express = require('express');
const Order = require('../models/order');
const { ALLOWED_EVENTS, sendMetaEvent } = require('../services/metaConversionsApi');

const router = express.Router();
const hits = new Map();
const WINDOW_MS = 60 * 1000;
const MAX_HITS = 120;

function rateLimited(ip) {
  const now = Date.now();
  const current = hits.get(ip) || { start: now, count: 0 };
  if (now - current.start > WINDOW_MS) { hits.set(ip, { start: now, count: 1 }); return false; }
  current.count += 1; hits.set(ip, current);
  return current.count > MAX_HITS;
}

const orderFromEventId = eventId => String(eventId || '').match(/^order:([A-Za-z0-9-]{3,80})(?::purchase)?$/)?.[1];

router.post('/', async (req, res) => {
  if (rateLimited(req.ip)) return res.status(429).json({ message: 'Analytics rate limit exceeded.' });
  const eventName = String(req.body?.event || '');
  const eventId = String(req.body?.event_id || '').slice(0, 180);
  if (!ALLOWED_EVENTS.has(eventName) || !eventId || req.body?.marketing_consent !== true) return res.status(204).end();
  // Registration is emitted authoritatively by /api/auth/register.
  if (eventName === 'CompleteRegistration') return res.status(204).end();

  try {
    let userData = {};
    let customData = req.body?.properties || {};
    if (eventName === 'OrderPlaced' || eventName === 'Purchase') {
      const orderNumber = orderFromEventId(eventId);
      const order = orderNumber ? await Order.findOne({ orderNumber }).select('orderNumber email items total currency paymentStatus marketingConsent').lean() : null;
      const isPurchase = eventName === 'Purchase' || /:purchase$/.test(eventId);
      if (!order || order.marketingConsent !== true || (isPurchase && order.paymentStatus !== 'verified') || (!isPurchase && eventName !== 'OrderPlaced')) return res.status(204).end();
      userData = { email: order.email };
      customData = { content_ids: order.items.map(item => String(item.productId)), contents: order.items.map(item => ({ content_id: String(item.productId), content_name: item.name, quantity: item.quantity })), value: order.total, currency: order.currency || 'PKR', order_id: order.orderNumber };
    }
    void sendMetaEvent({ eventName, eventId, consent: true, eventTime: req.body?.event_time, eventSourceUrl: req.body?.page_url, userData, customData }).catch(() => {});
    return res.status(204).end();
  } catch (_) {
    return res.status(204).end();
  }
});

module.exports = router;
