export function getValidDiscount(price, compareAtPrice) {
  const selling = Number(price);
  const original = Number(compareAtPrice);
  if (!Number.isFinite(selling) || selling < 0 || !Number.isFinite(original) || original <= selling || original <= 0) return { sellingPrice: Number.isFinite(selling) ? selling : 0, originalPrice: null, discountPercent: 0 };
  return { sellingPrice: selling, originalPrice: original, discountPercent: Math.round(((original - selling) / original) * 100) };
}
