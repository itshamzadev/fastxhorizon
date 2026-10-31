import type { AnalyticsRange } from '@/models/Analytics'
import type { AnalyticsOverview } from '@/models/Analytics'
import { apiClient } from '@/services/api/client'

type BackendAnalytics = { range: AnalyticsRange; totalCustomers?: number; orderTotals?: { orders?: number; revenue?: number; completed?: number; pending?: number; cancelledOrders?: number }; dailySales?: Array<{ label: string; value: number; orders: number }>; statusCounts?: Array<{ status: string; count: number; amount: number }>; topProducts?: Array<{ id: string; name: string; quantity: number; sales: number }>; limitations?: string[] }

/** Analytics feature service. */
export const analyticsService = {
  async getOverview(range: AnalyticsRange): Promise<AnalyticsOverview> {
    const response = await apiClient.get<{ data: BackendAnalytics | AnalyticsOverview }>(
      '/admin/dashboard/analytics',
      {
        query: { range },
      },
    )
    const source = response.data
    if ('totals' in source) return { ...source, totalCustomers: source.totalCustomers || 0, totals: { ...source.totals, completedOrders: source.totals.completedOrders || source.totals.orders, pendingOrders: source.totals.pendingOrders || 0, cancelledOrders: source.totals.cancelledOrders || 0 }, statusCounts: source.statusCounts || [], topProducts: source.topProducts || [], limitations: source.limitations || [] }
    const totals = source.orderTotals || {}
    return {
      range: source.range,
      totals: { revenue: totals.revenue || 0, orders: totals.orders || 0, completedOrders: totals.completed || 0, pendingOrders: totals.pending || 0, cancelledOrders: totals.cancelledOrders || 0, visitors: null, conversionRate: null },
      changes: { revenue: null, orders: null, visitors: null, conversionRate: null },
      totalCustomers: source.totalCustomers || 0,
      series: (source.dailySales || []).map((point) => ({ date: point.label, revenue: point.value, orders: point.orders, visitors: 0 })),
      topPages: [], topCountries: [], statusCounts: source.statusCounts || [], topProducts: source.topProducts || [], limitations: source.limitations || [],
    }
  },
}
