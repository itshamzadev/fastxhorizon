require('dotenv').config();

const readline = require('readline');
const bcrypt = require('bcryptjs');
const mongoose = require('mongoose');
const Admin = require('../models/admin');
const { isLocalMongoUri } = require('../middleware/adminAuth');

function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
}

function isPrivateIp(hostname) {
  const parts = hostname.split('.').map(Number);
  if (parts.length !== 4 || parts.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) return false;
  return parts[0] === 10 || (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) || (parts[0] === 192 && parts[1] === 168);
}

function isPrivateMongoHost(hostname, env) {
  const host = String(hostname || '').toLowerCase().replace(/\.$/, '');
  if (!host || ['localhost', '127.0.0.1', '::1'].includes(host)) return false;
  if (isPrivateIp(host) || host === '::' || host.startsWith('fc') || host.startsWith('fd')) return true;
  const allowlist = String(env.MONGO_PRIVATE_HOST_ALLOWLIST || '').split(',').map((value) => value.trim().toLowerCase()).filter(Boolean);
  if (allowlist.includes(host)) return true;
  return !host.includes('.') && env.PRODUCTION_MONGO_PRIVATE_NETWORK === 'true';
}

function validateBootstrapEnvironment(env = process.env) {
  const production = env.NODE_ENV === 'production';
  if (production) {
    if (env.PRODUCTION_ADMIN_BOOTSTRAP_CONFIRM !== 'YES') throw new Error('Production bootstrap requires PRODUCTION_ADMIN_BOOTSTRAP_CONFIRM=YES.');
    if (env.ADMIN_DEV_SKIP_MFA !== 'false') throw new Error('Production bootstrap requires ADMIN_DEV_SKIP_MFA=false.');
    if (env.PRODUCTION_MONGO_PRIVATE_NETWORK !== 'true') throw new Error('Production bootstrap requires PRODUCTION_MONGO_PRIVATE_NETWORK=true.');
    if (!env.MONGO_URI) throw new Error('Production bootstrap requires MONGO_URI.');
    let parsed;
    try { parsed = new URL(env.MONGO_URI); } catch { throw new Error('MONGO_URI is invalid.'); }
    if (!['mongodb:', 'mongodb+srv:'].includes(parsed.protocol) || !isPrivateMongoHost(parsed.hostname, env)) {
      throw new Error('Production bootstrap requires a private MongoDB host.');
    }
    return;
  }
  if (!isLocalMongoUri(env.MONGO_URI)) throw new Error('Non-production bootstrap is restricted to a local MongoDB URI.');
}

function isStrongProductionPassword(password) {
  return password.length >= 12 && /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password) && /[^A-Za-z0-9]/.test(password);
}

function question(prompt, hidden = false) {
  if (!hidden) {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    return new Promise((resolve) => rl.question(prompt, (answer) => { rl.close(); resolve(answer.trim()); }));
  }

  if (!process.stdin.isTTY || typeof process.stdin.setRawMode !== 'function') {
    throw new Error('A TTY is required for secure hidden password input. Run npm run admin:create in an interactive terminal.');
  }

  return new Promise((resolve) => {
    process.stdout.write(prompt);
    process.stdin.setEncoding('utf8');
    process.stdin.setRawMode(true);
    process.stdin.resume();
    let answer = '';
    const onData = (chunk) => {
      for (const key of chunk) {
        if (key === '\u0003') {
          process.stdin.setRawMode(false);
          process.stdin.off('data', onData);
          process.stdout.write('\n');
          process.exit(130);
        } else if (key === '\r' || key === '\n') {
          process.stdin.setRawMode(false);
          process.stdin.off('data', onData);
          process.stdin.pause();
          process.stdout.write('\n');
          resolve(answer.trim());
        } else if (key === '\u007f' || key === '\b') {
          answer = answer.slice(0, -1);
        } else if (key >= ' ') {
          answer += key;
        }
      }
    };
    process.stdin.on('data', onData);
  });
}

async function main() {
  const production = process.env.NODE_ENV === 'production';
  validateBootstrapEnvironment(process.env);
  const email = (process.argv.find((arg) => arg.startsWith('--email=')) || '').slice('--email='.length) || process.env.ADMIN_INITIAL_EMAIL || await question('Super Admin email: ');
  const password = production ? await question('Super Admin password (12+ chars, upper/lower/number/symbol): ', true) : (process.env.ADMIN_INITIAL_PASSWORD || await question('Super Admin password (min 12 characters): ', true));
  const name = process.env.ADMIN_INITIAL_NAME || await question('Super Admin name: ');
  if (!isValidEmail(email) || !password || password.length < 12 || !name) throw new Error('Name, valid email, and a password of at least 12 characters are required.');
  if (production && !isStrongProductionPassword(password)) throw new Error('Production password must be 12+ characters with upper, lower, number and symbol.');

  await mongoose.connect(process.env.MONGO_URI);
  const existing = await Admin.findOne({ email: email.toLowerCase() });
  if (existing) throw new Error('An administrator with that email already exists.');
  const passwordHash = await bcrypt.hash(password, 12);
  await Admin.create({ name, email: email.toLowerCase(), passwordHash, role: 'super_admin', status: 'active' });
  console.log(`Created ${production ? 'production' : 'local'} Super Admin ${email.toLowerCase()}. MFA enrollment is required on first login.`);
}

if (require.main === module) main()
  .catch((error) => { console.error(`Admin bootstrap failed: ${error.message}`); process.exitCode = 1; })
  .finally(async () => { if (mongoose.connection.readyState) await mongoose.disconnect(); });

module.exports = { isPrivateMongoHost, isStrongProductionPassword, isValidEmail, validateBootstrapEnvironment };
