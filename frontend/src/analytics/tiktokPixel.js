import { apiClient } from '../services/apiClient';

const TIKTOK_SCRIPT_URL = 'https://analytics.tiktok.com/i18n/pixel/events.js';
const PIXEL_ID = String(process.env.REACT_APP_TIKTOK_PIXEL_ID || '').trim();
const GLOBAL_MARKER = '__FASTXHORIZON_TIKTOK_PIXEL__';
let lastTrackedPage = null;
const trackedEventIds = new Set();

function parseConsentValue(value) {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (['accepted', 'accept', 'all', 'granted', 'true', 'yes'].includes(normalized)) return true;
    if (['denied', 'deny', 'rejected', 'reject', 'false', 'no', 'necessary'].includes(normalized)) return false;
    try { return parseConsentValue(JSON.parse(value)); } catch (_) { return null; }
  }
  if (typeof value === 'object') {
    const relevant = ['analytics', 'marketing', 'advertising', 'tracking', 'statistics']
      .find(key => Object.prototype.hasOwnProperty.call(value, key));
    return relevant ? parseConsentValue(value[relevant]) : null;
  }
  return null;
}

export function hasTikTokConsent() {
  if (typeof window === 'undefined') return false;
  const globalConsent = parseConsentValue(window.__FASTXHORIZON_COOKIE_CONSENT__);
  if (globalConsent !== null) return globalConsent;

  const keys = ['cookieConsent', 'cookie-consent', 'cookie_consent', 'consentPreferences', 'analyticsConsent', 'marketingConsent'];
  for (const key of keys) {
    try {
      const consent = parseConsentValue(window.localStorage.getItem(key));
      if (consent !== null) return consent;
    } catch (_) {
      // Storage can be unavailable in privacy-restricted browsers.
    }
  }

  // No consent manager exists in the current storefront. If one is added later,
  // its explicit denial is honored by the checks above.
  return true;
}

function setAndDefer(queue, method) {
  if (!queue[method]) queue[method] = (...args) => queue.push([method, ...args]);
}

export function initializeTikTokPixel(routeKey = '') {
  if (!PIXEL_ID || typeof window === 'undefined' || !hasTikTokConsent()) return false;
  if (window[GLOBAL_MARKER] === PIXEL_ID) return false;

  const objectName = 'ttq';
  window.TiktokAnalyticsObject = objectName;
  const ttq = window[objectName] = window[objectName] || [];
  const methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie', 'holdConsent', 'revokeConsent', 'grantConsent'];
  ttq.methods = methods;
  methods.forEach(method => setAndDefer(ttq, method));
  ttq.setAndDefer = (queue, method) => setAndDefer(queue, method);
  ttq.instance = ttq.instance || function instance(id) {
    const queue = ttq._i?.[id] || [];
    methods.forEach(method => setAndDefer(queue, method));
    return queue;
  };
  ttq.load = ttq.load || function load(id, options) {
    const scriptUrl = TIKTOK_SCRIPT_URL;
    ttq._i = ttq._i || {};
    ttq._i[id] = [];
    ttq._i[id]._u = scriptUrl;
    ttq._t = ttq._t || {};
    ttq._t[id] = +new Date();
    ttq._o = ttq._o || {};
    ttq._o[id] = options || {};
    const script = document.createElement('script');
    script.type = 'text/javascript';
    script.async = true;
    script.src = `${scriptUrl}?sdkid=${encodeURIComponent(id)}&lib=${objectName}`;
    script.dataset.fastxhorizonTikTokPixel = id;
    const firstScript = document.getElementsByTagName('script')[0];
    firstScript?.parentNode?.insertBefore(script, firstScript);
  };

  ttq.load(PIXEL_ID);
  ttq.page();
  lastTrackedPage = routeKey;
  window[GLOBAL_MARKER] = PIXEL_ID;
  return true;
}

export function trackTikTokPageView(routeKey = '') {
  if (!PIXEL_ID || typeof window === 'undefined' || !hasTikTokConsent()) return;
  if (routeKey && lastTrackedPage === routeKey) return;
  if (window[GLOBAL_MARKER] !== PIXEL_ID) {
    initializeTikTokPixel(routeKey);
    return;
  }
  window.ttq?.page?.();
  lastTrackedPage = routeKey;
}

export function getTikTokPixelId() {
  return PIXEL_ID;
}

function rememberEvent(eventId) {
  if (!eventId) return true;
  if (trackedEventIds.has(eventId)) return false;
  trackedEventIds.add(eventId);
  return true;
}

export function trackTikTokEvent(eventName, properties = {}, eventId = '') {
  if (!PIXEL_ID || typeof window === 'undefined' || !hasTikTokConsent()) return false;
  if (!window.ttq?.track) initializeTikTokPixel();
  if (!window.ttq?.track || !rememberEvent(eventId)) return false;
  const payload = { ...properties, event_id: eventId || undefined };
  window.ttq.track(eventName, payload);
  if (typeof apiClient?.post === 'function') {
    void apiClient.post('analytics/tiktok/events', {
      event: eventName,
      event_id: eventId,
      event_time: Math.floor(Date.now() / 1000),
      properties,
      page_url: window.location.href,
    }).catch(() => {});
  }
  return true;
}

export async function identifyTikTokCustomer({ email, phoneNumber, externalId } = {}) {
  if (!PIXEL_ID || typeof window === 'undefined' || !hasTikTokConsent() || !window.ttq?.identify) return false;
  const encoder = new TextEncoder();
  const hash = async value => {
    const normalized = String(value || '').trim().toLowerCase();
    if (!normalized || !window.crypto?.subtle) return '';
    const digest = await window.crypto.subtle.digest('SHA-256', encoder.encode(normalized));
    return Array.from(new Uint8Array(digest)).map(byte => byte.toString(16).padStart(2, '0')).join('');
  };
  const [hashedEmail, hashedPhone, hashedExternalId] = await Promise.all([hash(email), hash(phoneNumber), hash(externalId)]);
  const identity = {};
  if (hashedEmail) identity.email = hashedEmail;
  if (hashedPhone) identity.phone_number = hashedPhone;
  if (hashedExternalId) identity.external_id = hashedExternalId;
  if (!Object.keys(identity).length) return false;
  window.ttq.identify(identity);
  return true;
}
