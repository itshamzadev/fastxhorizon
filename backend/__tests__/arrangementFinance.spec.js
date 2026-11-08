const { calculateArrangementAmounts } = require('../utils/arrangementFinance');

describe('arrangement advance calculations', () => {
  test('calculates 60 percent advance and exact remainder in PKR', () => {
    expect(calculateArrangementAmounts(5000, 1)).toEqual({ subtotal: 5000, advanceAmount: 3000, remainingAmount: 2000 });
    expect(calculateArrangementAmounts(333.33, 3)).toEqual({ subtotal: 999.99, advanceAmount: 599.99, remainingAmount: 400 });
  });

  test('rejects invalid prices and quantities', () => {
    expect(calculateArrangementAmounts(0, 1)).toBeNull();
    expect(calculateArrangementAmounts(100, 1.5)).toBeNull();
  });
});
