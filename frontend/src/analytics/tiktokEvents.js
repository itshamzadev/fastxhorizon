import { trackTikTokEvent } from './tiktokPixel';

export const TIKTOK_CURRENCY = 'PKR';

export function productContent(product, quantity = 1) {
  const id = product?._id || product?.id || product?.productId || product?.sku;
  return id ? {
    content_id: String(id),
    content_type: 'product',
    content_name: String(product?.name || product?.title || 'Product'),
    quantity: Math.max(1, Number(quantity) || 1),
  } : null;
}

export function productContents(items = []) {
  return items.map(item => productContent(item, item?.quantity)).filter(Boolean);
}

export function eventValue(items = []) {
  return items.reduce((total, item) => total + (Number(item?.price) || 0) * Math.max(1, Number(item?.quantity) || 1), 0);
}

export function trackProductEvent(eventName, product, { quantity = 1, value, eventId } = {}) {
  const content = productContent(product, quantity);
  if (!content) return false;
  return trackTikTokEvent(eventName, {
    contents: [content],
    value: Number.isFinite(Number(value)) ? Number(value) : (Number(product?.price) || 0) * content.quantity,
    currency: TIKTOK_CURRENCY,
  }, eventId || `${eventName}:${content.content_id}`);
}

export function trackCartEvent(eventName, items, eventId, extraProperties = {}) {
  const contents = productContents(items);
  if (!contents.length) return false;
  return trackTikTokEvent(eventName, { contents, value: eventValue(items), currency: TIKTOK_CURRENCY, ...extraProperties }, eventId);
}
