import { API_BASE_URL } from '../services/apiClient';

const API_ORIGIN = API_BASE_URL.replace(/\/api\/?$/, '');

export function resolveMediaUrl(value) {
  if (typeof value !== 'string') return '';
  const source = value.trim();
  if (!source) return '';
  if (/^\/media\//i.test(source)) return `${API_ORIGIN}${source}`;
  if (/^(https?:|data:|blob:|\/)/i.test(source)) return source;
  return `/${source.replace(/^\/+/, '')}`;
}

export default resolveMediaUrl;
