import { apiClient } from '@/services/api/client'

export interface AnnouncementSettings {
  enabled: boolean
  message: string
}

export interface StoreOperationsSettings {
  onlineOrderingEnabled: boolean
  message: string
  updatedAt: string | null
  updatedBy: { id: string; email: string; name?: string } | null
  reason: string
}

export const DEFAULT_ANNOUNCEMENT = 'Coming Soon — Discover a New Shopping Experience with FastXHorizon'

export const settingsService = {
  async getAnnouncement(): Promise<AnnouncementSettings> {
    const response = await apiClient.get<{ data: AnnouncementSettings }>('/admin/storefront/announcement')
    return response.data
  },

  async updateAnnouncement(input: AnnouncementSettings): Promise<AnnouncementSettings> {
    const response = await apiClient.put<{ data: AnnouncementSettings }>('/admin/storefront/announcement', input)
    return response.data
  },
  async getStoreOperations(): Promise<StoreOperationsSettings> {
    const response = await apiClient.get<{ data: StoreOperationsSettings }>('/admin/settings/store-operations')
    return response.data
  },
  async updateStoreOperations(input: Pick<StoreOperationsSettings, 'onlineOrderingEnabled' | 'message' | 'reason'>): Promise<StoreOperationsSettings> {
    const response = await apiClient.patch<{ data: StoreOperationsSettings }>('/admin/settings/store-operations', input)
    return response.data
  },
}
