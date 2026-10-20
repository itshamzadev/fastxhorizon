<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\OfflinePaymentMethod;
use App\Models\OfflinePaymentProof;
use App\Models\OfflinePaymentProofEvent;
use App\Models\Order;
use App\Services\OrderService;
use App\Utils\OrderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrontendOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->owned($request, $id);
        return response()->json(['data' => (new OrderResource($order))->resolve()]);
    }

    public function tracking(Request $request, int $id): JsonResponse
    {
        $order = $this->owned($request, $id);
        $history = OrderManager::getTrackOrderStatusHistory(
            orderId: $order->id,
            isOrderOnlyDigital: $this->orderService->getCheckIsOrderOnlyDigital(order: $order),
        );

        return response()->json(['data' => $history]);
    }

    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $order = $this->owned($request, $id);
        $reference = (string) ($order->transaction_ref ?? '');
        $proof = $order->paymentProofs()->latest('id')->first();
        return response()->json(['data' => [
            'order_id' => (int) $order->id,
            'payment_method' => (string) $order->payment_method,
            'payment_status' => (string) $order->payment_status,
            'provider_reference' => $reference !== '' ? substr($reference, 0, 4) . '…' . substr($reference, -4) : null,
            'offline_proof' => $proof ? [
                'uuid' => $proof->uuid,
                'status' => $proof->status,
                'transaction_reference' => $proof->transaction_reference,
                'amount_claimed' => $proof->amount_claimed,
                'rejection_reason' => $proof->status === 'rejected' ? $proof->rejection_reason : null,
                'submitted_at' => optional($proof->created_at)->toISOString(),
                'reviewed_at' => optional($proof->reviewed_at)->toISOString(),
            ] : null,
            'updated_at' => optional($order->updated_at)->toISOString(),
        ]]);
    }

    public function submitPaymentProof(Request $request, int $id): JsonResponse
    {
        $order = $this->owned($request, $id);
        $request->validate([
            'method_id' => ['required', 'integer'],
            'transaction_reference' => ['required', 'string', 'max:191'],
            'amount_claimed' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($order->payment_method !== 'offline_payment' || $order->payment_status === 'paid') {
            return response()->json(['message' => 'This order is not eligible for an offline payment proof.'], 422);
        }

        $method = OfflinePaymentMethod::query()->whereKey($request->integer('method_id'))->where('status', 1)->first();
        if (!$method) {
            return response()->json(['message' => 'The selected offline payment method is unavailable.'], 422);
        }

        $configuredMethodId = data_get($order->offlinePayments?->payment_info, 'method_id');
        if ($configuredMethodId !== null && (int) $configuredMethodId !== (int) $method->id) {
            return response()->json(['message' => 'The selected payment method does not match this order.'], 422);
        }

        if (abs((float) $request->input('amount_claimed') - (float) $order->order_amount) > 0.01) {
            return response()->json(['message' => 'The claimed payment amount must match the order amount.'], 422);
        }

        $file = $request->file('proof');
        $hash = hash_file('sha256', $file->getRealPath());
        if (OfflinePaymentProof::query()->where('order_id', $order->id)->where('file_hash', $hash)->exists()) {
            return response()->json(['message' => 'This payment proof has already been submitted.'], 409);
        }

        $disk = (string) config('filesystems.default', 'local');
        $path = $file->store('payment-proofs/' . $order->id, $disk);

        try {
            $proof = DB::transaction(function () use ($request, $order, $method, $file, $hash, $disk, $path) {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($lockedOrder->payment_method !== 'offline_payment' || $lockedOrder->payment_status === 'paid') {
                    abort(response()->json(['message' => 'This order is no longer eligible for a payment proof.'], 422));
                }
                if (OfflinePaymentProof::query()->where('order_id', $lockedOrder->id)->where('status', 'pending')->exists()) {
                    abort(response()->json(['message' => 'A payment proof is already awaiting review for this order.'], 409));
                }

                $proof = OfflinePaymentProof::create([
                    'uuid' => (string) Str::uuid(),
                    'customer_id' => $request->user()->id,
                    'order_id' => $lockedOrder->id,
                    'offline_method_id' => $method->id,
                    'transaction_reference' => trim((string) $request->input('transaction_reference')),
                    'amount_claimed' => $lockedOrder->order_amount,
                    'currency' => $lockedOrder->display_currency_code ?: ($lockedOrder->base_currency_code ?: getCurrencyCode()),
                    'proof_disk' => $disk,
                    'proof_path' => $path,
                    'proof_mime' => (string) $file->getMimeType(),
                    'proof_size' => (int) $file->getSize(),
                    'file_hash' => $hash,
                    'note' => $request->input('note'),
                    'status' => 'pending',
                ]);

                OfflinePaymentProofEvent::create([
                    'proof_id' => $proof->id,
                    'order_id' => $lockedOrder->id,
                    'actor_id' => $request->user()->id,
                    'actor_type' => 'customer',
                    'event' => 'submitted',
                    'from_status' => null,
                    'to_status' => 'pending',
                ]);

                return $proof;
            });
        } catch (QueryException $exception) {
            Storage::disk($disk)->delete($path);
            if (str_contains(strtolower($exception->getMessage()), 'offline_proof_order_file_unique')) {
                return response()->json(['message' => 'This payment proof has already been submitted.'], 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return response()->json(['data' => [
            'uuid' => $proof->uuid,
            'status' => $proof->status,
            'transaction_reference' => $proof->transaction_reference,
            'submitted_at' => optional($proof->created_at)->toISOString(),
        ]], 201);
    }

    private function owned(Request $request, int $id): Order
    {
        return Order::query()
            ->where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->where('is_guest', 0)
            ->with(['details', 'orderStatusHistory'])
            ->firstOrFail();
    }
}
