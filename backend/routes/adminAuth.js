const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const QRCode = require('qrcode');
const express = require('express');
const { check, validationResult } = require('express-validator');
const Admin = require('../models/admin');
const AdminSession = require('../models/adminSession');
const AdminAuditLog = require('../models/adminAuditLog');
const {
  SESSION_COOKIE,
  cookieOptions,
  getSession,
  hashToken,
  isLocalDevelopmentMfaBypassAllowed,
  requireAdminSession,
  requirePreMfaSession,
} = require('../middleware/adminAuth');
const {
  decryptSecret,
  consumeRecoveryCode,
  encryptSecret,
  generateTotpSecret,
  publicAdmin,
  recoveryCodes,
  verifyTotp,
} = require('../services/adminSecurity');

const router = express.Router();
const loginAttempts = new Map();
const PRE_MFA_TTL_MS = 5 * 60 * 1000;
const AUTH_SESSION_TTL_MS = 8 * 60 * 60 * 1000;

function rateLimit(key, limit = 8, windowMs = 15 * 60 * 1000) {
  const now = Date.now();
  const current = loginAttempts.get(key);
  if (!current || current.resetAt <= now) {
    loginAttempts.set(key, { count: 1, resetAt: now + windowMs });
    return null;
  }
  current.count += 1;
  if (current.count > limit) return Math.ceil((current.resetAt - now) / 1000);
  return null;
}

function clientKey(req, email = '') {
  return `${req.ip || req.socket?.remoteAddress || 'unknown'}:${String(email).toLowerCase()}`;
}

function cookieString(value, maxAgeSeconds) {
  const options = cookieOptions();
  const parts = [`${SESSION_COOKIE}=${encodeURIComponent(value)}`, `Max-Age=${maxAgeSeconds}`, `Path=${options.path}`];
  if (options.domain) parts.push(`Domain=${options.domain}`);
  if (options.httpOnly) parts.push('HttpOnly');
  if (options.secure) parts.push('Secure');
  parts.push(`SameSite=${options.sameSite === 'strict' ? 'Strict' : 'Lax'}`);
  return parts.join('; ');
}

function setSessionCookie(res, token, maxAgeMs) {
  res.setHeader('Set-Cookie', cookieString(token, Math.floor(maxAgeMs / 1000)));
}

function clearSessionCookie(res) {
  res.setHeader('Set-Cookie', cookieString('', 0));
}

function audit(req, values) {
  return AdminAuditLog.create({
    ...values,
    ip: req.ip || req.socket?.remoteAddress,
    userAgent: req.get('user-agent'),
  }).catch((error) => console.error('Admin audit log failed:', error.message));
}

async function createSession(adminId, state, expiresAt) {
  const token = crypto.randomBytes(32).toString('base64url');
  const session = await AdminSession.create({
    tokenHash: hashToken(token),
    adminId,
    state,
    expiresAt,
  });
  return { token, session };
}

function validationError(req, res) {
  const errors = validationResult(req);
  if (!errors.isEmpty()) {
    res.status(422).json({ code: 'validation_error', message: 'Please fix the highlighted fields.', errors: errors.array() });
    return true;
  }
  return false;
}

router.post(
  '/login',
  [check('email').isEmail(), check('password').isString().isLength({ min: 8, max: 200 })],
  async (req, res) => {
    const email = String(req.body?.email || '').trim().toLowerCase();
    const retryAfter = rateLimit(clientKey(req, email));
    if (retryAfter) {
      res.set('Retry-After', String(retryAfter));
      return res.status(429).json({ code: 'rate_limited', message: 'Too many login attempts. Try again later.' });
    }
    if (validationError(req, res)) return;

    try {
      const admin = await Admin.findOne({ email }).select('+passwordHash +mfaSecretEncrypted +recoveryCodeHashes');
      const passwordOk = admin ? await bcrypt.compare(req.body.password, admin.passwordHash) : false;
      if (!admin || !passwordOk || admin.status !== 'active') {
        await audit(req, { email, action: 'login', success: false });
        return res.status(401).json({ code: 'invalid_credentials', message: 'Invalid administrator credentials.' });
      }

      const bypass = isLocalDevelopmentMfaBypassAllowed(req);
      const state = bypass ? 'authenticated' : 'pre_mfa';
      const ttl = bypass ? AUTH_SESSION_TTL_MS : PRE_MFA_TTL_MS;
      const { token, session } = await createSession(admin._id, state, new Date(Date.now() + ttl));
      setSessionCookie(res, token, ttl);

      if (bypass) {
        admin.lastLoginAt = new Date();
        await admin.save();
        await audit(req, { adminId: admin._id, email, action: 'login_dev_mfa_bypass', success: true });
        return res.json({ user: publicAdmin(admin), mfaRequired: false });
      }

      await audit(req, { adminId: admin._id, email, action: 'password_verified', success: true });
      return res.json({ mfaRequired: true, enrollmentRequired: !admin.mfaSecretEncrypted });
    } catch (error) {
      console.error('Admin login failed:', error.message);
      return res.status(500).json({ code: 'internal_error', message: 'Unable to complete administrator login.' });
    }
  },
);

