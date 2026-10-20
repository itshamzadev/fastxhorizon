<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\BaseController;
use App\Models\CustomerWalletWithdrawalRequest;
use App\Services\CustomerWalletLedgerService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerWalletWithdrawalController extends BaseController
{
    public function index(?Request $request = null, ?string $type = null): View
    {
        $requests = CustomerWalletWithdrawalRequest::with('customer')->latest()->paginate(25);
        return view('admin-views.customer.wallet.withdrawals', compact('requests'));
    }

    public function updateStatus(Request $request, int $id, CustomerWalletLedgerService $ledger): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:under_review,approved,rejected,paid',
            'admin_note' => 'nullable|string|max:2000',
            'payment_reference' => 'required_if:status,paid|nullable|string|max:255',
        ]);
        $withdrawal = CustomerWalletWithdrawalRequest::findOrFail($id);

        if ($withdrawal->status === 'paid') {
            return back()->withErrors(['status' => 'A paid withdrawal cannot be changed.']);
        }
        if ($data['status'] === 'paid' && $withdrawal->status !== 'approved') {
            return back()->withErrors(['status' => 'Approve the withdrawal before marking it paid.']);
        }
        try {
            DB::transaction(function () use ($data, $withdrawal, $ledger): void {
                $withdrawal = CustomerWalletWithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);
                if ($withdrawal->status === 'paid') {
                    throw new \RuntimeException('A paid withdrawal cannot be changed.');
                }
                if ($data['status'] === 'paid' && $withdrawal->status !== 'approved') {
                    throw new \RuntimeException('Approve the withdrawal before marking it paid.');
                }
                if ($data['status'] === 'paid') {
                    $transaction = $ledger->record(
                        userId: $withdrawal->customer_id,
                        credit: 0,
                        debit: $withdrawal->amount,
                        transactionType: 'wallet_withdrawal',
                        reference: $withdrawal->reference,
                        options: [
                            'currency_code' => 'PKR',
                            'source' => 'customer_wallet_withdrawal',
                            'created_by' => auth('admin')->id(),
                            'idempotency_key' => 'wallet-withdrawal:' . $withdrawal->id,
                            'ignore_withdrawal_holds' => true,
                            'description' => 'Approved Fast X Horizon Wallet withdrawal ' . $withdrawal->reference,
                        ],
                    );
                    if (!$transaction) {
                        throw new \RuntimeException('The withdrawal could not be paid because the wallet balance is insufficient or the request was already processed.');
                    }
                }

                $withdrawal->update([
                    'status' => $data['status'],
                    'admin_note' => $data['admin_note'] ?? $withdrawal->admin_note,
                    'payment_reference' => $data['payment_reference'] ?? $withdrawal->payment_reference,
                    'reviewed_by' => auth('admin')->id(),
                    'reviewed_at' => now(),
                    'paid_at' => $data['status'] === 'paid' ? now() : $withdrawal->paid_at,
                    'released_at' => in_array($data['status'], ['rejected', 'cancelled'], true) ? now() : $withdrawal->released_at,
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }
        ToastMagic::success('Wallet withdrawal status updated.');
        return back();
    }
}
