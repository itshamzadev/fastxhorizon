const express = require('express');
const { getSitemapXml, SITE_URL } = require('../services/seoSitemap');

const router = express.Router();

router.get('/sitemap.xml', async (req, res) => {
  try {
    const xml = await getSitemapXml();
    res.type('application/xml').set('Cache-Control', 'public, max-age=300').send(xml);
  } catch (error) {
    console.error('Sitemap generation failed:', error.message);
    res.status(503).type('text/plain').send('Sitemap temporarily unavailable');
  }
});

router.get('/robots.txt', (req, res) => {
  res.type('text/plain').set('Cache-Control', 'public, max-age=3600').send([
    'User-agent: *',
    'Allow: /',
    'Disallow: /login',
    'Disallow: /register',
    'Disallow: /forgot-password',
    'Disallow: /reset-password',
    'Disallow: /cart',
    'Disallow: /checkout',
    'Disallow: /order-success',
    'Disallow: /admin',
    `Sitemap: ${SITE_URL}/sitemap.xml`,
    '',
  ].join('\n'));
});

module.exports = router;
