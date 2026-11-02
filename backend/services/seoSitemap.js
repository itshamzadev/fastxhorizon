const Product = require('../models/product');
const Category = require('../models/category');

const SITE_URL = String(process.env.PUBLIC_SITE_URL || 'https://www.fastxhorizon.com').replace(/\/+$/, '');
const CACHE_TTL_MS = 5 * 60 * 1000;
let cached = null;

const escapeXml = value => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&apos;');

const safeDate = value => {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toISOString();
};

const addUrl = (urls, path, lastmod = '') => {
  const url = new URL(path, `${SITE_URL}/`).toString();
  if (!urls.has(url)) urls.set(url, lastmod);
};

async function buildSitemapXml() {
  const [products, categories] = await Promise.all([
    Product.find({
      status: 'active',
      price: { $gt: 0 },
      $or: [{ image: { $type: 'string', $ne: '' } }, { images: { $elemMatch: { $type: 'string', $ne: '' } } }],
    }).select('_id updatedAt').lean(),
    Category.find({ active: true }).select('name slug parentSlug updatedAt').sort({ parentSlug: 1, name: 1 }).lean(),
  ]);

  const urls = new Map();
  addUrl(urls, '/');
  addUrl(urls, '/shop');
  addUrl(urls, '/about');
  addUrl(urls, '/support');
  addUrl(urls, '/privacy');
  addUrl(urls, '/terms');
  addUrl(urls, '/shipping-returns');

  for (const product of products) {
    // ProductDetails is currently routed by Mongo id; use that real public
    // route instead of inventing slug routes that the SPA does not serve.
    addUrl(urls, `/product/${encodeURIComponent(String(product._id))}`, safeDate(product.updatedAt));
  }

  // The public shop uses its existing category query filter. Names are used
  // intentionally because that is the public route's supported contract.
  for (const category of categories) {
    addUrl(urls, `/shop?category=${encodeURIComponent(category.name)}`, safeDate(category.updatedAt));
  }

  const body = [...urls.entries()].map(([loc, lastmod]) => [
    '  <url>',
    `    <loc>${escapeXml(loc)}</loc>`,
    lastmod ? `    <lastmod>${escapeXml(lastmod)}</lastmod>` : '',
    '  </url>',
  ].filter(Boolean).join('\n')).join('\n');

  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${body}\n</urlset>\n`;
}

async function getSitemapXml() {
  if (cached && Date.now() - cached.createdAt < CACHE_TTL_MS) return cached.xml;
  const xml = await buildSitemapXml();
  cached = { createdAt: Date.now(), xml };
  return xml;
}

const resetSitemapCache = () => { cached = null; };

module.exports = { buildSitemapXml, getSitemapXml, resetSitemapCache, SITE_URL };
