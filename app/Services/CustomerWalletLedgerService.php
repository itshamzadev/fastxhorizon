<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\CustomerWalletWithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CustomerWalletLedgerService
{
    /**
     * Record one immutable wallet ledger entry and update the cached balance.
     * Amounts passed to this method are in the wallet's internal currency unit,
     * matching the existing 6Valley wallet storage convention.
     */
    public function record(
        int|string $userId,
        float|int $credit,
        float|int $debit,
        string $transactionType,
        ?string $reference = null,
        array $options = [],
    ): WalletTransaction|false {
        if ((int) (getWebConfig(name: 'wallet_status') ?? 0) !== 1) {
            return false;
        }

        $credit = round(max(0, (float) $credit), 20);
        $debit = round(max(0, (float) $debit), 20);
        if (($credit > 0 && $debit > 0) || ($credit <= 0 && $debit <= 0)) {
            return false;
        }

        try {
            return DB::transaction(function () use ($userId, $credit, $debit, $transactionType, $reference, $options): WalletTransaction {
                $user = User::query()->lockForUpdate()->find($userId);
                if (!$user) {
                    throw new \RuntimeException('Customer wallet owner not found.');
                }

                $idempotencyKey = $options['idempotency_key'] ?? null;
                if ($idempotencyKey) {
                    $existing = WalletTransaction::query()
                        ->where('user_id', $user->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->latest('id')
                        ->first();
                    if ($existing) {
                        if (
                            abs((float) $existing->credit - $credit) > 0.0000000001
                            || abs((float) $existing->debit - $debit) > 0.0000000001
                            || $existing->transaction_type !== $transactionType
                        ) {
                            throw new \RuntimeException('Wallet idempotency key was already used for a different transaction.');
                        }
                        return $existing;
                    }
                }

                $before = round((float) ($user->wallet_balance ?? 0), 20);
                $after = round($before + $credit - $debit, 20);
                if ($after < -0.0000000001) {
                    throw new \RuntimeException('Insufficient wallet balance.');
                }
                if ($debit > 0 && empty($options['ignore_withdrawal_holds'])) {
                    $held = (float) CustomerWalletWithdrawalRequest::query()
                        ->where('customer_id', $user->id)
                        ->whereIn('status', ['pending', 'under_review', 'approved'])
                        ->sum('held_amount');
                    if ($after + 0.0000000001 < $held) throw new \RuntimeException('Insufficient withdrawable wallet balance.');
                }

                $transaction = new WalletTransaction();
                $transaction->user_id = $user->id;
                $transaction->transaction_id = $options['transaction_id'] ?? (string) Str::uuid();
                $transaction->reference = $reference;
                $transaction->transaction_type = $transactionType;
                $transaction->credit = $credit;
                $transaction->debit = $debit;
                $transaction->admin_bonus = round((float) ($options['admin_bonus'] ?? 0), 20);
                $transaction->balance = $after;
                $transaction->balance_before = $before;
                $transaction->balance_after = $after;
                $transaction->payment_method = $options['payment_method'] ?? null;
                // The customer wallet is single-currency in this phase.  Keep
                // its ledger in the configured Fast X Horizon base currency;
                // storefront display currency must never change wallet math.
                $transaction->currency_code = $options['currency_code']
                    ?? strtoupper((string) config('fastxhorizon_currency.base_currency', 'PKR'));
                $transaction->source = $options['source'] ?? 'system';
                $transaction->status = $options['status'] ?? 'completed';
                $transaction->idempotency_key = $idempotencyKey;
                $transaction->related_order_id = $options['related_order_id'] ?? null;
                $transaction->related_refund_id = $options['related_refund_id'] ?? null;
                $transaction->created_by = $options['created_by'] ?? null;
                $transaction->description = $options['description'] ?? $reference;

                $user->wallet_balance = $after;
                $user->save();
                $transaction->save();

                return $transaction;
            }, 3);
        } catch (Throwable $exception) {
            if (!in_array($exception->getMessage(), [
                'Insufficient wallet balance.',
                'Wallet idempotency key was already used for a different transaction.',
            ], true)) {
                report($exception);
            }
            return false;
        }
    }
}