router.get('/mfa/enroll', requirePreMfaSession, async (req, res) => {
  try {
    if (req.admin.mfaSecretEncrypted) {
      return res.status(409).json({ code: 'mfa_already_enrolled', message: 'MFA is already enrolled.' });
    }
    const secret = generateTotpSecret();
    const encryptedSecret = encryptSecret(secret);
    const codes = recoveryCodes();
    req.adminSession.pendingMfaSecretEncrypted = encryptedSecret;
    req.adminSession.pendingRecoveryCodeHashes = await Promise.all(codes.map((code) => bcrypt.hash(code, 12)));
    await req.adminSession.save();
    const issuer = process.env.ADMIN_TOTP_ISSUER || 'FASTXHORIZON Admin';
    const label = `${issuer}:${req.admin.email}`;
    const otpauthUrl = `otpauth://totp/${encodeURIComponent(label)}?secret=${secret}&issuer=${encodeURIComponent(issuer)}&algorithm=SHA1&digits=6&period=30`;
    const qrCode = await QRCode.toDataURL(otpauthUrl, { errorCorrectionLevel: 'M' });
    return res.json({ qrCode, otpauthUrl, recoveryCodes: codes });
  } catch (error) {
    console.error('MFA enrollment failed:', error.message);
    return res.status(500).json({ code: 'mfa_enrollment_unavailable', message: 'MFA enrollment is not configured.' });
  }
});

router.post('/mfa/verify', [check('code').optional().isString(), check('recoveryCode').optional().isString()], async (req, res, next) => {
  const current = await getSession(req).catch(next);
  if (!current || current.session.state !== 'pre_mfa') {
    return res.status(401).json({ code: 'mfa_challenge_expired', message: 'The MFA challenge has expired. Sign in again.' });
  }
  req.admin = current.admin;
  req.adminSession = current.session;
  req.adminSessionToken = current.token;
  return next();
}, async (req, res) => {
  const retryAfter = rateLimit(`${clientKey(req, req.admin.email)}:mfa`, 6, 15 * 60 * 1000);
  if (retryAfter) {
    res.set('Retry-After', String(retryAfter));
    return res.status(429).json({ code: 'rate_limited', message: 'Too many MFA attempts. Try again later.' });
  }
  const code = String(req.body?.code || '');
  const recoveryCode = String(req.body?.recoveryCode || '').trim().toUpperCase();
  let verified = false;
  let recoveryResult = { valid: false, remainingHashes: req.admin.recoveryCodeHashes };
  let secret;
  try {
    const encrypted = req.adminSession.pendingMfaSecretEncrypted || req.admin.mfaSecretEncrypted;
    if (encrypted && code) secret = decryptSecret(encrypted);
    verified = Boolean(secret && verifyTotp(secret, code));
    if (!verified && recoveryCode && req.admin.mfaSecretEncrypted) {
      recoveryResult = await consumeRecoveryCode(recoveryCode, req.admin.recoveryCodeHashes, bcrypt.compare);
      verified = recoveryResult.valid;
    }
  } catch (error) {
    console.error('MFA verification failed:', error.message);
  }
  if (!verified) {
    await audit(req, { adminId: req.admin._id, email: req.admin.email, action: 'mfa_verify', success: false });
    return res.status(401).json({ code: 'invalid_mfa', message: 'Invalid MFA code or recovery code.' });
  }

  req.admin.recoveryCodeHashes = recoveryResult.remainingHashes;
  if (!req.admin.mfaSecretEncrypted) {
    req.admin.mfaSecretEncrypted = req.adminSession.pendingMfaSecretEncrypted;
    req.admin.recoveryCodeHashes = req.adminSession.pendingRecoveryCodeHashes;
    req.admin.mfaEnrolledAt = new Date();
  }
  req.admin.lastLoginAt = new Date();
  await req.admin.save();
  req.adminSession.state = 'authenticated';
  req.adminSession.pendingMfaSecretEncrypted = null;
  req.adminSession.pendingRecoveryCodeHashes = [];
  req.adminSession.expiresAt = new Date(Date.now() + AUTH_SESSION_TTL_MS);
  await req.adminSession.save();
  setSessionCookie(res, req.adminSessionToken, AUTH_SESSION_TTL_MS);
  await audit(req, { adminId: req.admin._id, email: req.admin.email, action: 'mfa_verify', success: true });
  return res.json({ user: publicAdmin(req.admin) });
});

router.get('/me', requireAdminSession, (req, res) => res.json({ user: publicAdmin(req.admin) }));

router.patch('/me', requireAdminSession, async (req, res, next) => {
  try {
    const name = String(req.body?.name || '').trim();
    const email = String(req.body?.email || '').trim().toLowerCase();
    if (name.length < 2 || name.length > 120 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      return res.status(400).json({ code: 'validation_error', message: 'A valid name and email address are required.' });
    }

    const existing = await Admin.findOne({ email }).select('_id').lean();
    if (existing && String(existing._id) !== String(req.admin._id)) {
      return res.status(409).json({ code: 'conflict', message: 'That email address is already in use.' });
    }

    req.admin.name = name;
    req.admin.email = email;
    await req.admin.save();
    await audit(req, { adminId: req.admin._id, email: req.admin.email, action: 'profile_updated', success: true });
    return res.json({ user: publicAdmin(req.admin) });
  } catch (error) {
    if (error.code === 11000) return res.status(409).json({ code: 'conflict', message: 'That email address is already in use.' });
    return next(error);
  }
});

router.post('/logout', async (req, res) => {
  const current = await getSession(req).catch(() => null);
  if (current) {
    await AdminSession.deleteOne({ _id: current.session._id });
    await audit(req, { adminId: current.admin._id, email: current.admin.email, action: 'logout', success: true });
  }
  clearSessionCookie(res);
  return res.status(204).send();
});

module.exports = router;
