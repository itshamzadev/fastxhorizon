require('dotenv').config();

const readline = require('readline');
const bcrypt = require('bcryptjs');
const mongoose = require('mongoose');
const Admin = require('../models/admin');
const { isLocalMongoUri } = require('../middleware/adminAuth');

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
  if (process.env.NODE_ENV === 'production' || !isLocalMongoUri(process.env.MONGO_URI)) {
    throw new Error('Admin bootstrap is restricted to a local MongoDB URI and non-production NODE_ENV.');
  }
  const email = (process.argv.find((arg) => arg.startsWith('--email=')) || '').slice('--email='.length) || await question('Super Admin email: ');
  const password = process.env.ADMIN_INITIAL_PASSWORD || await question('Super Admin password (min 12 characters): ', true);
  const name = process.env.ADMIN_INITIAL_NAME || await question('Super Admin name: ');
  if (!email || !password || password.length < 12 || !name) throw new Error('Name, valid email, and a password of at least 12 characters are required.');

  await mongoose.connect(process.env.MONGO_URI);
  const existing = await Admin.findOne({ email: email.toLowerCase() });
  if (existing) throw new Error('An administrator with that email already exists.');
  const passwordHash = await bcrypt.hash(password, 12);
  await Admin.create({ name, email: email.toLowerCase(), passwordHash, role: 'super_admin', status: 'active' });
  console.log(`Created local Super Admin ${email.toLowerCase()}. MFA enrollment is required outside the explicitly gated local bypass.`);
}

main()
  .catch((error) => { console.error(`Admin bootstrap failed: ${error.message}`); process.exitCode = 1; })
  .finally(async () => { if (mongoose.connection.readyState) await mongoose.disconnect(); });
