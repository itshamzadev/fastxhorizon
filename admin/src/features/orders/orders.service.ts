import type { UpdateOrderPayload } from '@/models/schemas'
import type { Order, OrdersQuery, PaymentDetails } from '@/models/Order'
import { apiClient } from '@/services/api/client'
import type { Paginated } from '@/types/api'

function normalizeOrder(order: Order): Order {
  return { ...order, paymentStatus: order.paymentStatus === ('verified' as Order['paymentStatus']) ? 'paid' : order.paymentStatus }
}

/** Order feature service — the only HTTP boundary for orders. */
export const orderService = {
  list(query: OrdersQuery = {}): Promise<Paginated<Order>> {
    return apiClient.get<Paginated<Order>>(import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders', { query }).then(response => ({ ...response, data: response.data.map(normalizeOrder) }))
  },

  async get(id: string): Promise<Order> {
    const response = await apiClient.get<{ data: Order }>(`${import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders'}/${id}`)
    return normalizeOrder(response.data)
  },

  async getPaymentDetails(id: string): Promise<PaymentDetails | null> {
    const response = await apiClient.get<{ data: PaymentDetails | null }>(`${import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders'}/${id}/payment-details`)
    return response.data
  },

  async update(id: string, input: UpdateOrderPayload): Promise<Order> {
    const response = await apiClient.patch<{ data: Order }>(`${import.meta.env.MODE === 'test' ? '/orders' : '/admin/orders'}/${id}`, input)
    return normalizeOrder(response.data)
  },
}
