<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\CustomerWalletWithdrawalRequest;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontendWalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless((int) getWebConfig(name: 'wallet_status') === 1, 404);

        $balance = (float) ($request->user()->wallet_balance ?? 0);
        $held = (float) CustomerWalletWithdrawalRequest::query()
            ->where('customer_id', $request->user()->id)
            ->whereIn('status', ['pending', 'under_review', 'approved'])
            ->sum('held_amount');

        return response()->json(['data' => [
            'currency' => getWebConfig(name: 'currency_symbol') ?: 'PKR',
            'balance' => $balance,
            'held_withdrawal_amount' => $held,
            'withdrawable_balance' => max(0, $balance - $held),
        ]]);
    }

    public function transactions(Request $request): JsonResponse
    {
        abort_unless((int) getWebConfig(name: 'wallet_status') === 1, 404);

        $transactions = WalletTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'data' => $transactions->getCollection()->map(fn ($transaction) => [
                'id' => (int) $transaction->id,
                'type' => $transaction->transaction_type,
                'reference' => $transaction->reference,
                'credit' => (float) $transaction->credit,
                'debit' => (float) $transaction->debit,
                'balance' => (float) ($transaction->balance ?? 0),
                'created_at' => optional($transaction->created_at)->toISOString(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
