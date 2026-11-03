const jwt = require('jsonwebtoken');
const Admin = require('../models/admin');
const { hasAdminPermission } = require('./adminAuth');

function secret() {
  return process.env.MOBILE_ACCESS_TOKEN_SECRET || process.env.JWT_SECRET;
}

async function requireMobileSession(req, res, next) {
  try {
    const authorization = String(req.headers.authorization || '');
    const token = authorization.startsWith('Bearer ') ? authorization.slice(7).trim() : '';
    if (!token || !secret()) return res.status(401).json({ code: 'mobile_unauthorized', message: 'Mobile authentication required.' });
    const payload = jwt.verify(token, secret());
    if (payload.type !== 'mobile_access' || !payload.sub) throw new Error('invalid_mobile_token');
    const admin = await Admin.findById(payload.sub).select('+passwordHash +mfaSecretEncrypted +recoveryCodeHashes');
    if (!admin || admin.status !== 'active') return res.status(401).json({ code: 'mobile_unauthorized', message: 'Administrator session is no longer active.' });
    req.admin = admin;
    req.mobileSessionId = payload.sid;
    return next();
  } catch (error) {
    return res.status(401).json({ code: 'mobile_unauthorized', message: 'Mobile session expired. Please sign in again.' });
  }
}

function requireMobilePermission(permission) {
  return (req, res, next) => hasAdminPermission(req.admin, permission)
    ? next()
    : res.status(403).json({ code: 'admin_forbidden', message: 'Administrator permission required.' });
}

module.exports = { requireMobileSession, requireMobilePermission };
