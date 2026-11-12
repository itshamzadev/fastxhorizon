function calculateArrangementAmounts(unitPrice, quantity) {
  const price = Number(unitPrice);
  const qty = Number(quantity);
  if (!Number.isFinite(price) || price <= 0 || !Number.isInteger(qty) || qty < 1) return null;
  const subtotal = Math.round(price * qty * 100) / 100;
  const advanceAmount = Math.round(subtotal * 0.5 * 100) / 100;
  return { subtotal, advanceAmount, remainingAmount: Math.round((subtotal - advanceAmount) * 100) / 100 };
}

module.exports = { calculateArrangementAmounts };
