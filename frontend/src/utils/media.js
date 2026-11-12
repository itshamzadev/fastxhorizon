import { API_BASE_URL } from '../services/apiClient';

const API_ORIGIN = String(API_BASE_URL || '').replace(/\/api\/?$/, '');

export function resolveMediaUrl(value) {
  if (typeof value !== 'string') return '';
  const source = value.trim();
  if (!source) return '';
  if (/^\/media\//i.test(source)) return `${API_ORIGIN}${source}`;
  if (/^(https?:|data:|blob:|\/)/i.test(source)) return source;
  return `/${source.replace(/^\/+/, '')}`;
}

function internalProductFilename(value) {
  const source = String(value || '').trim();
  const match = source.match(/\/media\/products\/([^/?#]+)$/i);
  return match ? decodeURIComponent(match[1]) : '';
}

export function optimizedMediaUrl(value, width = 640, format = 'webp') {
  const filename = internalProductFilename(value);
  if (!filename) return resolveMediaUrl(value);
  return `${API_ORIGIN}/media/variants/${encodeURIComponent(filename)}?w=${encodeURIComponent(width)}&format=${encodeURIComponent(format)}`;
}

export function responsiveMediaProps(value, fallbackWidth = 640) {
  const widths = [240, 320, 480, 640, 960, 1280];
  return {
    src: optimizedMediaUrl(value, fallbackWidth),
    srcSet: widths.map(width => `${optimizedMediaUrl(value, width)} ${width}w`).join(', '),
    sizes: '(max-width: 599px) 50vw, (max-width: 1199px) 25vw, 16vw',
  };
}

export default resolveMediaUrl;
