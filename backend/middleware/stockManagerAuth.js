const crypto = require('crypto');
const StockManagerDevice = require('../models/stockManagerDevice');

const digest = value => crypto.createHash('sha256').update(String(value)).digest('hex');

async function requireStockManagerDevice(req, res, next) {
  const authorization = String(req.get('authorization') || '');
  const token = authorization.startsWith('Bearer ') ? authorization.slice(7).trim() : '';
  const deviceId = String(req.get('x-fxh-device-id') || '').trim();
  if (!token || !deviceId) return res.status(401).json({ message: 'Stock Manager device authentication is required.' });
  const device = await StockManagerDevice.findOne({ deviceId, status: 'active' }).select('+tokenHash');
  if (!device || !crypto.timingSafeEqual(Buffer.from(device.tokenHash), Buffer.from(digest(token)))) return res.status(401).json({ message: 'Invalid or revoked Stock Manager device credentials.' });
  device.lastSeenAt = new Date();
  await device.save();
  req.stockManagerDevice = device;
  return next();
}

module.exports = { digest, requireStockManagerDevice };
