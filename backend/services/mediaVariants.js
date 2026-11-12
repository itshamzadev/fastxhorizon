const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const sharp = require('sharp');

const MEDIA_ROOT = path.resolve(process.env.MEDIA_STORAGE_ROOT || path.join(process.cwd(), 'data', 'media'));
const ORIGINAL_ROOT = path.join(MEDIA_ROOT, 'products');
const VARIANT_ROOT = path.join(MEDIA_ROOT, 'variants');
const ALLOWED_WIDTHS = [160, 240, 320, 480, 640, 960, 1280, 1600];
const ALLOWED_FORMATS = new Set(['webp', 'avif']);

function normalizeWidth(value) {
  const requested = Number.parseInt(String(value || ''), 10);
  if (!Number.isFinite(requested)) return 640;
  return ALLOWED_WIDTHS.reduce((closest, width) => Math.abs(width - requested) < Math.abs(closest - requested) ? width : closest, ALLOWED_WIDTHS[0]);
}

function normalizeFormat(value) {
  const format = String(value || 'webp').toLowerCase();
  return ALLOWED_FORMATS.has(format) ? format : 'webp';
}

function safeFilename(value) {
  const filename = path.basename(String(value || ''));
  if (!filename || filename !== String(value || '') || filename.includes('..')) return null;
  return filename;
}

function pathsFor(filename, width, format) {
  const safe = safeFilename(filename);
  if (!safe) return null;
  const normalizedWidth = normalizeWidth(width);
  const normalizedFormat = normalizeFormat(format);
  return {
    source: path.join(ORIGINAL_ROOT, safe),
    target: path.join(VARIANT_ROOT, `${safe}.w${normalizedWidth}.${normalizedFormat}`),
    width: normalizedWidth,
    format: normalizedFormat,
  };
}

async function ensureVariant(filename, width, format) {
  const paths = pathsFor(filename, width, format);
  if (!paths) return null;
  try { await fsp.access(paths.source, fs.constants.R_OK); } catch { return null; }
  try { await fsp.access(paths.target, fs.constants.R_OK); return paths; } catch { /* generate below */ }

  await fsp.mkdir(VARIANT_ROOT, { recursive: true });
  const temporary = `${paths.target}.${process.pid}.${Date.now()}.tmp`;
  const output = sharp(paths.source, { failOn: 'error' })
    .rotate()
    .resize({ width: paths.width, fit: 'inside', withoutEnlargement: true });
  if (paths.format === 'avif') output.avif({ quality: 55, effort: 4 });
  else output.webp({ quality: 78, effort: 4 });
  try {
    await output.toFile(temporary);
    await fsp.rename(temporary, paths.target);
  } catch (error) {
    await fsp.rm(temporary, { force: true }).catch(() => {});
    try { await fsp.access(paths.target, fs.constants.R_OK); } catch { throw error; }
  }
  return paths;
}

module.exports = { ALLOWED_WIDTHS, ensureVariant, normalizeFormat, normalizeWidth, pathsFor };
