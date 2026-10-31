import { apiClient } from '@/services/api/client'
import type { Category, CategoryInput } from '@/models/Category'

export const categoryService = {
  async publicList(): Promise<{ data: Array<Category & { subcategories: Category[] }> }> {
    return apiClient.get('/products/categories', { authenticated: false })
  },
  async list(): Promise<{ data: Category[]; meta: { total: number; parentCount: number } }> {
    return apiClient.get('/admin/categories')
  },
  async create(input: CategoryInput): Promise<Category> {
    const response = await apiClient.post<{ data: Category }>('/admin/categories', input)
    return response.data
  },
  async update(id: string, input: Partial<CategoryInput>): Promise<Category> {
    const response = await apiClient.patch<{ data: Category }>(`/admin/categories/${id}`, input)
    return response.data
  },
  async remove(id: string): Promise<void> {
    await apiClient.delete(`/admin/categories/${id}`)
  },
}
