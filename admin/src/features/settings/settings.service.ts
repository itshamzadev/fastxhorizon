import { apiClient } from '@/services/api/client'

export interface AnnouncementSettings {
  enabled: boolean
  message: string
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
}
