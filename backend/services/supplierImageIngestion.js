const crypto = require('crypto');
const dns = require('dns').promises;
const fs = require('fs/promises');
const path = require('path');
const sharp = require('sharp');

const MAX_BYTES = Number(process.env.SUPPLIER_IMAGE_MAX_BYTES || 8 * 1024 * 1024);
const TIMEOUT_MS = Number(process.env.SUPPLIER_IMAGE_TIMEOUT_MS || 10000);
const STORAGE_ROOT = path.resolve(process.env.MEDIA_STORAGE_ROOT || path.join(process.cwd(), 'data', 'media', 'products'));
const HOSTS = {
  artomall: new Set((process.env.SUPPLIER_IMAGE_HOSTS_ARTO || 'artomall.com,www.artomall.com,i0.wp.com').split(',').map((host) => host.trim().toLowerCase()).filter(Boolean)),
  yourmart: new Set((process.env.SUPPLIER_IMAGE_HOSTS_YOURMART || 'admin.yourmart.pk,yourmart.pk,www.yourmart.pk,yourmart.com,www.yourmart.com').split(',').map((host) => host.trim().toLowerCase()).filter(Boolean)),
};

function isPrivateAddress(address) {
  const value = String(address || '').toLowerCase();
  return value === '::1' || value === 'localhost' || /^127\./.test(value) || /^10\./.test(value) || /^192\.168\./.test(value) || /^172\.(1[6-9]|2\d|3[0-1])\./.test(value) || /^169\.254\./.test(value) || value.startsWith('fc') || value.startsWith('fd') || value.startsWith('fe80:');
}

async function validateSupplierImageUrl(value, supplier) {
  let parsed;
  try { parsed = new URL(String(value)); } catch { throw new Error('Image URL is invalid.'); }
  if (parsed.protocol !== 'https:' || parsed.username || parsed.password || parsed.port) throw new Error('Image URL must use HTTPS without credentials or a custom port.');
  const allowed = HOSTS[String(supplier || '').toLowerCase()] || new Set();
  if (!allowed.has(parsed.hostname.toLowerCase()) && ![...allowed].some((host) => parsed.hostname.toLowerCase().endsWith(`.${host}`))) throw new Error('Image host is not approved for this supplier.');
  const addresses = await dns.lookup(parsed.hostname, { all: true, verbatim: true });
  if (!addresses.length || addresses.some(({ address }) => isPrivateAddress(address))) throw new Error('Image host resolves to a blocked network destination.');
  return parsed;
}

async function downloadImage(url, supplier) {
  const parsed = await validateSupplierImageUrl(url, supplier);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    const response = await fetch(parsed.href, { redirect: 'manual', signal: controller.signal, headers: { Accept: 'image/avif,image/webp,image/png,image/jpeg' } });
    if (response.status >= 300 && response.status < 400) throw new Error('Image redirects are not permitted.');
    if (!response.ok) throw new Error('Image download failed.');
    const type = String(response.headers.get('content-type') || '').split(';')[0].toLowerCase();
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/avif'].includes(type)) throw new Error('Downloaded content is not a supported image.');
    const length = Number(response.headers.get('content-length') || 0);
    if (length > MAX_BYTES) throw new Error('Image exceeds the permitted size.');
    const reader = response.body?.getReader();
    if (!reader) throw new Error('Image response is empty.');
    const chunks = []; let total = 0;
    while (true) { const { done, value } = await reader.read(); if (done) break; total += value.byteLength; if (total > MAX_BYTES) throw new Error('Image exceeds the permitted size.'); chunks.push(Buffer.from(value)); }
    const buffer = Buffer.concat(chunks);
    const output = await sharp(buffer, { failOn: 'error' }).rotate().webp({ quality: 86 }).toBuffer();
    return { buffer: output, sourceUrl: parsed.href };
  } finally { clearTimeout(timer); }
}

async function ingestSupplierImages(images, { supplier = 'artomall' } = {}) {
  const urls = [...new Set((Array.isArray(images) ? images : []).map(String).filter(Boolean))].slice(0, 30);
  if (!urls.length) throw new Error('At least one product image is required.');
  await fs.mkdir(STORAGE_ROOT, { recursive: true });
  const stored = [];
  for (const url of urls) {
    const downloaded = await downloadImage(url, supplier);
    const mediaId = crypto.randomBytes(18).toString('hex');
    const filename = `${mediaId}.webp`;
    const target = path.join(STORAGE_ROOT, filename);
    const temp = `${target}.${process.pid}.tmp`;
    await fs.writeFile(temp, downloaded.buffer, { flag: 'wx' });
    await fs.rename(temp, target);
    stored.push({ mediaPath: `/media/products/${filename}`, url: downloaded.sourceUrl });
  }
  return stored;
}

async function auditExternalProductImages(Product) {
  const products = await Product.find({ $or: [{ image: /^https?:\/\//i }, { images: { $elemMatch: /^https?:\/\//i } }] }).select('_id name image images supplierName').lean();
  return { totalAffected: products.length, products: products.map(({ _id, name, image, images, supplierName }) => ({ id: String(_id), name, supplierName, externalImageCount: [image, ...(images || [])].filter((value) => /^https?:\/\//i.test(value || '')).length })) };
}

module.exports = { STORAGE_ROOT, validateSupplierImageUrl, ingestSupplierImages, auditExternalProductImages };
