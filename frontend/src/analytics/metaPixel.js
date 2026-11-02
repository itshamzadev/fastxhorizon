import { apiClient } from '../services/apiClient';

const SCRIPT_URL = 'https://connect.facebook.net/en_US/fbevents.js';
const PIXEL_ID = String(process.env.REACT_APP_META_PIXEL_ID || '').trim();
const GLOBAL_MARKER = '__FASTXHORIZON_META_PIXEL__';
const PRIVATE_PREFIXES = ['/login', '/register', '/forgot-password', '/reset-password', '/cart', '/checkout', '/order-success', '/wishlist', '/admin'];
const trackedEvents = new Set();
let lastPage = '';

function parseConsent(value) {
  if (value === true || value === false) return value;
  if (value && typeof value === 'object') {
    for (const key of ['marketing', 'advertising', 'analytics', 'tracking', 'statistics']) {
      if (Object.prototype.hasOwnProperty.call(value, key)) return parseConsent(value[key]);
    }
    return null;
  }
  if (typeof value !== 'string' || !value.trim()) return null;
  const normalized = value.trim().toLowerCase();
  if (['accepted', 'accept', 'all', 'granted', 'true', 'yes'].includes(normalized)) return true;
  if (['denied', 'deny', 'rejected', 'reject', 'false', 'no', 'necessary'].includes(normalized)) return false;
  try { return parseConsent(JSON.parse(value)); } catch (_) { return null; }
}

export function hasMetaConsent() {
  if (typeof window === 'undefined') return false;
  const globalValue = parseConsent(window.__FASTXHORIZON_COOKIE_CONSENT__);
  if (globalValue !== null) return globalValue;
  for (const key of ['marketingConsent', 'consentPreferences', 'cookieConsent', 'cookie-consent', 'cookie_consent']) {
    try {
      const stored = window.localStorage.getItem(key);
      const value = parseConsent(stored);
      if (value !== null) return value;
    } catch (_) { /* Privacy-restricted storage: keep tracking disabled. */ }
  }
  return false;
}

function isPrivateRoute(route) {
  return PRIVATE_PREFIXES.some(prefix => route === prefix || route.startsWith(`${prefix}/`));
}

export function initializeMetaPixel() {
  if (!PIXEL_ID || typeof window === 'undefined' || !hasMetaConsent() || isPrivateRoute(window.location.pathname)) return false;
  if (window[GLOBAL_MARKER] === PIXEL_ID) return true;
  if (window.fbq) return false;

  const fbq = window.fbq = function fbq() { fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); };
  if (!window._fbq) window._fbq = fbq;
  fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = [];
  const script = document.createElement('script');
  script.async = true; script.src = SCRIPT_URL; script.dataset.fastxhorizonMetaPixel = PIXEL_ID;
  document.head.appendChild(script);
  fbq('init', PIXEL_ID);
  window[GLOBAL_MARKER] = PIXEL_ID;
  return true;
}

export function trackMetaPageView(routeKey = '') {
  if (!PIXEL_ID || !hasMetaConsent() || typeof window === 'undefined' || isPrivateRoute(window.location.pathname) || lastPage === routeKey) return false;
  if (!initializeMetaPixel()) return false;
  window.fbq('track', 'PageView');
  if (typeof apiClient?.post === 'function') {
    void apiClient.post('analytics/meta/events', { event: 'PageView', event_id: `pageview:${routeKey}`, event_time: Math.floor(Date.now() / 1000), properties: {}, page_url: window.location.href, marketing_consent: true }).catch(() => {});
  }
  lastPage = routeKey;
  return true;
}

export function trackMetaEvent(eventName, parameters = {}, eventId = '') {
  if (!PIXEL_ID || !hasMetaConsent() || typeof window === 'undefined' || isPrivateRoute(window.location.pathname)) return false;
  if (!initializeMetaPixel()) return false;
  if (eventId && trackedEvents.has(eventId)) return false;
  if (eventId) trackedEvents.add(eventId);
  const options = eventId ? { eventID: eventId } : undefined;
  const method = eventName === 'OrderPlaced' ? 'trackCustom' : 'track';
  window.fbq(method, eventName, parameters, options);
  if (eventName !== 'CompleteRegistration' && typeof apiClient?.post === 'function') {
    void apiClient.post('analytics/meta/events', { event: eventName, event_id: eventId, event_time: Math.floor(Date.now() / 1000), properties: parameters, page_url: window.location.href, marketing_consent: true }).catch(() => {});
  }
  return true;
}

export function getMetaPixelId() { return PIXEL_ID; }
