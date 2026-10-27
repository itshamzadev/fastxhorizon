const crypto = require('crypto');
const express = require('express');
const request = require('supertest');

jest.mock('../models/adminSession', () => ({ findOne: jest.fn() }));
jest.mock('../models/admin', () => ({ findById: jest.fn() }));

const AdminSession = require('../models/adminSession');
const Admin = require('../models/admin');
const {
  isLocalDevelopmentMfaBypassAllowed,
  isLocalMongoUri,
  requireAdminSession,
  requirePreMfaSession,
} = require('../middleware/adminAuth');
const { consumeRecoveryCode, generateTotpSecret, totpCode, verifyTotp } = require('../services/adminSecurity');

describe('administrator security boundaries', () => {
  const originalEnv = { ...process.env };

  afterEach(() => {
    process.env = { ...originalEnv };
    jest.clearAllMocks();
  });

  test('local MFA bypass requires development, explicit opt-in, loopback request, and local MongoDB', () => {
    process.env.NODE_ENV = 'development';
    process.env.ADMIN_DEV_SKIP_MFA = 'true';
    process.env.MONGO_URI = 'mongodb://127.0.0.1:27017/fastxhorizon_local';
    const requestLike = { socket: { localAddress: '127.0.0.1', remoteAddress: '::1' } };
    expect(isLocalDevelopmentMfaBypassAllowed(requestLike)).toBe(true);

    requestLike.socket.remoteAddress = '192.168.1.20';
    expect(isLocalDevelopmentMfaBypassAllowed(requestLike)).toBe(false);
    requestLike.socket.remoteAddress = '::1';
    process.env.NODE_ENV = 'production';
    expect(isLocalDevelopmentMfaBypassAllowed(requestLike)).toBe(false);
    expect(isLocalMongoUri('mongodb://api.fastxhorizon.com/prod')).toBe(false);
  });

  test('TOTP accepts the current code and rejects an invalid code', () => {
    const secret = generateTotpSecret();
    const counter = Math.floor(Date.now() / 30000);
    const code = totpCode(secret, counter);
    expect(verifyTotp(secret, code)).toBe(true);
    expect(verifyTotp(secret, `${(Number(code) + 1) % 1000000}`.padStart(6, '0'), 0)).toBe(false);
  });

  test('a recovery code is consumed and cannot be reused', async () => {
    const compare = jest.fn(async (candidate, stored) => candidate === stored);
    const first = await consumeRecoveryCode('FXH-ONE', ['FXH-ONE', 'FXH-TWO'], compare);
    expect(first.valid).toBe(true);
    expect(first.remainingHashes).toEqual(['FXH-TWO']);
    const second = await consumeRecoveryCode('FXH-ONE', first.remainingHashes, compare);
    expect(second.valid).toBe(false);
  });

  test('unauthenticated and pre-MFA sessions cannot access administrator APIs', async () => {
    const app = express();
    app.get('/admin', requireAdminSession, (req, res) => res.json({ ok: true }));
    app.get('/mfa', requirePreMfaSession, (req, res) => res.json({ ok: true }));
    AdminSession.findOne.mockResolvedValue(null);
    expect((await request(app).get('/admin')).status).toBe(401);

    AdminSession.findOne.mockResolvedValue({ state: 'pre_mfa', expiresAt: new Date(Date.now() + 60_000) });
    Admin.findById.mockReturnValue({ select: jest.fn().mockResolvedValue({ status: 'active' }) });
    expect((await request(app).get('/admin').set('Cookie', 'fxh_admin_session=test')).status).toBe(401);
    expect(AdminSession.findOne).toHaveBeenCalledWith(expect.objectContaining({ expiresAt: expect.objectContaining({ $gt: expect.any(Date) }) }));
    expect((await request(app).get('/mfa').set('Cookie', 'fxh_admin_session=test')).status).toBe(200);
  });
});
