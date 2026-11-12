const { calculateArrangementAmounts } = require('../utils/arrangementFinance');

describe('arrangement advance calculations', () => {
  test('calculates mandatory 50 percent advance and exact remainder in PKR', () => {
    expect(calculateArrangementAmounts(5000, 1)).toEqual({ subtotal: 5000, advanceAmount: 2500, remainingAmount: 2500 });
    expect(calculateArrangementAmounts(333.33, 3)).toEqual({ subtotal: 999.99, advanceAmount: 500, remainingAmount: 499.99 });
  });

  test('rejects invalid prices and quantities', () => {
    expect(calculateArrangementAmounts(0, 1)).toBeNull();
    expect(calculateArrangementAmounts(100, 1.5)).toBeNull();
  });
});
