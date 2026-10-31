const fs = require('fs/promises');
const path = require('path');
const dns = require('dns').promises;
const { validateSupplierImageUrl, ingestSupplierImages, STORAGE_ROOT } = require('../services/supplierImageIngestion');

describe('supplier image ingestion URL protection', () => {
  test('requires HTTPS and an approved ARTO host', async () => {
    await expect(validateSupplierImageUrl('http://i0.wp.com/artomall.com/image.png', 'artomall')).rejects.toThrow('HTTPS');
    await expect(validateSupplierImageUrl('https://example.com/image.png', 'artomall')).rejects.toThrow('approved');
  });

  test('rejects credentials and custom ports', async () => {
    await expect(validateSupplierImageUrl('https://user:pass@i0.wp.com/image.png', 'artomall')).rejects.toThrow('credentials');
    await expect(validateSupplierImageUrl('https://i0.wp.com:8443/image.png', 'artomall')).rejects.toThrow('custom port');
  });

  test('downloads, validates and writes an internal WebP media path', async () => {
    const originalLookup = dns.lookup;
    const originalFetch = global.fetch;
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64');
    dns.lookup = async () => [{ address: '93.184.216.34', family: 4 }];
    global.fetch = async () => new Response(png, { status: 200, headers: { 'content-type': 'image/png', 'content-length': String(png.length) } });
    try {
      const stored = await ingestSupplierImages(['https://i0.wp.com/artomall.com/image.png'], { supplier: 'artomall' });
      expect(stored[0].mediaPath).toMatch(/^\/media\/products\/[a-f0-9]+\.webp$/);
      expect(stored[0].url).toContain('i0.wp.com');
      const filename = path.basename(stored[0].mediaPath);
      const stat = await fs.stat(path.join(STORAGE_ROOT, filename));
      expect(stat.size).toBeGreaterThan(0);
      await fs.unlink(path.join(STORAGE_ROOT, filename));
    } finally { dns.lookup = originalLookup; global.fetch = originalFetch; }
  });
});
