import type { DashboardOverview, DashboardStudent } from '@/models/Dashboard'
import { apiClient } from '@/services/api/client'

export const dashboardService = {
  async getOverview(): Promise<DashboardOverview> {
    const response = await apiClient.get<{ data: DashboardOverview }>('/admin/dashboard/overview')
    return response.data
  },
  async getStudents(): Promise<DashboardStudent[]> {
    return []
  },
}
