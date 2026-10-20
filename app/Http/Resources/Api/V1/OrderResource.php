<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'group_id' => $this->order_group_id,
            'status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'currency' => $this->display_currency_code ?: $this->base_currency_code,
            'total' => (float) ($this->display_order_amount ?? $this->order_amount),
            'subtotal' => (float) $this->order_amount,
            'discount' => (float) $this->discount_amount,
            'shipping' => (float) $this->shipping_cost,
            'tax' => (float) $this->total_tax_amount,
            'coupon_code' => $this->coupon_code,
            'created_at' => optional($this->created_at)->toISOString(),
            'shipping_address' => $this->shipping_address_data,
            'items' => $this->whenLoaded('details', fn () => $this->details->map(function ($detail) {
                $snapshot = is_array($detail->product_details)
                    ? $detail->product_details
                    : (json_decode((string) $detail->product_details, true) ?: []);

                return [
                    'id' => (int) $detail->id,
                    'product_id' => (int) $detail->product_id,
                    'name' => $snapshot['name'] ?? $snapshot['title'] ?? 'Product',
                    'quantity' => (int) $detail->qty,
                    'unit_price' => (float) $detail->price,
                    'discount' => (float) $detail->discount,
                    'delivery_status' => $detail->delivery_status,
                    'variation' => $detail->variation,
                ];
            })->values()->all()),
            'timeline' => $this->whenLoaded('orderStatusHistory', fn () => $this->orderStatusHistory->map(fn ($event) => [
                'status' => $event->status,
                'cause' => $event->cause,
                'created_at' => optional($event->created_at)->toISOString(),
            ])->values()->all()),
        ];
    }
}
