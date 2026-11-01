const axios = require('axios');
const TikTokEvent = require('../models/tiktokEvent');

const TIKTOK_ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
const ALLOWED_EVENTS = new Set(['ViewContent', 'AddToWishlist', 'Search', 'AddPaymentInfo', 'AddToCart', 'InitiateCheckout', 'PlaceAnOrder', 'CompleteRegistration', 'Purchase']);
const RETRY_AFTER_MS = 60 * 1000;
const MAX_CONTENTS = 100;
const inFlightEvents = new Set();

function config() {
  return {
    accessToken: String(process.env.TIKTOK_EVENTS_API_ACCESS_TOKEN || '').trim(),
    pixelId: String(process.env.TIKTOK_PIXEL_ID || '').trim(),
  };
}

function isPaymentConfirmedEvent(eventName, properties) {
  return eventName !== 'Purchase' || properties?.payment_status === 'verified' || properties?.payment_lifecycle === 'VERIFIED';
}

function sanitizeProperties(input = {}) {
  const contents = Array.isArray(input.contents) ? input.contents.slice(0, MAX_CONTENTS).map(item => ({
    content_id: String(item?.content_id || '').slice(0, 180),
    content_type: String(item?.content_type || 'product').slice(0, 40),
    content_name: String(item?.content_name || '').slice(0, 250),
    ...(Number.isFinite(Number(item?.quantity)) ? { quantity: Math.max(1, Number(item.quantity)) } : {}),
  })).filter(item => item.content_id) : [];
  const properties = { contents };
  if (Number.isFinite(Number(input.value))) properties.value = Number(input.value);
  if (String(input.currency || '').toUpperCase() === 'PKR') properties.currency = 'PKR';
  if (typeof input.search_string === 'string') properties.search_string = input.search_string.slice(0, 200);
  if (input.payment_status === 'verified') properties.payment_status = 'verified';
  if (input.payment_lifecycle === 'VERIFIED') properties.payment_lifecycle = 'VERIFIED';
  return properties;
}

function validEventId(value) {
  return typeof value === 'string' && /^[A-Za-z0-9:_./-]{1,180}$/.test(value);
}

async function claimEvent(eventId, eventName) {
  const now = new Date();
  const existing = await TikTokEvent.findOne({ eventId }).lean();
  if (existing?.status === 'sent') return { claimed: false, reason: 'already_sent' };
  if (existing?.status === 'sending' && now.getTime() - new Date(existing.lastAttemptAt).getTime() < RETRY_AFTER_MS) return { claimed: false, reason: 'in_progress' };
  const record = await TikTokEvent.findOneAndUpdate(
    { eventId },
    { $set: { eventName, status: 'sending', lastAttemptAt: now }, $inc: { attempts: 1 }, $setOnInsert: { eventId, eventName } },
    { upsert: true, new: true, setDefaultsOnInsert: true },
  ).lean();
  return { claimed: record?.status === 'sending' };
}

async function sendTikTokEvent({ eventName, eventId, properties = {}, eventTime, pageUrl }) {
  const { accessToken, pixelId } = config();
  if (!accessToken || !pixelId) return { sent: false, skipped: true, reason: 'not_configured' };
  if (!ALLOWED_EVENTS.has(eventName) || !validEventId(eventId) || !isPaymentConfirmedEvent(eventName, properties)) return { sent: false, skipped: true, reason: 'invalid_or_unconfirmed' };

  const claim = await claimEvent(eventId, eventName);
  if (!claim.claimed) return { sent: false, skipped: true, reason: claim.reason };
  if (inFlightEvents.has(eventId)) return { sent: false, skipped: true, reason: 'in_progress' };
  inFlightEvents.add(eventId);

  const nowSeconds = Math.floor(Date.now() / 1000);
  const requestedTime = Number(eventTime);
  const timestamp = Number.isInteger(requestedTime) && Math.abs(nowSeconds - requestedTime) <= 300 ? requestedTime : nowSeconds;
  const body = {
    event_source: 'web',
    event_source_id: pixelId,
    data: [{
      event: eventName,
      event_id: eventId,
      event_time: timestamp,
      properties: sanitizeProperties(properties),
      ...(typeof pageUrl === 'string' && /^https:\/\//i.test(pageUrl) ? { page: { url: pageUrl.slice(0, 2000) } } : {}),
    }],
  };

  try {
    const response = await axios.post(TIKTOK_ENDPOINT, body, {
      timeout: 8000,
      headers: { 'Access-Token': accessToken, 'Content-Type': 'application/json' },
    });
    const responseCode = response?.data?.code;
    if (responseCode !== 0 && responseCode !== undefined) throw new Error(`TikTok API code ${responseCode}`);
    await TikTokEvent.updateOne({ eventId }, { $set: { status: 'sent', sentAt: new Date(), lastError: '' } });
    return { sent: true };
  } catch (error) {
    await TikTokEvent.updateOne({ eventId }, { $set: { status: 'failed', lastError: String(error?.response?.data?.message || error?.message || 'request_failed').slice(0, 300) } }).catch(() => {});
    console.warn(`[TikTok Events API] ${eventName} failed (event ${eventId.slice(0, 32)}): ${String(error?.response?.status || error?.message || 'request_failed').slice(0, 120)}`);
    return { sent: false, failed: true };
  } finally {
    inFlightEvents.delete(eventId);
  }
}

module.exports = { ALLOWED_EVENTS, sendTikTokEvent, sanitizeProperties };
