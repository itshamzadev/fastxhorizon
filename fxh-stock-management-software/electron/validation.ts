import { z } from 'zod';

export const productIdSchema = z.string().trim().min(1).max(200);
export const stockReceiveSchema = z.object({ variantId: productIdSchema, quantity: z.number().int().positive(), unitCost: z.number().finite().nonnegative(), reason: z.string().trim().max(500).optional() });
export const saleSchema = z.object({ variantId: productIdSchema, quantity: z.number().int().positive(), customerName: z.string().trim().max(160).optional(), customerPhone: z.string().trim().max(60).optional(), paymentMethod: z.string().trim().max(80).optional(), unitPrice: z.number().finite().nonnegative().optional(), deliveryCost: z.number().finite().nonnegative().optional(), customerDeliveryFee: z.number().finite().nonnegative().optional(), discount: z.number().finite().nonnegative().optional() });
export const syncConfigSchema = z.object({ apiBaseUrl: z.string().url().refine(value => value.startsWith('https://') || value.startsWith('http://127.0.0.1') || value.startsWith('http://localhost'), 'Only HTTPS or local development URLs are allowed'), deviceId: z.string().trim().min(8).max(120), deviceName: z.string().trim().min(1).max(120) });
export const syncRegistrationSchema = syncConfigSchema.extend({ registrationSecret: z.string().min(16).max(300) });
export const syncEventSchema = z.object({ eventId: z.string().trim().min(8).max(160).optional(), eventType: z.string().trim().min(1).max(80), entityType: z.string().trim().min(1).max(80), entityId: z.string().trim().min(1).max(200), payload: z.record(z.unknown()), idempotencyKey: z.string().trim().min(8).max(160).optional() });

export function parseIpc<T>(schema: z.ZodType<T>, input: unknown): T {
  const result = schema.safeParse(input);
  if (!result.success) throw new Error(result.error.issues.map(issue => `${issue.path.join('.') || 'input'}: ${issue.message}`).join('; '));
  return result.data;
}
