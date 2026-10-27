import { apiClient } from './apiClient';

export const WISHLIST_STORAGE_KEY = 'fastxhorizonGuestWishlist';

export function getCustomerToken() {
  return sessionStorage.getItem('MERNEcommerceToken') || '';
}

export function getGuestWishlistIds() {
  try {
    const value = JSON.parse(localStorage.getItem(WISHLIST_STORAGE_KEY) || '[]');
    return Array.isArray(value) ? Array.from(new Set(value.map(String).filter(Boolean))) : [];
  } catch (error) {
    return [];
  }
}

export function saveGuestWishlistIds(ids) {
  localStorage.setItem(WISHLIST_STORAGE_KEY, JSON.stringify(Array.from(new Set(ids.map(String).filter(Boolean)))));
}

export async function getWishlist() {
  const { data } = await apiClient.get('wishlist');
  return Array.isArray(data) ? data : [];
}

export async function addWishlistItem(id) {
  const { data } = await apiClient.post(`wishlist/${id}`);
  return data;
}

export async function removeWishlistItem(id) {
  await apiClient.delete(`wishlist/${id}`);
}

export async function mergeWishlist(ids) {
  const { data } = await apiClient.post('wishlist/merge', { productIds: ids });
  return Array.isArray(data) ? data : [];
}
