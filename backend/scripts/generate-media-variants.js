const fs = require('fs/promises');
const path = require('path');
const { ALLOWED_WIDTHS, ensureVariant } = require('../services/mediaVariants');

async function main() {
  const mediaRoot = path.resolve(process.env.MEDIA_STORAGE_ROOT || path.join(process.cwd(), 'data', 'media'));
  const productsRoot = path.join(mediaRoot, 'products');
  const files = (await fs.readdir(productsRoot, { withFileTypes: true }).catch(() => [])).filter(entry => entry.isFile() && /\.(?:png|jpe?g|webp|avif)$/i.test(entry.name)).map(entry => entry.name);
  let generated = 0;
  for (const filename of files) {
    for (const width of ALLOWED_WIDTHS) {
      await ensureVariant(filename, width, 'webp');
      generated += 1;
    }
  }
  console.log(`Prepared ${generated} WebP variants for ${files.length} original product images.`);
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
