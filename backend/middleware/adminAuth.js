const crypto = require('crypto');
const Admin = require('../models/admin');
const AdminSession = require('../models/adminSession');

const SESSION_COOKIE = 'fxh_admin_session';

function isLoopbackAddress(address) {
  if (!address) return false;
  const normalized = address.replace(/^::ffff:/, '');
  return normalized === '127.0.0.1' || normalized === '::1' || normalized === 'localhost';
}

function isLocalMongoUri(uri) {
  if (!uri) return false;
  try {
    const parsed = new URL(uri);
    return ['localhost', '127.0.0.1', '::1'].includes(parsed.hostname);
  } catch {
    return false;
  }
}

function isLocalDevelopmentMfaBypassAllowed(req) {
  return (
    process.env.NODE_ENV === 'development' &&
    process.env.ADMIN_DEV_SKIP_MFA === 'true' &&
    isLoopbackAddress(req.socket?.localAddress) &&
    isLoopbackAddress(req.socket?.remoteAddress) &&
    isLocalMongoUri(process.env.MONGO_URI)
  );
}

function hasAdminPermission(admin, permission) {
  return admin?.role === 'super_admin' || (admin?.permissions || []).includes(permission);
}

function requireAdminPermission(permission) {
  return (req, res, next) => {
    if (!hasAdminPermission(req.admin, permission)) {
      return res.status(403).json({ code: 'admin_forbidden', message: 'Administrator permission required.' });
    }
    return next();
  };
}

function readCookie(req, name) {
  const header = req.headers.cookie || '';
  const match = header.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

function hashToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex');
}

function cookieOptions() {
  const production = process.env.NODE_ENV === 'production';
  return {
    httpOnly: true,
    secure: production,
    sameSite: production ? 'lax' : 'strict',
    path: '/',
    ...(process.env.ADMIN_COOKIE_DOMAIN ? { domain: process.env.ADMIN_COOKIE_DOMAIN } : {}),
  };
}

async function getSession(req) {
  const token = readCookie(req, SESSION_COOKIE);
  if (!token) return null;
  const session = await AdminSession.findOne({
    tokenHash: hashToken(token),
    expiresAt: { $gt: new Date() },
  });
  if (!session) return null;
  const admin = await Admin.findById(session.adminId).select('+passwordHash +mfaSecretEncrypted +recoveryCodeHashes');
  if (!admin || admin.status !== 'active') return null;
  return { token, session, admin };
}

async function requireAdminSession(req, res, next) {
  try {
    const current = await getSession(req);
    if (!current || current.session.state !== 'authenticated') {
      return res.status(401).json({ code: 'admin_unauthorized', message: 'Administrator authentication required.' });
    }
    req.admin = current.admin;
    req.adminSession = current.session;
    return next();
  } catch (error) {
    console.error('Admin session lookup failed:', error.message);
    return res.status(500).json({ code: 'internal_error', message: 'Unable to verify administrator session.' });
  }
}

async function requirePreMfaSession(req, res, next) {
  try {
    const current = await getSession(req);
    if (!current || current.session.state !== 'pre_mfa') {
      return res.status(401).json({ code: 'mfa_challenge_required', message: 'A valid MFA challenge is required.' });
    }
    req.admin = current.admin;
    req.adminSession = current.session;
    return next();
  } catch (error) {
    console.error('Pre-MFA session lookup failed:', error.message);
    return res.status(500).json({ code: 'internal_error', message: 'Unable to verify MFA challenge.' });
  }
}

module.exports = {
  SESSION_COOKIE,
  cookieOptions,
  getSession,
  hashToken,
  isLocalDevelopmentMfaBypassAllowed,
  isLocalMongoUri,
  isLoopbackAddress,
  readCookie,
  requireAdminSession,
  requireAdminPermission,
  hasAdminPermission,
  requirePreMfaSession,
};
