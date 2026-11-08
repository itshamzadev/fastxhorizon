import { getValidDiscount } from '../utils/pricing';

describe('genuine product discount calculation', () => {
  test('returns the rounded discount only when comparison price is higher', () => {
    expect(getValidDiscount(4000, 5000)).toEqual({ sellingPrice: 4000, originalPrice: 5000, discountPercent: 20 });
    expect(getValidDiscount(4000, 4000).discountPercent).toBe(0);
    expect(getValidDiscount(4000, 3000).originalPrice).toBeNull();
  });

  test('does not use missing or invalid comparison data', () => {
    expect(getValidDiscount(2400, undefined)).toEqual({ sellingPrice: 2400, originalPrice: null, discountPercent: 0 });
    expect(getValidDiscount(2400, -10).discountPercent).toBe(0);
  });
});
