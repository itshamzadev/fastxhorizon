const express = require('express');
const request = require('supertest');

jest.mock('../models/adminSession', () => ({ findOne: jest.fn() }));
jest.mock('../models/admin', () => ({ findById: jest.fn() }));
jest.mock('../models/order', () => ({ aggregate: jest.fn() }));
jest.mock('../models/user', () => ({ countDocuments: jest.fn() }));
jest.mock('../models/product', () => ({ countDocuments: jest.fn() }));
jest.mock('../models/adminAuditLog', () => ({ create: jest.fn() }));
jest.mock('../models/category', () => ({}));
jest.mock('../models/catalogImport', () => ({}));
jest.mock('../models/paymentConfiguration', () => ({}));
jest.mock('../models/storefrontSetting', () => ({}));
jest.mock('../models/newsletterSubscriber', () => ({}));
jest.mock('../models/refund', () => ({}));
jest.mock('../services/catalogImport', () => ({ SUPPLIER: 'YOURMART', safeFilename: jest.fn(), buildEntries: jest.fn(), makeProductDocument: jest.fn() }));

const AdminSession = require('../models/adminSession');
const Admin = require('../models/admin');
const Order = require('../models/order');
const User = require('../models/user');
const Product = require('../models/product');
const adminRoutes = require('../routes/admin');

function testApp() {
  const app = express();
  app.use(express.json());
  app.use('/api/admin', adminRoutes);
  return app;
}

describe('admin analytics API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    AdminSession.findOne.mockResolvedValue({ state: 'authenticated', adminId: 'admin-1', expiresAt: new Date(Date.now() + 60_000) });
    Admin.findById.mockReturnValue({ select: jest.fn().mockResolvedValue({ _id: 'admin-1', email: 'admin@example.test', name: 'Admin', role: 'super_admin', status: 'active', permissions: [] }) });
    Order.aggregate.mockResolvedValue([]);
    User.countDocuments.mockResolvedValue(0);
    Product.countDocuments.mockResolvedValue(0);
  });

  test('requires an authenticated admin session', async () => {
    const response = await request(testApp()).get('/api/admin/dashboard/analytics?range=30d');
    expect(response.status).toBe(401);
  });

  test('returns a valid zero-valued response when collections are empty', async () => {
    const response = await request(testApp()).get('/api/admin/dashboard/analytics?range=30d').set('Cookie', 'fxh_admin_session=test');
    expect(response.status).toBe(200);
    expect(response.body.data.orderTotals).toEqual(expect.objectContaining({ orders: 0, gmv: 0, revenue: 0, completed: 0, pending: 0, cancelledOrders: 0 }));
    expect(response.body.data.dailySales).toEqual([]);
    expect(response.body.data.topProducts).toEqual([]);
    expect(response.body.data.catalog).toEqual(expect.objectContaining({ total: 0, published: 0 }));
  });
});
