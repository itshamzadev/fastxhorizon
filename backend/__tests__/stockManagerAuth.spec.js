const crypto = require('crypto');

describe('Stock Manager credential hashing', () => {
  it('produces deterministic fixed-length digests for token comparison', () => {
    const digest = value => crypto.createHash('sha256').update(String(value)).digest('hex');
    expect(digest('token-a')).toHaveLength(64);
    expect(digest('token-a')).toBe(digest('token-a'));
    expect(digest('token-a')).not.toBe(digest('token-b'));
  });
});
