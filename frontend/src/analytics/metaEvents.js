import { trackMetaEvent } from './metaPixel';

export const META_CURRENCY = 'PKR';

export function metaContent(product, quantity = 1) {
  const id = product?._id || product?.id || product?.productId || product?.sku;
  if (!id) return null;
  return { id: String(id), content_name: String(product?.name || product?.title || 'Product'), quantity: Math.max(1, Number(quantity) || 1) };
}

export function metaContents(items = []) { return items.map(item => metaContent(item, item?.quantity)).filter(Boolean); }
export function metaValue(items = []) { return items.reduce((sum, item) => sum + (Number(item?.price) || 0) * Math.max(1, Number(item?.quantity) || 1), 0); }

export function trackMetaProduct(eventName, product, { quantity = 1, eventId } = {}) {
  const item = metaContent(product, quantity);
  if (!item) return false;
  return trackMetaEvent(eventName, { content_ids: [item.id], content_type: 'product', contents: [item], content_name: item.content_name, value: (Number(product?.price) || 0) * item.quantity, currency: META_CURRENCY }, eventId || `${eventName}:${item.id}`);
}

export function trackMetaCart(eventName, items, eventId, extra = {}) {
  const contents = metaContents(items);
  if (!contents.length) return false;
  return trackMetaEvent(eventName, { content_ids: contents.map(item => item.id), content_type: 'product', contents, value: metaValue(items), currency: META_CURRENCY, ...extra }, eventId);
}
