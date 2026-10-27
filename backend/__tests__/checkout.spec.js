const express = require('express');
const bodyParser = require('body-parser');
const request = require('supertest');
jest.mock('../models/paymentConfiguration', () => ({ findOne: jest.fn() }));
const PaymentConfiguration = require('../models/paymentConfiguration');
const checkoutRouter = require('../routes/checkout');

describe('POST /api/checkout/create-order payment guards', () => {
  let app;
  beforeEach(() => { PaymentConfiguration.findOne.mockReturnValue({ lean: jest.fn().mockResolvedValue({ automaticEnabled: false, manualEnabled: true, codEnabled: false, manualMethods: [] }) }); app = express(); app.use(bodyParser.json()); app.use('/api/checkout', checkoutRouter); });
  test('rejects missing fields', async () => { const res = await request(app).post('/api/checkout/create-order').send({ items: [], email: 'a@b.com' }); expect(res.status).toBe(400); });
  test('rejects automatic payment while disabled', async () => { const res = await request(app).post('/api/checkout/create-order').send({ items: [{ productId: 'p1', quantity: 1 }], name: 'Test', email: 'test@example.com', shippingAddress: 'Addr', paymentMethod: 'automatic' }); expect(res.status).toBe(400); });
  test('rejects COD while disabled', async () => { const res = await request(app).post('/api/checkout/create-order').send({ items: [{ productId: 'p1', quantity: 1 }], name: 'Test', email: 'test@example.com', shippingAddress: 'Addr', paymentMethod: 'cod' }); expect(res.status).toBe(400); });
  test('rejects invalid email before order work', async () => { const res = await request(app).post('/api/checkout/create-order').send({ items: [{ productId: 'p1', quantity: 1 }], name: 'Test', email: 'invalid', shippingAddress: 'Addr', paymentMethod: 'manual' }); expect(res.status).toBe(400); });
});
