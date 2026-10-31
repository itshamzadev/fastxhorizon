import { apiClient } from '@/services/api/client'
import type { Paginated } from '@/types/api'

export interface ImportSummary { rows: number; uniqueProducts: number; imageRows: number; newProducts: number; existingProducts: number; skippedProducts: number; failedProducts: number; invalidRows: number; missingPrices: number; missingImages: number; duplicateSkus?: number; selectedProducts?: number }
export interface ImportPreviewProduct { supplierKey: string; name: string; sku?: string; slug?: string; category: string; subcategory: string; supplierSKU: string; sourceCategory?: string; sourcePrice: number | null; sellingPrice?: number | null; draftSellingPrice: number | null; shortDescription?: string; descriptionHtml?: string; features?: string[]; specifications?: string[]; brand?: string; productUrl?: string; stockAvailable?: boolean; image: string | null; images?: string[]; imageCount: number; tags?: string[]; supplier?: string; parent?: string; warnings?: string[]; pricingStatus: string; duplicate?: boolean; validation: string }
export interface ImportCategoryOption { name: string; slug: string; parentSlug: string | null }
export interface ImportPreview { id: string; expiresAt: string; supplier: string; format?: string; filename: string; summary: ImportSummary; products: ImportPreviewProduct[]; categoryOptions?: ImportCategoryOption[] }
export interface ImportResult { id: string; imported: number; skipped: number; failed: number; status: string; errors?: Array<{ supplierKey?: string; message?: string }> }
export interface ImportHistory { id: string; supplier: string; filename: string; uploadedBy: string; status: string; mode: string; summary: ImportSummary; createdAt: string }

export const importService = {
  preview(source: string, filename: string, format: 'woocommerce' | 'artomall' = 'woocommerce') { return apiClient.postText<{ data: ImportPreview }>('/admin/product-imports/preview', source, { filename, format }) },
  confirm(importId: string, mode: 'new_only' | 'update_existing' | 'skip_existing', products: ImportPreviewProduct[]) { return apiClient.post<{ data: ImportResult }>('/admin/product-imports/confirm', { importId, mode, confirm: true, products }) },
  history(page = 1) { return apiClient.get<Paginated<ImportHistory>>('/admin/product-imports', { query: { page, pageSize: 15 } }) },
}
