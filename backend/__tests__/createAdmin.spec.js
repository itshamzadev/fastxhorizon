const { isStrongProductionPassword, validateBootstrapEnvironment } = require('../scripts/create-admin');

describe('production Super Admin bootstrap guard', () => {
  const base = {
    NODE_ENV: 'production',
    PRODUCTION_ADMIN_BOOTSTRAP_CONFIRM: 'YES',
    ADMIN_DEV_SKIP_MFA: 'false',
    PRODUCTION_MONGO_PRIVATE_NETWORK: 'true',
    MONGO_URI: 'mongodb://mongodb:27017/fastxhorizon',
  };

  test('accepts an explicitly authorized private Coolify Mongo host', () => {
    expect(() => validateBootstrapEnvironment(base)).not.toThrow();
  });

  test('rejects missing production confirmation', () => {
    expect(() => validateBootstrapEnvironment({ ...base, PRODUCTION_ADMIN_BOOTSTRAP_CONFIRM: '' })).toThrow(/PRODUCTION_ADMIN_BOOTSTRAP_CONFIRM/);
  });

  test('rejects development MFA bypass in production', () => {
    expect(() => validateBootstrapEnvironment({ ...base, ADMIN_DEV_SKIP_MFA: 'true' })).toThrow(/ADMIN_DEV_SKIP_MFA/);
  });

  test('rejects loopback and public Mongo hosts', () => {
    expect(() => validateBootstrapEnvironment({ ...base, MONGO_URI: 'mongodb://127.0.0.1:27017/db' })).toThrow(/private MongoDB/);
    expect(() => validateBootstrapEnvironment({ ...base, MONGO_URI: 'mongodb://mongo.example.com:27017/db', PRODUCTION_MONGO_PRIVATE_NETWORK: 'true' })).toThrow(/private MongoDB/);
  });

  test('requires a strong production password', () => {
    expect(isStrongProductionPassword('weakpassword')).toBe(false);
    expect(isStrongProductionPassword('Strong-Admin-2026!')).toBe(true);
  });
});
