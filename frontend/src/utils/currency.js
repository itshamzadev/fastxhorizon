const pkrNumberFormatter = new Intl.NumberFormat('en-PK', { maximumFractionDigits: 2 });

export function formatPrice(value) {
  const amount = Number(value);
  if (!Number.isFinite(amount)) return 'Price unavailable';
  return `Rs. ${pkrNumberFormatter.format(amount)}`;
}

export default formatPrice;
