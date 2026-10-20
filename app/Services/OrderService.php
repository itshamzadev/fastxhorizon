<?php

namespace App\Services;

use Modules\TaxModule\app\Traits\VatTaxManagement;
use App\Services\FastXHorizonCurrencyService;

class OrderService
{
    use VatTaxManagement;

    public function __construct()
    {
    }

    public function getPOSOrderData(int|string $orderId, array $cart, float $amount, float $totalTaxAmount, float $paidAmount, string $paymentType, string $addedBy, int $userId): array
    {
        $taxConfig = self::getTaxSystemType();
        $currencyService = app(FastXHorizonCurrencyService::class);
        $displayCurrencyCode = session('currency_code', $currencyService->baseCode());
        $baseAmount = currencyConverter(amount: $amount);

        return [
            'id' => $orderId,
            'customer_id' => $userId,
            'customer_type' => 'customer',
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'seller_id' => $addedBy == 'seller' ? auth('seller')->id() : auth('admin')->id(),
            'seller_is' => $addedBy,
            'payment_method' => $paymentType,
            'order_type' => 'POS',
            'checked' => 1,
            'total_tax_amount' => $totalTaxAmount,
            'tax_type' => $taxConfig['SystemTaxVatType'],
            'tax_model' => $taxConfig['is_included'] ? 'include' : 'exclude',
            'extra_discount' => $cart['ext_discount'] ?? 0,
            'extra_discount_type' => $cart['ext_discount_type'] ?? null,
            'order_amount' => $baseAmount,
            'init_order_amount' => $baseAmount,
            'paid_amount' => currencyConverter(amount: $paidAmount),
            'base_currency_code' => $currencyService->baseCode(),
            'display_currency_code' => $displayCurrencyCode,
            'currency_exchange_rate' => $currencyService->displayRate($displayCurrencyCode),
            'display_order_amount' => $currencyService->fromBase($baseAmount, $displayCurrencyCode),
            'discount_amount' => $cart['coupon_discount'] ?? 0,
            'coupon_code' => $cart['coupon_code'] ?? null,
            'discount_type' => (isset($cart['coupon_code']) && $cart['coupon_code']) ? 'coupon_discount' : NULL,
            'coupon_discount_bearer' => $cart['coupon_bearer'] ?? 'inhouse',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function getCheckIsOrderOnlyDigital(object $order): bool
    {
        $isOrderOnlyDigital = true;
        if ($order->orderDetails) {
            foreach ($order->orderDetails as $detail) {
                $product = json_decode($detail->product_details);
                if (isset($product->product_type) && $product->product_type == 'physical') {
                    $isOrderOnlyDigital = false;
                }
            }
        }
        return $isOrderOnlyDigital;
    }

}
