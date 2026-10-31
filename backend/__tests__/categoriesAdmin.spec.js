const express = require('express');
const request = require('supertest');

jest.mock('../models/adminSession', () => ({ findOne: jest.fn() }));
jest.mock('../models/admin', () => ({ findById: jest.fn() }));
jest.mock('../models/category', () => ({
  find: jest.fn(), findOne: jest.fn(), findById: jest.fn(), findByIdAndUpdate: jest.fn(),
  countDocuments: jest.fn(), exists: jest.fn(), create: jest.fn(), deleteOne: jest.fn(),
}));
jest.mock('../models/product', () => ({ countDocuments: jest.fn(), exists: jest.fn(), find: jest.fn() }));
jest.mock('../models/adminAuditLog', () => ({ create: jest.fn().mockResolvedValue({}) }));
jest.mock('../services/catalogImport', () => ({ SUPPLIER: 'YOURMART', safeFilename: jest.fn(), buildEntries: jest.fn(), makeProductDocument: jest.fn() }));

const AdminSession = require('../models/adminSession');
const Admin = require('../models/admin');
const Category = require('../models/category');
const Product = require('../models/product');
const adminRoutes = require('../routes/admin');

function appFor(role = 'super_admin') {
  const app = express();
  app.use(express.json());
  AdminSession.findOne.mockResolvedValue({ state: 'authenticated', adminId: 'admin-1', expiresAt: new Date(Date.now() + 60_000) });
  Admin.findById.mockReturnValue({ select: jest.fn().mockResolvedValue({ _id: 'admin-1', email: 'admin@example.test', name: 'Admin', role, status: 'active', permissions: [] }) });
  app.use('/api/admin', adminRoutes);
  return app;
}

describe('Super Admin category management', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    Category.find.mockReturnValue({ sort: jest.fn().mockReturnValue({ lean: jest.fn().mockResolvedValue([]) }) });
    Category.countDocuments.mockResolvedValue(0);
    Product.countDocuments.mockResolvedValue(0);
    Product.exists.mockResolvedValue(false);
  });

  test('only Super Admin can read category management', async () => {
    expect((await request(appFor('admin')).get('/api/admin/categories').set('Cookie', 'fxh_admin_session=test')).status).toBe(403);
    expect((await request(appFor('super_admin')).get('/api/admin/categories').set('Cookie', 'fxh_admin_session=test')).status).toBe(200);
  });

  test('rejects a subcategory whose parent is not top-level', async () => {
    Category.exists.mockResolvedValue(false);
    const response = await request(appFor('super_admin')).post('/api/admin/categories').set('Cookie', 'fxh_admin_session=test').send({ name: 'Nested', slug: 'nested', parentSlug: 'child-parent' });
    expect(response.status).toBe(400);
    expect(response.body.message).toMatch(/top-level category/i);
  });

  test('creates a valid category and rejects duplicate scoped slugs', async () => {
    Category.exists.mockResolvedValueOnce(false);
    Category.create.mockResolvedValue({ _id: 'cat-1', name: 'Electronics', slug: 'electronics', parentSlug: null, active: true });
    expect((await request(appFor('super_admin')).post('/api/admin/categories').set('Cookie', 'fxh_admin_session=test').send({ name: 'Electronics' })).status).toBe(201);
    Category.exists.mockResolvedValueOnce(true);
    expect((await request(appFor('super_admin')).post('/api/admin/categories').set('Cookie', 'fxh_admin_session=test').send({ name: 'Electronics', slug: 'electronics' })).status).toBe(409);
  });
});
