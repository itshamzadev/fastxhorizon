import axios from 'axios'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '')

export const apiClient = axios.create({
    baseURL: apiBaseUrl,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    timeout: Number(import.meta.env.VITE_API_TIMEOUT || 15000),
})

apiClient.interceptors.request.use((config) => {
    const token = window.localStorage.getItem('fxh_api_token')
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

apiClient.interceptors.response.use((response) => response, async (error) => {
    const config = error.config || {}
    const method = String(config.method || 'get').toLowerCase()
    const retryableMethod = ['get', 'head', 'options'].includes(method)
    const status = error.response?.status
    const retryableFailure = !error.response || status === 408 || status === 429 || status >= 500

    // Reads may be retried once when a load balancer/node has a transient
    // failure. Writes (especially checkout/payment POSTs) are never retried
    // here unless their own idempotency contract explicitly handles it.
    if (retryableMethod && retryableFailure && !config.__fxRetried) {
        config.__fxRetried = true
        await new Promise((resolve) => setTimeout(resolve, 250))
        return apiClient.request(config)
    }

    const payload = error.response?.data
    error.apiMessage = payload?.message || payload?.errors?.[0]?.message || error.message
    return Promise.reject(error)
})

export function setApiToken(token) {
    if (token) window.localStorage.setItem('fxh_api_token', token)
    else window.localStorage.removeItem('fxh_api_token')
}

export function getApiBaseUrl() { return apiBaseUrl }
