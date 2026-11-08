const express = require('express');
const request = require('supertest');
const mongoose = require('mongoose');
const fs = require('fs');
const os = require('os');
const path = require('path');
const syncRouter = require('../routes/stockManagerSync');
const catalogRouter = require('../routes/stockManagerCatalog');
const Product = require('../models/product');
const StockManagerIdentity = require('../models/stockManagerIdentity');
const StockSyncEvent = require('../models/stockSyncEvent');

const URI = process.env.STOCK_MANAGER_E2E_MONGO_URI || '';
const E2E_DB = 'fastxhorizon_e2e';
const canRun = Boolean(URI);

function assertSafeDatabase() {
  if (!URI) throw new Error('STOCK_MANAGER_E2E_MONGO_URI is required for the Mongo integration suite.');
  const parsed = new URL(URI);
  if (parsed.pathname.replace(/^\//, '') !== E2E_DB) throw new Error(`Refusing integration tests outside ${E2E_DB}.`);
  if (parsed.pathname.includes('production')) throw new Error('Production database is forbidden in integration tests.');
}

describe('Stock Manager MongoDB synchronization (isolated)', () => {
  const run = canRun ? test : test.skip;
  const suffix = `e2e-sync-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  let app;
  let auth;
  let product;
  const mediaDir = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-catalog-media-'));

  beforeAll(async () => {
    if (!canRun) return;
    assertSafeDatabase();
    process.env.STOCK_MANAGER_REGISTRATION_SECRET = `secret-${suffix}`;
    process.env.MEDIA_STORAGE_ROOT = mediaDir;
    await mongoose.connect(URI, { serverSelectionTimeoutMS: 5000 });
    if (mongoose.connection.name !== E2E_DB) throw new Error(`Connected to unexpected database ${mongoose.connection.name}`);
    app = express();
    app.use(express.json());
    app.use('/api/stock-manager', syncRouter);
    app.use('/api/stock-manager/catalog', catalogRouter);
    const response = await request(app).post('/api/stock-manager/devices/register')
      .set('x-fxh-registration-secret', process.env.STOCK_MANAGER_REGISTRATION_SECRET)
      .send({ deviceId: suffix, deviceName: 'E2E Stock Manager' });
    expect(response.status).toBe(201);
    auth = { authorization: `Bearer ${response.body.data.token}`, 'x-fxh-device-id': suffix };
  });

  afterAll(async () => {
    if (!canRun) return;
    await Product.deleteMany({ supplierKey: { $regex: `^FXH_STOCK_MANAGER:${suffix}` } });
    await StockManagerIdentity.deleteMany({ localEntityId: { $regex: `^${suffix}` } });
    await StockSyncEvent.deleteMany({ sourceDeviceId: suffix });
    await mongoose.disconnect();
    fs.rmSync(mediaDir, { recursive: true, force: true });
  });

  run('creates, maps, updates, receives and idempotently sells a local product', async () => {
    const localProductId = `${suffix}:product`;
    const localVariantId = `${suffix}:variant`;
    const create = await request(app).post('/api/stock-manager/sync/push').set(auth).send({ events: [{
      eventId: `${suffix}:create`, idempotencyKey: `${suffix}:create`, eventType: 'PRODUCT_CREATE', entityType: 'product', entityId: localProductId,
      payload: { localProductId, localSku: `${suffix}:SKU`, name: 'E2E Sync Product', category: 'Clothing', brandName: 'E2E Brand', fabricMaterial: 'SHAMRAY', clothingType: 'Suit', description: 'Isolated test fixture', variants: [{ id: localVariantId, sku: `${suffix}:VAR`, color: 'Black', size: 'M', quantity_on_hand: 0, selling_price: 3000, original_price: 3500, image_paths: [] }] },
    }] });
    expect(create.body.data.results[0].status).toBe('APPLIED');
    product = await Product.findOne({ supplierKey: `FXH_STOCK_MANAGER:${localProductId}` });
    expect(product).toBeTruthy();
    expect(product.fabricMaterial).toBe('SHAMRAY');
    expect(product.variants[0].options.color).toBe('Black');

    const receipt = await request(app).post('/api/stock-manager/sync/push').set(auth).send({ events: [{
      eventId: `${suffix}:receipt`, idempotencyKey: `${suffix}:receipt`, eventType: 'STOCK_RECEIPT', entityType: 'variant', entityId: localVariantId,
      payload: { localProductId, localVariantId, quantity: 1, reason: 'E2E receiving' },
    }] });
    expect(receipt.body.data.results[0].status).toBe('APPLIED');
    product = await Product.findById(product._id);
    expect(product.variants[0].stock).toBe(1);

    const sale = { events: [{ eventId: `${suffix}:sale`, idempotencyKey: `${suffix}:sale`, eventType: 'OFFLINE_SALE', entityType: 'sale', entityId: `${suffix}:invoice`, payload: { localProductId, localVariantId, quantity: 1, reason: 'E2E offline sale' } }] };
    const firstSale = await request(app).post('/api/stock-manager/sync/push').set(auth).send(sale);
    expect(firstSale.body.data.results[0].status).toBe('APPLIED');
    const retrySale = await request(app).post('/api/stock-manager/sync/push').set(auth).send(sale);
    expect(retrySale.body.data.results[0].idempotent).toBe(true);
    product = await Product.findById(product._id);
    expect(product.variants[0].stock).toBe(0);

    const update = await request(app).post('/api/stock-manager/sync/push').set(auth).send({ events: [{
      eventId: `${suffix}:update`, idempotencyKey: `${suffix}:update`, eventType: 'PRODUCT_UPDATE', entityType: 'product', entityId: localProductId,
      payload: { localProductId, name: 'E2E Sync Product Updated', brandName: 'E2E Brand 2', fabricMaterial: 'Khaddar', variants: [{ id: localVariantId, selling_price: 3200, original_price: 3600, color: 'Navy', size: 'L', image_paths: [] }] },
    }] });
    expect(update.body.data.results[0].status).toBe('APPLIED');
    product = await Product.findById(product._id);
    expect(product.name).toBe('E2E Sync Product Updated');
    expect(product.variants[0].price).toBe(3200);

    const archive = await request(app).post('/api/stock-manager/sync/push').set(auth).send({ events: [{ eventId: `${suffix}:archive`, idempotencyKey: `${suffix}:archive`, eventType: 'PRODUCT_ARCHIVED', entityType: 'product', entityId: localProductId, payload: { localProductId } }] });
    expect(archive.body.data.results[0].status).toBe('APPLIED');
    product = await Product.findById(product._id);
    expect(product.status).toBe('archived');
  }, 30000);

  run('publishes catalog idempotently without overwriting operational stock', async () => {
    const localProductId = `${suffix}:catalog-product`;
    const payload = { localProductId, localRevision: 1, sku: `${suffix}:CATALOG`, name: 'Catalog-only fixture', description: 'Draft locally first', category: 'Clothing', brandName: 'Catalog Brand', fabricMaterial: 'Linen', clothingType: 'Shirt', status: 'active', variants: [{ id: `${suffix}:catalog-variant`, sku: `${suffix}:CATALOG-V`, color: 'White', size: 'M', quantity: 3, sellingPrice: 2400, compareAtPrice: 3000, images: [] }] };
    const first = await request(app).post('/api/stock-manager/catalog/upsert').set(auth).send({ product: payload });
    expect(first.status).toBe(200);
    const remoteId = first.body.data.remoteProductId;
    const created = await Product.findById(remoteId);
    expect(created.status).toBe('active');
    expect(created.variants[0].stock).toBe(3);
    created.variants[0].stock = 1; await created.save();
    const repeated = await request(app).post('/api/stock-manager/catalog/upsert').set(auth).send({ product: { ...payload, remoteVersion: first.body.data.remoteVersion } });
    expect(repeated.status).toBe(200);
    const afterRepeat = await Product.findById(remoteId);
    expect(afterRepeat.variants[0].stock).toBe(1);
    expect(await Product.countDocuments({ supplierKey: `FXH_STOCK_MANAGER:${localProductId}` })).toBe(1);
    const conflict = await request(app).post('/api/stock-manager/catalog/upsert').set(auth).send({ product: { ...payload, localRevision: 2, remoteVersion: 0, name: 'Stale catalog update' } });
    expect(conflict.status).toBe(409);
  }, 30000);

  run('accepts only signed product image content and deduplicates by hash', async () => {
    const png = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
    const first = await request(app).post('/api/stock-manager/catalog/media').set(auth).attach('files', png, 'fixture.png');
    expect(first.status).toBe(200);
    expect(first.body.data.files[0].url).toMatch(/^\/media\/products\//);
    const second = await request(app).post('/api/stock-manager/catalog/media').set(auth).attach('files', png, 'another-name.png');
    expect(second.status).toBe(200);
    expect(second.body.data.files[0].filename).toBe(first.body.data.files[0].filename);
    const rejected = await request(app).post('/api/stock-manager/catalog/media').set(auth).attach('files', Buffer.from('not an image'), 'bad.png');
    expect(rejected.status).toBe(415);
  }, 30000);
});
