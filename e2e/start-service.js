const { spawn } = require('child_process');

const service = process.argv[2];
const configs = {
  backend: { cwd: 'backend', command: 'npm.cmd', args: ['start'], env: { PORT: '5001', MONGO_URI: 'mongodb://127.0.0.1:27017/fastxhorizon_e2e', NODE_ENV: 'development', CORS_ORIGINS: 'http://127.0.0.1:3001,http://127.0.0.1:5174,http://localhost:3001,http://localhost:5174', SKIP_SEED_ON_START: 'true', ADMIN_DEV_SKIP_MFA: 'true', JWT_SECRET: 'phase7-e2e-only-secret', PRIVATE_EVIDENCE_STORAGE_ROOT: 'E:/Fast X Horizon/e2e/.private-evidence' } },
  frontend: { cwd: 'frontend', command: 'npm.cmd', args: ['start'], env: { PORT: '3001', BROWSER: 'none', REACT_APP_API_BASE_URL: 'http://127.0.0.1:5001/api' } },
  admin: { cwd: 'admin', command: 'npm.cmd', args: ['run', 'dev', '--', '--host', '127.0.0.1'], env: { VITE_API_URL: 'http://127.0.0.1:5001/api', VITE_ENABLE_MOCK_API: 'false' } },
};
if (!configs[service]) throw new Error(`Unknown E2E service: ${service}`);
const config = configs[service];
const child = spawn(config.command, config.args, { cwd: require('path').resolve(__dirname, '..', config.cwd), env: { ...process.env, ...config.env }, stdio: 'inherit', shell: true });
const stop = () => { if (!child.killed) child.kill('SIGTERM'); };
process.on('SIGINT', stop); process.on('SIGTERM', stop);
child.on('exit', code => process.exit(code ?? 0));
