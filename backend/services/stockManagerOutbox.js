const StockSyncEvent = require('../models/stockSyncEvent');
const Order = require('../models/order');

async function enqueueOrderStockEvents(order, eventType = 'ONLINE_SALE') {
  const items = order.items || [];
  for (const item of items) {
    const eventId = `website-order-${order.orderNumber}-${eventType}-${String(item.productId)}`;
    await StockSyncEvent.updateOne(
      { eventId },
      { $setOnInsert: { eventId, idempotencyKey: eventId, source: 'WEBSITE', eventType, entityType: 'order', entityId: String(order._id), payload: { remoteProductId: String(item.productId), quantity: item.quantity, orderNumber: order.orderNumber } } },
      { upsert: true },
    );
  }
  await Order.updateOne({ _id: order._id }, { $set: { stockSyncState: 'APPLIED', stockSyncLastError: '', updatedAt: new Date() } });
  return true;
}

async function markOrderStockSyncFailed(order, error) {
  await Order.updateOne({ _id: order._id }, { $set: { stockSyncState: 'ERROR', stockSyncLastError: String(error?.message || error).slice(0, 500), updatedAt: new Date() } });
}

async function retryPendingOrderStockEvents() {
  const orders = await Order.find({ stockSyncState: { $in: ['PENDING', 'ERROR'] } }).sort({ createdAt: 1 }).limit(100);
  let processed = 0;
  for (const order of orders) {
    try { await enqueueOrderStockEvents(order); processed += 1; } catch (error) { await markOrderStockSyncFailed(order, error); }
  }
  return processed;
}

module.exports = { enqueueOrderStockEvents, markOrderStockSyncFailed, retryPendingOrderStockEvents };
