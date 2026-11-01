const express = require('express');
const { ALLOWED_EVENTS, sendTikTokEvent, sanitizeProperties } = require('../services/tiktokEventsApi');

const router = express.Router();
const requestBuckets = new Map();
const WINDOW_MS = 60 * 1000;
const MAX_REQUESTS = 120;

function withinRateLimit(req) {
  const key = String(req.ip || 'unknown');
  const now = Date.now();
  const current = requestBuckets.get(key);
  if (!current || now - current.startedAt >= WINDOW_MS) {
    requestBuckets.set(key, { startedAt: now, count: 1 });
    return true;
  }
  current.count += 1;
  return current.count <= MAX_REQUESTS;
}

router.post('/events', async (req, res) => {
  if (!withinRateLimit(req)) return res.status(429).json({ code: 'rate_limited', message: 'Analytics event rate limit exceeded.' });
  const eventName = String(req.body?.event || '');
  const eventId = String(req.body?.event_id || '');
  if (!ALLOWED_EVENTS.has(eventName) || !/^[A-Za-z0-9:_./-]{1,180}$/.test(eventId)) return res.status(400).json({ code: 'invalid_event', message: 'Invalid analytics event.' });
  const properties = sanitizeProperties(req.body?.properties || {});
  // The endpoint intentionally returns immediately after scheduling the
  // non-blocking delivery. No customer PII, tokens or payment evidence is accepted.
  void sendTikTokEvent({ eventName, eventId, properties, eventTime: req.body?.event_time, pageUrl: req.body?.page_url }).catch(error => {
    console.warn(`[TikTok Events API] delivery error for ${eventName}: ${String(error?.message || 'unknown_error').slice(0, 120)}`);
  });
  return res.status(202).json({ accepted: true });
});

module.exports = router;
