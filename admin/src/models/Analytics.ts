import type { ActivityEvent } from './Dashboard'

export type AnalyticsRange = '7d' | '30d' | '90d'

export const ANALYTICS_RANGES: readonly AnalyticsRange[] = ['7d', '30d', '90d'] as const

/** Totals over the selected range. */
export interface AnalyticsTotals {
  revenue: number
  orders: number
  completedOrders: number
  pendingOrders: number
  cancelledOrders: number
  visitors: number | null
  /** Percentage, e.g. `3.4` means 3.4%. Null means no tracking source exists. */
  conversionRate: number | null
}

export interface AnalyticsOverview {
  range: AnalyticsRange
  totals: AnalyticsTotals
  totalCustomers: number
  /**
   * Per-metric percentage changes versus the previous comparable window,
   * keyed by metric name.
   */
  changes: {
    revenue: number | null
    orders: number | null
    visitors: number | null
    conversionRate: number | null
  }
  series: AnalyticsDailyPoint[]
  topPages: TopPage[]
  topCountries: TopCountry[]
  statusCounts: Array<{ status: string; count: number; amount: number }>
  topProducts: Array<{ id: string; name: string; quantity: number; sales: number }>
  limitations: string[]
}

export interface AnalyticsDailyPoint {
  date: string
  revenue: number
  orders: number
  visitors: number
}

export interface TopPage {
  path: string
  views: number
  changePct: number
}

export interface TopCountry {
  country: string
  share: number
}

/**
 * The analytics overview intentionally reuses the dashboard activity feed.
 */
export type AnalyticsActivity = ActivityEvent
