const crypto = require('crypto');
const Admin = require('../models/admin');
const AdminDevice = require('../models/adminDevice');
const AdminNotification = require('../models/adminNotification');
const Outbox = require('../models/adminNotificationOutbox');

let firebaseApp;
function getFirebase() {
  if (firebaseApp !== undefined) return firebaseApp;
  firebaseApp = null;
  if (process.env.FIREBASE_NOTIFICATIONS_ENABLED !== 'true') return firebaseApp;
  try {
    const admin = require('firebase-admin');
    if (!admin.apps.length) {
      const privateKey = String(process.env.FIREBASE_PRIVATE_KEY || '').replace(/\\n/g, '\n');
      admin.initializeApp({ credential: admin.credential.cert({ projectId: process.env.FIREBASE_PROJECT_ID, clientEmail: process.env.FIREBASE_CLIENT_EMAIL, privateKey }) });
    }
    firebaseApp = admin;
  } catch (error) { console.error('FCM initialization unavailable:', error.message); }
  return firebaseApp;
}

function safeKey(value) { return crypto.createHash('sha256').update(String(value)).digest('hex'); }

async function processOutbox(id) {
  const entry = await Outbox.findOneAndUpdate({ _id: id, status: 'pending' }, { $set: { status: 'processing' }, $inc: { attempts: 1 } }, { new: true });
  if (!entry) return;
  try {
    const admins = await Admin.find({ status: 'active', $or: [{ role: 'super_admin' }, { permissions: 'orders.read' }] }).select('_id').lean();
    const adminIds = admins.map((item) => item._id);
    const devices = await AdminDevice.find({ adminId: { $in: adminIds }, enabled: true }).lean();
    await Promise.all(adminIds.map((adminId) => AdminNotification.updateOne({ adminId, dedupeKey: `${entry.dedupeKey}:${adminId}` }, { $setOnInsert: { adminId, type: entry.type, title: entry.title, body: entry.body, orderId: entry.orderId, data: entry.data, dedupeKey: `${entry.dedupeKey}:${adminId}` } }, { upsert: true })));
    const fcm = getFirebase();
    if (fcm && devices.length) {
      const result = await fcm.messaging().sendEachForMulticast({ tokens: devices.map((item) => item.fcmToken), notification: { title: entry.title, body: entry.body }, data: Object.fromEntries(Object.entries(entry.data || {}).map(([key, value]) => [key, String(value)])), android: { priority: 'high', notification: { channelId: 'orders-high' } } });
      const invalid = [];
      result.responses.forEach((response, index) => { if (!response.success && ['messaging/registration-token-not-registered', 'messaging/invalid-registration-token'].includes(response.error?.code)) invalid.push(devices[index]._id); });
      if (invalid.length) await AdminDevice.updateMany({ _id: { $in: invalid } }, { $set: { enabled: false } });
    }
    await Outbox.updateOne({ _id: entry._id }, { $set: { status: 'sent', deliveredAt: new Date(), lastError: '' } });
  } catch (error) {
    await Outbox.updateOne({ _id: entry._id }, { $set: { status: 'failed', lastError: String(error.message || 'notification_failed').slice(0, 500), nextAttemptAt: new Date(Date.now() + 60_000) } });
    console.error('Admin notification dispatch failed:', error.message);
  }
}

async function enqueueAdminNotification({ type, title, body, orderId = null, data = {}, dedupeKey }) {
  if (!dedupeKey) throw new Error('notification_dedupe_key_required');
  try {
    const entry = await Outbox.create({ dedupeKey: safeKey(dedupeKey), type, title, body, orderId, data });
    setImmediate(() => processOutbox(entry._id).catch(() => {}));
    return entry;
  } catch (error) {
    if (error.code === 11000) return null;
    throw error;
  }
}

async function retryPendingNotifications() {
  const pending = await Outbox.find({ status: { $in: ['pending', 'failed'] }, nextAttemptAt: { $lte: new Date() } }).limit(25).select('_id').lean();
  await Promise.all(pending.map((entry) => processOutbox(entry._id)));
}

module.exports = { enqueueAdminNotification, retryPendingNotifications };
