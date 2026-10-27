/**
 * Central runtime configuration. Values come from Vite env variables
 * (see `.env.example`) with sensible defaults so `npm run dev` works
 * out of the box.
 */

/** Blank host env vars (common on Vercel) must fall back to the mock prefix. */
export function resolveApiBaseUrl(value: string | undefined): string {
  return (value ?? '').trim() || '/api'
}

export const appConfig = {
  name: 'FastXHorizon Control Panel',
  version: '1.0.0',

  /** Base URL every API request is resolved against. */
  apiBaseUrl: resolveApiBaseUrl(import.meta.env.VITE_API_URL),

  /** Explicitly opt into mock data; normal development uses Express. */
  enableMockApi: import.meta.env.MODE === 'test' || import.meta.env.VITE_ENABLE_MOCK_API === 'true',

} as const

export const STORAGE_KEYS = {
  authSession: 'vital.auth.session',
  theme: 'vital.theme',
  sidebarCollapsed: 'vital.sidebar.collapsed',
} as const
