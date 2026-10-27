const crypto = require('crypto');

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function encryptionKey() {
  const configured = process.env.ADMIN_TOTP_ENCRYPTION_KEY;
  if (!configured) throw new Error('ADMIN_TOTP_ENCRYPTION_KEY is required for MFA secret encryption.');
  return crypto.createHash('sha256').update(configured).digest();
}

function encryptSecret(value) {
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', encryptionKey(), iv);
  const encrypted = Buffer.concat([cipher.update(value, 'utf8'), cipher.final()]);
  return [iv.toString('base64url'), cipher.getAuthTag().toString('base64url'), encrypted.toString('base64url')].join('.');
}

function decryptSecret(value) {
  const [ivText, tagText, encryptedText] = String(value).split('.');
  const decipher = crypto.createDecipheriv('aes-256-gcm', encryptionKey(), Buffer.from(ivText, 'base64url'));
  decipher.setAuthTag(Buffer.from(tagText, 'base64url'));
  return Buffer.concat([decipher.update(Buffer.from(encryptedText, 'base64url')), decipher.final()]).toString('utf8');
}

function base32Encode(buffer) {
  let bits = 0;
  let value = 0;
  let output = '';
  for (const byte of buffer) {
    value = (value << 8) | byte;
    bits += 8;
    while (bits >= 5) {
      output += BASE32_ALPHABET[(value >>> (bits - 5)) & 31];
      bits -= 5;
    }
  }
  if (bits > 0) output += BASE32_ALPHABET[(value << (5 - bits)) & 31];
  return output;
}

function base32Decode(input) {
  let bits = 0;
  let value = 0;
  const output = [];
  for (const char of input.replace(/=+$/, '').toUpperCase()) {
    const index = BASE32_ALPHABET.indexOf(char);
    if (index < 0) throw new Error('Invalid base32 secret.');
    value = (value << 5) | index;
    bits += 5;
    if (bits >= 8) {
      output.push((value >>> (bits - 8)) & 255);
      bits -= 8;
    }
  }
  return Buffer.from(output);
}

function generateTotpSecret() {
  return base32Encode(crypto.randomBytes(20));
}

function totpCode(secret, counter) {
  const buffer = Buffer.alloc(8);
  buffer.writeUInt32BE(Math.floor(counter / 0x100000000), 0);
  buffer.writeUInt32BE(counter >>> 0, 4);
  const digest = crypto.createHmac('sha1', base32Decode(secret)).update(buffer).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const binary = ((digest[offset] & 0x7f) << 24) | (digest[offset + 1] << 16) | (digest[offset + 2] << 8) | digest[offset + 3];
  return String(binary % 1000000).padStart(6, '0');
}

function verifyTotp(secret, code, window = 1) {
  if (!/^\d{6}$/.test(String(code))) return false;
  const counter = Math.floor(Date.now() / 30000);
  return Array.from({ length: window * 2 + 1 }, (_, index) => counter + index - window)
    .some((candidate) => crypto.timingSafeEqual(Buffer.from(totpCode(secret, candidate)), Buffer.from(String(code))));
}

function recoveryCodes(count = 10) {
  return Array.from({ length: count }, () => `FXH-${crypto.randomBytes(5).toString('hex').toUpperCase()}`);
}

async function consumeRecoveryCode(code, hashes, compare) {
  for (let index = 0; index < hashes.length; index += 1) {
    if (await compare(code, hashes[index])) {
      return { valid: true, remainingHashes: hashes.filter((_, itemIndex) => itemIndex !== index) };
    }
  }
  return { valid: false, remainingHashes: hashes };
}

function publicAdmin(admin) {
  return {
    id: String(admin._id || admin.id),
    name: admin.name,
    email: admin.email,
    role: admin.role,
    permissions: admin.permissions || [],
    status: admin.status,
    lastLoginAt: admin.lastLoginAt || null,
    createdAt: admin.createdAt,
    updatedAt: admin.updatedAt,
  };
}

module.exports = {
  decryptSecret,
  consumeRecoveryCode,
  encryptSecret,
  generateTotpSecret,
  publicAdmin,
  recoveryCodes,
  totpCode,
  verifyTotp,
};
