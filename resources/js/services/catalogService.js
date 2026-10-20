import { apiClient } from '../lib/apiClient'

export const catalogService = {
    listProducts: (params = {}) => apiClient.get('/frontend/products', { params }),
    getProduct: (slug) => apiClient.get(`/frontend/products/${encodeURIComponent(slug)}`),
    listCategories: () => apiClient.get('/frontend/categories'),
}
