const bcrypt = require('bcryptjs');
const express = require('express');
const request = require('supertest');

jest.mock('../models/admin', () => ({ findOne: jest.fn() }));
jest.mock('../models/adminSession', () => ({ create: jest.fn() }));
jest.mock('../models/adminAuditLog', () => ({ create: jest.fn().mockResolvedValue({}) }));

const Admin = require('../models/admin');
const AdminSession = require('../models/adminSession');
const adminAuthRouter = require('../routes/adminAuth');

function queryResult(value) {
  return { select: jest.fn().mockResolvedValue(value) };
}

describe('administrator authentication', () => {
  const originalEnv = { ...process.env };
  let app;

  beforeEach(() => {
    process.env = { ...originalEnv, NODE_ENV: 'production', MONGO_URI: 'mongodb://127.0.0.1:27017/test' };
    app = express();
    app.use(express.json());
    app.use('/api/admin/auth', adminAuthRouter);
    AdminSession.create.mockResolvedValue({ _id: 'session-1' });
  });

  afterEach(() => {
    process.env = { ...originalEnv };
    jest.clearAllMocks();
  });

  test('wrong passwords are rejected', async () => {
    Admin.findOne.mockReturnValue(queryResult({
      _id: 'admin-1', email: 'admin@example.com', passwordHash: awaitHash('correct-password'), status: 'active',
    }));
    const response = await request(app).post('/api/admin/auth/login').send({ email: 'admin@example.com', password: 'wrong-password' });
    expect(response.status).toBe(401);
    expect(AdminSession.create).not.toHaveBeenCalled();
  });

  test('production password verification alone does not create an authenticated session', async () => {
    const passwordHash = awaitHash('correct-password');
    const admin = { _id: 'admin-1', email: 'admin@example.com', passwordHash, status: 'active', mfaSecretEncrypted: null, save: jest.fn() };
    Admin.findOne.mockReturnValue(queryResult(admin));
    const response = await request(app).post('/api/admin/auth/login').send({ email: admin.email, password: 'correct-password' });
    expect(response.status).toBe(200);
    expect(response.body).toEqual({ mfaRequired: true, enrollmentRequired: true });
    expect(AdminSession.create).toHaveBeenCalledWith(expect.objectContaining({ state: 'pre_mfa' }));
  });
});

function awaitHash(value) {
  // bcrypt hashes are deterministic enough for this test fixture and generated before request handling.
  return bcrypt.hashSync(value, 4);
}
