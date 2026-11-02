const crypto = require('crypto');
const axios = require('axios');
const MetaEvent = require('../models/metaEvent');

const ALLOWED_EVENTS = new Set(['PageView', 'ViewContent', 'Search', 'AddToWishlist', 'AddToCart', 'InitiateCheckout', 'AddPaymentInfo', 'CompleteRegistration', 'OrderPlaced', 'Purchase']);
const PIXEL_ID = String(process.env.META_PIXEL_ID || '').trim();
const ACCESS_TOKEN = String(process.env.META_CAPI_ACCESS_TOKEN || '').trim();
const ENABLED = String(process.env.META_CAPI_ENABLED || '').toLowerCase() === 'true';
// Keep the version configurable because Meta retires Graph API versions.
const API_VERSION = String(process.env.META_GRAPH_API_VERSION || 'v23.0').trim();
const TEST_EVENT_CODE = String(process.env.META_CAPI_TEST_EVENT_CODE || '').trim();

const truncate = (value, length = 240) => String(value || '').replace(/[\r\n]+/g, ' ').slice(0, length);

const hash = value => { const normalized = String(value || '').trim().toLowerCase(); return normalized ? crypto.createHash('sha256').update(normalized).digest('hex') : undefined; };
function safeUserData(userData = {}) { const result = {}; if (userData.email) result.em = [hash(userData.email)]; if (userData.phone) result.ph = [hash(String(userData.phone).replace(/[^0-9+]/g, ''))]; return result; }
function safeCustomData(customData = {}) {
  const result = {};
  if (Array.isArray(customData.content_ids)) result.content_ids = customData.content_ids.map(String).slice(0, 100);
  if (Array.isArray(customData.contents)) result.contents = customData.contents.slice(0, 100).map(item => ({ content_id: String(item.content_id || item.id || ''), content_type: 'product', content_name: String(item.content_name || '').slice(0, 200), quantity: Math.max(1, Number(item.quantity) || 1) }));
  if (customData.value !== undefined && Number.isFinite(Number(customData.value))) result.value = Number(customData.value);
  result.currency = 'PKR';
  if (customData.order_id) result.order_id = String(customData.order_id).slice(0, 100);
  return result;
}

async function sendMetaEvent({ eventName, eventId, consent = false, eventTime, eventSourceUrl, userData, customData }) {
  if (!ENABLED || !PIXEL_ID || !ACCESS_TOKEN || consent !== true || !ALLOWED_EVENTS.has(eventName) || !eventId) return { skipped: true };
  try {
    const existing = await MetaEvent.findOne({ eventId }).lean();
    if (existing?.status === 'sent' || existing?.status === 'sending') return { skipped: true, reason: 'duplicate' };
    if (existing) await MetaEvent.updateOne({ _id: existing._id }, { $set: { status: 'sending', lastAttemptAt: new Date() }, $inc: { attempts: 1 } });
    else { try { await MetaEvent.create({ eventId, eventName, status: 'sending', attempts: 1 }); } catch (error) { if (error?.code === 11000) return { skipped: true, reason: 'duplicate' }; throw error; } }
    const payload = { data: [{ event_name: eventName, event_time: Number.isInteger(Number(eventTime)) ? Number(eventTime) : Math.floor(Date.now() / 1000), event_id: String(eventId), action_source: 'website', event_source_url: /^https:\/\//i.test(String(eventSourceUrl || '')) ? String(eventSourceUrl) : 'https://www.fastxhorizon.com/', user_data: safeUserData(userData), custom_data: safeCustomData(customData) }] };
    if (TEST_EVENT_CODE) payload.test_event_code = TEST_EVENT_CODE;
    const response = await axios.post(`https://graph.facebook.com/${API_VERSION}/${encodeURIComponent(PIXEL_ID)}/events`, payload, { params: { access_token: ACCESS_TOKEN }, timeout: 5000 });
    if (response.status < 200 || response.status >= 300 || response.data?.error) throw new Error('Meta API rejected event.');
    await MetaEvent.updateOne({ eventId }, { $set: { status: 'sent', sentAt: new Date(), lastError: '' } });
    console.info('[Meta CAPI] event accepted', {
      eventName,
      eventId: truncate(eventId, 40),
      status: response.status,
      eventsReceived: Number.isFinite(Number(response.data?.events_received)) ? Number(response.data.events_received) : null,
    });
    return { sent: true };
  } catch (error) {
    await MetaEvent.updateOne({ eventId }, { $set: { status: 'failed', lastError: String(error?.response?.data?.error?.message || error?.message || 'request_failed').slice(0, 300) } }).catch(() => {});
    const metaError = error?.response?.data?.error || {};
    console.warn('[Meta CAPI] event delivery failed', {
      eventName,
      eventId: truncate(eventId, 40),
      status: error?.response?.status || null,
      code: metaError.code || null,
      type: truncate(metaError.type, 80) || null,
      message: truncate(metaError.message || error?.message, 240),
    });
    return { sent: false, error: true };
  }
}

module.exports = { ALLOWED_EVENTS, sendMetaEvent, safeUserData, safeCustomData };
