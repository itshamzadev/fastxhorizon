import { apiClient } from '@/services/api/client'
import type { Paginated } from '@/types/api'

export interface PricingProduct { id: string; name: string; image: string | null; sku: string | null; category: string; supplierName: string | null; supplierSKU: string | null; sourcePrice: number | null; confirmedSupplierCost: number | null; operationalCostEstimate: number | null; draftSellingPrice: number | null; sellingPrice: number | null; targetProfit: number | null; estimatedNetProfit: number | null; pricingStatus: string; status: string; currency: string; updatedAt: string }
export interface PricingInput { mode: 'fixed_profit' | 'percentage_markup' | 'manual'; confirmedSupplierCost?: number | null; operationalCostEstimate?: number | null; targetProfit?: number | null; markupPercent?: number | null; sellingPrice?: number | null }

export const pricingService = {
  list(query: { page?: number; search?: string; pricingStatus?: string } = {}) { return apiClient.get<Paginated<PricingProduct>>('/admin/pricing/products', { query: { pageSize: 20, ...query } }) },
  update(id: string, input: PricingInput) { return apiClient.patch<{ data: PricingProduct }>(`/admin/pricing/products/${id}`, input) },
  approve(ids: string[]) { return apiClient.post<{ data: { approved: number } }>('/admin/pricing/approve', { ids, confirm: true }) },
  publish(ids: string[]) { return apiClient.post<{ data: { published: number } }>('/admin/pricing/publish', { ids, confirm: true }) },
}
