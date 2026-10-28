import { apiClient } from '@/services/api/client'
import type { Paginated } from '@/types/api'

export interface ImportSummary { rows: number; uniqueProducts: number; imageRows: number; newProducts: number; existingProducts: number; skippedProducts: number; failedProducts: number; invalidRows: number; missingPrices: number; missingImages: number }
export interface ImportPreviewProduct { supplierKey: string; name: string; category: string; subcategory: string; supplierSKU: string; sourcePrice: number | null; draftSellingPrice: number | null; image: string | null; imageCount: number; pricingStatus: string; validation: string }
export interface ImportPreview { id: string; expiresAt: string; supplier: string; filename: string; summary: ImportSummary; products: ImportPreviewProduct[] }
export interface ImportResult { id: string; imported: number; skipped: number; failed: number; status: string }
export interface ImportHistory { id: string; supplier: string; filename: string; uploadedBy: string; status: string; mode: string; summary: ImportSummary; createdAt: string }

export const importService = {
  preview(csv: string, filename: string) { return apiClient.postText<{ data: ImportPreview }>('/admin/product-imports/preview', csv, { filename }) },
  confirm(importId: string, mode: 'new_only' | 'update_existing' | 'skip_existing') { return apiClient.post<{ data: ImportResult }>('/admin/product-imports/confirm', { importId, mode, confirm: true }) },
  history(page = 1) { return apiClient.get<Paginated<ImportHistory>>('/admin/product-imports', { query: { page, pageSize: 15 } }) },
}
