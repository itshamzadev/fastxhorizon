import * as React from 'react';
import { useLocation } from 'react-router-dom';

const SITE_URL = 'https://www.fastxhorizon.com';
const PRIVATE_PATHS = ['/login', '/register', '/forgot-password', '/reset-password', '/cart', '/checkout', '/order-success', '/wishlist'];

export default function SeoMeta() {
  const location = useLocation();

  React.useEffect(() => {
    const path = `${location.pathname}${location.pathname === '/shop' ? location.search : ''}`;
    const canonicalUrl = new URL(path || '/', `${SITE_URL}/`).toString();
    const isPrivate = PRIVATE_PATHS.some(route => location.pathname === route || location.pathname.startsWith(`${route}/`));
    const title = location.pathname === '/' ? 'FASTXHORIZON — Online Store' : `FASTXHORIZON — ${location.pathname.slice(1).replace(/[-/]/g, ' ')}`;
    const description = 'Shop curated products with secure checkout and reliable delivery from FASTXHORIZON.';

    document.title = title;
    const setMeta = (selector, attribute, value) => {
      let element = document.head.querySelector(selector);
      if (!element) {
        element = document.createElement('meta');
        element.setAttribute(attribute, selector.includes('property=') ? selector.split('"')[1] : selector.split('"')[1]);
        document.head.appendChild(element);
      }
      element.setAttribute('content', value);
    };
    const setLink = (rel, href) => {
      let element = document.head.querySelector(`link[rel="${rel}"]`);
      if (!element) {
        element = document.createElement('link');
        element.setAttribute('rel', rel);
        document.head.appendChild(element);
      }
      element.setAttribute('href', href);
    };

    setLink('canonical', canonicalUrl);
    setMeta('meta[property="og:url"]', 'property', canonicalUrl);
    setMeta('meta[property="og:title"]', 'property', title);
    setMeta('meta[property="og:description"]', 'property', description);
    setMeta('meta[name="twitter:title"]', 'name', title);
    setMeta('meta[name="twitter:description"]', 'name', description);
    setMeta('meta[name="robots"]', 'name', isPrivate ? 'noindex,nofollow' : 'index,follow');
  }, [location.pathname, location.search]);

  return null;
}
