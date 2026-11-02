import * as React from 'react';
import { useLocation } from 'react-router-dom';

export const SITE_URL = 'https://www.fastxhorizon.com';
export const HOME_SOCIAL_IMAGE = `${SITE_URL}/home-seo.png`;
const DEFAULT_DESCRIPTION = 'Shop jewellery, fashion, watches and everyday essentials from FASTXHORIZON with secure checkout and reliable delivery across Pakistan.';
const PRIVATE_PATHS = ['/login', '/register', '/forgot-password', '/reset-password', '/cart', '/checkout', '/order-success', '/wishlist'];
const ROUTE_META = {
  '/': ['FASTXHORIZON — Online Store in Pakistan', DEFAULT_DESCRIPTION],
  '/shop': ['Shop Products | FASTXHORIZON', 'Explore jewellery, fashion, watches and everyday essentials available from FASTXHORIZON.'],
  '/about': ['About FASTXHORIZON', 'Learn about FASTXHORIZON and our considered online marketplace for useful, expressive products.'],
  '/owners': ['Owners & Leadership | FASTXHORIZON', 'Meet the FASTXHORIZON leadership team behind our 2026 multi-category online marketplace.'],
  '/support': ['Customer Support | FASTXHORIZON', 'Get help with FASTXHORIZON products, orders, delivery and account questions.'],
  '/shipping-returns': ['Shipping & Returns | FASTXHORIZON', 'Read FASTXHORIZON shipping, delivery and returns information before ordering.'],
  '/privacy': ['Privacy Policy | FASTXHORIZON', 'Review how FASTXHORIZON handles privacy, consent and customer information.'],
  '/terms': ['Terms & Conditions | FASTXHORIZON', 'Read the terms that apply when using the FASTXHORIZON online store.'],
};

function absoluteUrl(value, fallback = HOME_SOCIAL_IMAGE) {
  if (!value) return fallback;
  try { return new URL(value, `${SITE_URL}/`).toString(); } catch (_) { return fallback; }
}

export default function SeoMeta({ title: titleProp, description: descriptionProp, image: imageProp, imageAlt = 'FASTXHORIZON online store', type = 'website', jsonLd }) {
  const location = useLocation();

  React.useEffect(() => {
    const params = new URLSearchParams(location.search);
    const category = params.get('category');
    const canonicalPath = `${location.pathname}${location.pathname === '/shop' && category ? `?category=${encodeURIComponent(category)}` : ''}`;
    const canonicalUrl = new URL(canonicalPath || '/', `${SITE_URL}/`).toString();
    const isPrivate = PRIVATE_PATHS.some(route => location.pathname === route || location.pathname.startsWith(`${route}/`)) || (location.pathname === '/shop' && Boolean(params.get('q')));
    const [routeTitle, routeDescription] = ROUTE_META[location.pathname] || [];
    const title = titleProp || routeTitle || `FASTXHORIZON — ${location.pathname.slice(1).replace(/[-/]/g, ' ')}`;
    const description = descriptionProp || routeDescription || DEFAULT_DESCRIPTION;
    const image = absoluteUrl(imageProp);

    document.title = title;
    const setMeta = (selector, attribute, value) => {
      let element = document.head.querySelector(selector);
      if (!element) {
        element = document.createElement('meta');
        element.setAttribute(attribute, selector.match(/\[.*?["']([^"']+)["']\]/)?.[1] || '');
        document.head.appendChild(element);
      }
      element.setAttribute('content', value);
    };
    const setLink = (rel, href) => {
      let element = document.head.querySelector(`link[rel="${rel}"]`);
      if (!element) { element = document.createElement('link'); element.setAttribute('rel', rel); document.head.appendChild(element); }
      element.setAttribute('href', href);
    };

    setLink('canonical', canonicalUrl);
    setMeta('meta[property="og:url"]', 'property', canonicalUrl);
    setMeta('meta[property="og:title"]', 'property', title);
    setMeta('meta[property="og:description"]', 'property', description);
    setMeta('meta[property="og:type"]', 'property', type);
    setMeta('meta[property="og:site_name"]', 'property', 'FASTXHORIZON');
    setMeta('meta[property="og:image"]', 'property', image);
    setMeta('meta[property="og:image:secure_url"]', 'property', image);
    setMeta('meta[property="og:image:type"]', 'property', 'image/png');
    setMeta('meta[property="og:image:alt"]', 'property', imageAlt);
    setMeta('meta[name="twitter:title"]', 'name', title);
    setMeta('meta[name="twitter:description"]', 'name', description);
    setMeta('meta[name="twitter:image"]', 'name', image);
    setMeta('meta[name="twitter:image:alt"]', 'name', imageAlt);
    setMeta('meta[name="twitter:card"]', 'name', 'summary_large_image');
    setMeta('meta[name="robots"]', 'name', isPrivate ? 'noindex,nofollow' : 'index,follow');

    let structuredData = document.head.querySelector('script[data-fxh-seo-jsonld]');
    if (!structuredData) {
      structuredData = document.createElement('script');
      structuredData.type = 'application/ld+json';
      structuredData.dataset.fxhSeoJsonld = 'true';
      document.head.appendChild(structuredData);
    }
    const payload = jsonLd || (location.pathname === '/' ? {
      '@context': 'https://schema.org',
      '@graph': [
        { '@type': 'Organization', name: 'FASTXHORIZON', url: `${SITE_URL}/`, logo: `${SITE_URL}/fastxhorizon.png` },
        { '@type': 'WebSite', name: 'FASTXHORIZON', url: `${SITE_URL}/`, potentialAction: { '@type': 'SearchAction', target: `${SITE_URL}/shop?q={search_term_string}`, 'query-input': 'required name=search_term_string' } },
      ],
    } : null);
    structuredData.textContent = payload ? JSON.stringify(payload) : '';
  }, [descriptionProp, imageAlt, imageProp, jsonLd, location.pathname, location.search, titleProp, type]);

  return null;
}
