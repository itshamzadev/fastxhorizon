import { apiClient } from '@/services/api/client'

export interface NewsletterSubscriber {
  id: string
  email: string
  status: 'pending' | 'confirmed' | 'unsubscribed'
  source: string
  consentAt: string
  confirmedAt: string | null
  unsubscribedAt: string | null
  createdAt: string
}

export interface NewsletterStats {
  total: number
  confirmed: number
  pending: number
  unsubscribed: number
}

export const newsletterService = {
  list(query: { page: number; pageSize: number; search?: string; status?: string }) {
    return apiClient.get<{ data: NewsletterSubscriber[]; meta: { page: number; pageSize: number; total: number; totalPages: number } }>('/admin/newsletter/subscribers', { query })
  },
  overview() {
    return apiClient.get<{ data: NewsletterStats }>('/admin/newsletter/overview')
  },
}
