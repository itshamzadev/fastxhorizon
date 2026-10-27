export interface DashboardOrder {
  id: string
  number: string
  customerName: string
  total: number
  status: string
  placedAt: string
}

export interface DashboardCustomer {
  id: string
  name: string
  email: string
  createdAt: string
  status: string
}

export interface DashboardOverview {
  totalCustomers?: number
  activeCustomers?: number
  totalSellers?: number
  totalProducts?: number
  activeProducts?: number
  outOfStockProducts?: number
  totalOrders?: number
  pendingOrders?: number
  completedOrders?: number
  cancelledOrders?: number
  grossMerchandiseValue?: number
  confirmedCollectedRevenue?: number
  pendingManualPayments?: number
  recentOrders?: DashboardOrder[]
  recentCustomers?: DashboardCustomer[]
  topSellingProducts?: Array<{ name: string; quantity: number }>
  revenueByMonth?: Array<{ label: string; value: number }>
  orderCountByMonth?: Array<{ label: string; value: number }>
  /** Legacy component contracts retained for code-split pages not used by the live dashboard. */
  kpis?: Kpi[]
  tuitionByMonth?: SeriesPoint[]
  ordersByDay?: SeriesPoint[]
  trafficSources?: TrafficSource[]
  totalChildren?: number
  childrenByMonth?: StackedSeriesPoint[]
  programCategories?: SeriesPoint[]
  totalPrograms?: number
}

export interface SeriesPoint { label: string; value: number }
export interface Kpi { id: string; label: string; value: number; format: 'currency' | 'number' | 'percent'; changePct: number; series: SeriesPoint[] }
export interface StackedSeriesPoint { label: string; infant: number; toddler: number; schoolAge: number }
export interface TrafficSource { source: string; visits: number; share: number }
export interface ActivityEvent { id: string; actorName: string; action: string; target: string; createdAt: string; title?: string; description?: string }
export interface DashboardProgram { id: string; name: string; category: string; ageRange: string; sessionCount: number; dateRange: string; startTime: string; price: number; imageUrl: string }
export interface DashboardMessage { id: string; senderName: string; timestamp: string; preview: string }
export interface DashboardStudent { id: string; name: string; parentName: string; contractEnd: string }
export type ScheduleColorToken = 'chart-1' | 'chart-2' | 'chart-3' | 'chart-4'
export interface ScheduleEvent { id: string; date: string; time: string; title: string; category: string; colorToken: ScheduleColorToken }
