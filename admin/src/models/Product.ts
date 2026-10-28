import type { ListQuery } from '@/types/api'

export const PRODUCT_CATEGORIES = [
  'electronics',
  'furniture',
  'apparel',
  'stationery',
  'home',
  'sports',
  'jewellery',
] as const
export type ProductCategory = (typeof PRODUCT_CATEGORIES)[number]

export const PRODUCT_STATUSES = ['active', 'draft', 'archived'] as const
export type ProductStatus = (typeof PRODUCT_STATUSES)[number]

export type InventoryMode = 'NUMERIC' | 'AVAILABILITY_BASED' | 'SUPPLIER_BASED'
export type AvailabilityStatus = 'IN_STOCK' | 'OUT_OF_STOCK' | 'AVAILABLE_TO_ORDER'

export interface Product {
  id: string
  name: string
  sku: string
  description: string | null
  category: ProductCategory
  /** Price in major units (e.g. dollars), not cents. */
  price: number
  inventory: number
  inventoryMode?: InventoryMode
  availabilityStatus?: AvailabilityStatus
  status: ProductStatus
  createdAt: string
  updatedAt: string
  image?: string | null
  variantsEnabled?: boolean
  variantAttributes?: Array<{ name: string; type?: string; values: string[] }>
  variants?: Array<{ id?: string; key: string; options: Record<string, string>; sku?: string; price: number; compareAtPrice?: number | null; inventoryMode?: InventoryMode; stock?: number; availabilityStatus?: AvailabilityStatus }>
}

export interface CreateProductInput {
  name: string
  description?: string | null
  category: ProductCategory
  price: number
  inventory: number
  inventoryMode?: InventoryMode
  availabilityStatus?: AvailabilityStatus
  status?: ProductStatus
  image?: string
}

export type UpdateProductInput = Partial<CreateProductInput>

export interface ProductsQuery extends ListQuery {
  category?: ProductCategory | 'all'
  status?: ProductStatus | 'all'
}
