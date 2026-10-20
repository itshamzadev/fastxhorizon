<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\OfflinePaymentMethod;
use App\Services\FrontendCheckout\CheckoutQuoteService;
use App\Utils\OrderManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FrontendCheckoutController extends Controller
{
    public function __construct(private readonly CheckoutQuoteService $quotes)
    {
    }

    public function shippingOptions(Request $request): JsonResponse
    {
        $cart = $this->quotes->cart($request);
        return response()->json(['data' => $this->quotes->shippingOptions($cart)]);
    }

    public function offlineOptions(): JsonResponse
    {
        $config = getWebConfig(name: 'offline_payment');
        if ((int) ($config['status'] ?? 0) !== 1) {
            return response()->json(['data' => []]);
        }

        $methods = OfflinePaymentMethod::query()->where('status', 1)->get();
        return response()->json(['data' => $methods->map(fn ($method) => [
            'id' => (int) $method->id,
            'name' => (string) $method->method_name,
            'fields' => $method->method_fields ?? [],
            'instructions' => $method->method_informations ?? [],
        ])->values()->all()]);
    }

    public function preview(Request $request): JsonResponse
    {
        $quote = $this->quotes->quote($request);
        return response()->json(['data' => $quote]);
    }

    public function coupon(Request $request): JsonResponse
    {
        $request->validate(['coupon_code' => ['nullable', 'string', 'max:100']]);
        return $this->preview($request);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $request->validate([
            'address_id' => ['required', 'integer'],
            'billing_address_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'in:cash_on_delivery,offline_payment'],
            'method_id' => ['required_if:payment_method,offline_payment', 'integer'],
            'method_informations' => ['required_if:payment_method,offline_payment', 'array'],
            'order_note' => ['nullable', 'string', 'max:1000'],
            'bring_change_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $key = trim((string) $request->header('Idempotency-Key', $request->input('idempotency_key', '')));
        if ($key === '' || strlen($key) > 191) {
            return response()->json(['message' => 'Idempotency-Key header is required for order creation.'], 422);
        }

        $payload = $request->except(['idempotency_key']);
        ksort($payload);
        $requestHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $customerId = (int) $request->user()->id;

        try {
            $result = DB::transaction(function () use ($request, $key, $requestHash, $customerId) {
                // Serializes this customer's concurrent checkout attempts while the
                // database unique key protects retries across application nodes.
                User::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
                $existing = DB::table('frontend_checkout_idempotencies')
                    ->where(['customer_id' => $customerId, 'idempotency_key' => $key])
                    ->lockForUpdate()->first();

                if ($existing) {
                    if (!hash_equals($existing->request_hash, $requestHash)) {
                        abort(response()->json(['message' => 'This Idempotency-Key was already used with a different checkout request.'], 409));
                    }
                    if ($existing->status === 'completed' && $existing->response_json) {
                        return ['replay' => json_decode($existing->response_json, true)];
                    }
                    abort(response()->json(['message' => 'This checkout request is already being processed.'], 409));
                }

                DB::table('frontend_checkout_idempotencies')->insert([
                    'customer_id' => $customerId,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $cart = $this->quotes->cart($request);
                $physicalIds = $cart->where('product_type', 'physical')->pluck('product_id')->unique();
                if ($physicalIds->isNotEmpty()) {
                    Product::query()->whereIn('id', $physicalIds)->lockForUpdate()->get();
                }

                $quote = $this->quotes->quote($request);
                if (!$quote['can_place_order']) {
                    throw ValidationException::withMessages(['checkout' => $quote['warnings']]);
                }

                $offlineInfo = [];
                if ($request->input('payment_method') === 'offline_payment') {
                    $method = OfflinePaymentMethod::query()->where('id', $request->integer('method_id'))->where('status', 1)->first();
                    if (!$method) {
                        throw ValidationException::withMessages(['method_id' => 'Offline payment method is unavailable.']);
                    }
                    $allowedFields = array_column($method->method_informations ?? [], 'customer_input');
                    $submitted = $request->input('method_informations', []);
                    foreach ($allowedFields as $field) {
                        if (array_key_exists($field, $submitted)) {
                            $offlineInfo[$field] = is_scalar($submitted[$field]) ? (string) $submitted[$field] : $submitted[$field];
                        }
                    }
                    $offlineInfo = array_merge(['method_id' => $method->id, 'method_name' => $method->method_name], $offlineInfo);
                }

                $paymentMethod = $request->input('payment_method');
                $orderIds = OrderManager::generateOrder([
                    'is_guest' => 0,
                    'customer_id' => $customerId,
                    'order_status' => 'pending',
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'unpaid',
                    'transaction_ref' => '',
                    'address_id' => (int) $request->input('address_id'),
                    'billing_address_id' => $request->filled('billing_address_id') ? (int) $request->input('billing_address_id') : null,
                    'coupon_code' => trim((string) $request->input('coupon_code', '')),
                    'bring_change_amount' => $request->input('bring_change_amount', 0),
                    'bring_change_amount_currency' => $quote['currency'],
                    'requestObj' => $request,
                    'order_note' => $request->input('order_note', ''),
                    'offline_payment_info' => $offlineInfo,
                ]);

                $response = [
                    'order_ids' => array_values($orderIds),
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'unpaid',
                    'quote' => $quote,
                ];

                DB::table('frontend_checkout_idempotencies')->where([
                    'customer_id' => $customerId,
                    'idempotency_key' => $key,
                ])->update([
                    'status' => 'completed',
                    'order_ids' => json_encode(array_values($orderIds)),
                    'response_json' => json_encode($response),
                    'updated_at' => now(),
                ]);

                return ['response' => $response];
            }, 2);
        } catch (QueryException $exception) {
            // The unique customer/key index is the final cross-node guard.
            if (str_contains(strtolower($exception->getMessage()), 'frontend_checkout_customer_key_unique')) {
                $existing = DB::table('frontend_checkout_idempotencies')->where(['customer_id' => $customerId, 'idempotency_key' => $key])->first();
                if ($existing && $existing->status === 'completed' && $existing->response_json) {
                    return response()->json(['data' => json_decode($existing->response_json, true)], 200);
                }
            }
            throw $exception;
        }

        return response()->json(['data' => $result['replay'] ?? $result['response']], 201);
    }
}
