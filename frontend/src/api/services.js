import { api, authToken } from './client'

const data = (response) => response.data?.data ?? response.data

export const normalizeCategories = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.categories)) return payload.categories
  if (Array.isArray(payload?.data?.categories)) return payload.data.categories
  return []
}

export const catalogService = {
  products: (params = {}) => api.get('/frontend/products', { params }).then((r) => r.data),
  product: (slug) => api.get(`/frontend/products/${encodeURIComponent(slug)}`).then(data),
  categories: () => api.get('/frontend/categories').then((response) => normalizeCategories(response.data)),
  reviews: (slug) => api.get(`/frontend/products/${encodeURIComponent(slug)}/reviews`).then(data),
}

export const sellerService = {
  products: (slug, params = {}) => api.get(`/frontend/sellers/${encodeURIComponent(slug)}/products`, { params }).then(data),
}

export const authService = {
  login: (payload) => api.post('/frontend/auth/login', payload).then((r) => { if (r.data?.token) authToken.set(r.data.token); return r.data }),
  register: (payload) => api.post('/frontend/auth/register', payload).then((r) => { if (r.data?.token) authToken.set(r.data.token); return r.data }),
  logout: () => api.post('/frontend/auth/logout').finally(() => authToken.set(null)),
  me: () => api.get('/frontend/account').then(data),
}

export const cartService = {
  get: () => api.get('/frontend/cart').then(data),
  add: (payload) => api.post('/frontend/cart/items', { ...payload, id: payload.id ?? payload.product_id }).then(data),
  update: (payload) => api.put('/frontend/cart/items', payload).then(data),
  remove: (payload) => api.delete('/frontend/cart/items', { data: payload }).then(data),
}

export const wishlistService = {
  list: () => api.get('/frontend/wishlist').then(data),
  add: (payload) => api.post('/frontend/wishlist/items', payload).then(data),
  remove: (payload) => api.delete('/frontend/wishlist/items', { data: payload }).then(data),
}

export const orderService = {
  list: () => api.get('/frontend/orders').then(data),
  detail: (id) => api.get(`/frontend/orders/${id}`).then(data),
  tracking: (id) => api.get(`/frontend/orders/${id}/tracking`).then(data),
  paymentStatus: (id) => api.get(`/frontend/orders/${id}/payment-status`).then(data),
  submitPaymentProof: (id, payload) => api.post(`/frontend/orders/${id}/payment-proof`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }).then(data),
}

export const addressService = {
  list: () => api.get('/frontend/addresses').then(data),
  create: (payload) => api.post('/frontend/addresses', payload).then(data),
  update: (id, payload) => api.put(`/frontend/addresses/${id}`, payload).then(data),
  remove: (id) => api.delete(`/frontend/addresses/${id}`).then(data),
}

export const walletService = {
  summary: () => api.get('/frontend/wallet').then(data),
  transactions: (params = {}) => api.get('/frontend/wallet/transactions', { params }).then((r) => r.data),
}


export const checkoutService = {
  shippingOptions: () => api.get('/frontend/checkout/shipping-options').then(data),
  preview: (payload) => api.post('/frontend/checkout/preview', payload).then(data),
  coupon: (payload) => api.post('/frontend/checkout/coupon', payload).then(data),
  placeCodOrder: (payload, idempotencyKey) => api.post('/frontend/checkout/order', payload, { headers: { 'Idempotency-Key': idempotencyKey } }).then(data),
  offlineOptions: () => api.get('/frontend/payments/offline/options').then(data),
  initializeSafepay: (orderId) => api.post('/frontend/payments/safepay/initialize', { order_id: orderId }).then(data),
}
