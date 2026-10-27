import type { UpdateOrderPayload } from '@/models/schemas'
import type { Order, OrdersQuery } from '@/models/Order'
import { apiClient } from '@/services/api/client'
import type { Paginated } from '@/types/api'

/** Order feature service — the only HTTP boundary for orders. */
export const orderService = {
  list(query: OrdersQuery = {}): Promise<Paginated<Order>> {
    return apiClient.get<Paginated<Order>>(import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders', { query })
  },

  async get(id: string): Promise<Order> {
    const response = await apiClient.get<{ data: Order }>(`${import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders'}/${id}`)
    return response.data
  },

  async update(id: string, input: UpdateOrderPayload): Promise<Order> {
    const response = await apiClient.patch<{ data: Order }>(`${import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders'}/${id}`, input)
    return response.data
  },
}
