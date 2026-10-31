/*
 * Destructive local reset. This script intentionally refuses every database
 * except the documented loopback fastxhorizon_local database and never calls
 * dropDatabase(). Admin records and store configuration are preserved.
 */
require('dotenv').config();
const mongoose = require('mongoose');

const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';
const parsed = new URL(uri);
const localHosts = new Set(['127.0.0.1', 'localhost', '::1']);
const databaseName = decodeURIComponent(parsed.pathname.replace(/^\//, ''));
if (!['mongodb:', 'mongodb+srv:'].includes(parsed.protocol) || !localHosts.has(parsed.hostname) || databaseName !== 'fastxhorizon_local') {
  throw new Error('Refusing reset: only mongodb://127.0.0.1:27017/fastxhorizon_local is allowed.');
}

const CLEAR_COLLECTIONS = [
  'products', 'categories', 'orders', 'users', 'catalogimports',
  'paymentsubmissions', 'refunds', 'newslettersubscribers',
  'adminauditlogs', 'adminsessions',
];
const PRESERVED_COLLECTIONS = ['admins', 'paymentconfigurations', 'storefrontsettings'];

async function main() {
  await mongoose.connect(uri, { serverSelectionTimeoutMS: 5000 });
  const db = mongoose.connection.db;
  const before = {};
  const deleted = {};
  for (const name of [...CLEAR_COLLECTIONS, ...PRESERVED_COLLECTIONS]) before[name] = await db.collection(name).countDocuments();
  for (const name of CLEAR_COLLECTIONS) deleted[name] = (await db.collection(name).deleteMany({})).deletedCount;
  const after = {};
  for (const name of [...CLEAR_COLLECTIONS, ...PRESERVED_COLLECTIONS]) after[name] = await db.collection(name).countDocuments();
  console.log(JSON.stringify({ uriHost: parsed.hostname, database: db.databaseName, cleared: CLEAR_COLLECTIONS, preserved: PRESERVED_COLLECTIONS, before, deleted, after }, null, 2));
  await mongoose.disconnect();
}

main().catch(async (error) => { console.error(error.message); await mongoose.disconnect().catch(() => {}); process.exit(1); });
