<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\SafepayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FrontendPaymentController extends Controller
{
    public function __construct(private readonly SafepayGateway $safepay) {}

    public function initializeSafepay(Request $request): JsonResponse
    {
        $request->validate(['order_id' => ['required', 'integer']]);
        $order = Order::query()->whereKey($request->integer('order_id'))
            ->where('customer_id', $request->user()->id)
            ->where('is_guest', 0)
            ->firstOrFail();

        if ($order->payment_status === 'paid' || in_array($order->payment_method, ['cash_on_delivery', 'offline_payment'], true)) {
            return response()->json(['message' => 'This order is not eligible for Safepay payment.'], 422);
        }

        try {
            $result = $this->safepay->initialize([
                'order_id' => $order->id,
                'amount' => $order->order_amount,
                'currency' => $order->display_currency_code ?: ($order->base_currency_code ?: getCurrencyCode()),
            ]);
        } catch (RuntimeException $exception) {
            Log::notice('Safepay initialization unavailable', ['order_id' => $order->id, 'reason' => $exception->getMessage()]);
            return response()->json(['message' => 'Safepay sandbox is not configured or verified yet.'], 503);
        }

        return response()->json(['data' => $result]);
    }

    public function safepayWebhook(Request $request): JsonResponse
    {
        // Deliberately fail closed until Safepay's official signature contract
        // and sandbox credentials are installed and verified.
        return response()->json(['message' => 'Safepay webhook is not enabled.'], 503);
    }
}
