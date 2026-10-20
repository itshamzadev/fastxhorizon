import axios from 'axios'

const baseURL = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '')

export const api = axios.create({
  baseURL,
  timeout: Number(import.meta.env.VITE_API_TIMEOUT || 15000),
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

api.interceptors.request.use((config) => {
  const token = window.localStorage.getItem('fxh_api_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use((response) => response, async (error) => {
  const config = error.config || {}
  const method = String(config.method || 'get').toLowerCase()
  const safeRead = ['get', 'head', 'options'].includes(method)
  const status = error.response?.status
  if (safeRead && (!error.response || status === 408 || status === 429 || status >= 500) && !config.__fxRetried) {
    config.__fxRetried = true
    await new Promise((resolve) => setTimeout(resolve, 250))
    return api.request(config)
  }
  if (error.response?.status === 401) window.dispatchEvent(new CustomEvent('fxh:auth-expired'))
  error.apiMessage = error.response?.data?.message || error.response?.data?.errors?.[0]?.message || error.message
  return Promise.reject(error)
})

export const authToken = {
  get: () => window.localStorage.getItem('fxh_api_token'),
  set: (token) => token ? window.localStorage.setItem('fxh_api_token', token) : window.localStorage.removeItem('fxh_api_token'),
}

export const mediaUrl = (value) => {
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value
  const base = (import.meta.env.VITE_MEDIA_BASE_URL || '').replace(/\/$/, '')
  return base ? `${base}/${String(value).replace(/^\//, '')}` : value
}
