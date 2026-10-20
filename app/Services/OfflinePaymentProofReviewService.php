<?php

namespace App\Services;

use App\Models\OfflinePaymentProof;
use App\Models\OfflinePaymentProofEvent;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflinePaymentProofReviewService
{
    public function review(int $proofId, string $decision, int $adminId, ?string $rejectionReason = null): void
    {
        DB::transaction(function () use ($proofId, $decision, $adminId, $rejectionReason) {
            $proof = OfflinePaymentProof::query()->whereKey($proofId)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($proof->order_id)->lockForUpdate()->firstOrFail();

            if ($proof->status !== 'pending') {
                abort(response()->json(['message' => 'This payment proof has already been reviewed.'], 409));
            }

            $proof->forceFill([
                'status' => $decision,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => $decision === 'rejected' ? $rejectionReason : null,
            ])->save();

            if ($decision === 'approved') {
                $transaction = PaymentTransaction::query()
                    ->where('provider', 'offline')
                    ->where('provider_reference', $proof->transaction_reference)
                    ->lockForUpdate()
                    ->first();

                if ($transaction && (int) $transaction->order_id !== (int) $order->id) {
                    abort(response()->json(['message' => 'This payment reference is already linked to another order.'], 409));
                }

                if (!$transaction) {
                    PaymentTransaction::create([
                        'uuid' => (string) Str::uuid(),
                        'customer_id' => $proof->customer_id,
                        'order_id' => $order->id,
                        'provider' => 'offline',
                        'provider_reference' => $proof->transaction_reference,
                        'amount' => $order->order_amount,
                        'currency' => $proof->currency,
                        'status' => 'paid',
                        'metadata' => ['proof_uuid' => $proof->uuid, 'reviewed_by' => $adminId],
                        'paid_at' => now(),
                    ]);
                }

                if ($order->payment_status !== 'paid') {
                    $order->forceFill([
                        'payment_status' => 'paid',
                        'transaction_ref' => $proof->transaction_reference,
                        'payment_by' => 'offline_payment',
                    ])->save();
                    DB::table('order_details')->where('order_id', $order->id)->update(['payment_status' => 'paid']);
                }
            }

            OfflinePaymentProofEvent::create([
                'proof_id' => $proof->id,
                'order_id' => $order->id,
                'actor_id' => $adminId,
                'actor_type' => 'admin',
                'event' => $decision,
                'from_status' => 'pending',
                'to_status' => $decision,
                'note' => $decision === 'rejected' ? $rejectionReason : null,
            ]);
        });
    }
}
