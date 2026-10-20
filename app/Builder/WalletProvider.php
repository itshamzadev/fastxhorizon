<?php

namespace App\Builder;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Builder\Contracts\WalletProvider as WalletProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\WalletSummaryDTO;

/**
 * 6Valley host adapter for the storefront wallet surface.
 *
 * Read paths (summary + transactions) project the customer's
 * `users.wallet_balance` and the shared `wallet_transactions` ledger.
 *
 * Add-fund is intentionally gated: 6Valley routes wallet top-ups through its own
 * payment-request pipeline and has no `wallet_payments` table/model matching the
 * contract's WalletPayment flow, and the storefront wallet feature is disabled
 * via config('builder.wallet_features_enabled'). initiateAddFund therefore
 * returns a structured "unavailable" error and addFundResult returns null until
 * a host wallet-payment bridge exists.
 */
class WalletProvider implements WalletProviderContract
{
    private const TYPE_LABELS = [
        'add_fund'             => 'Add Fund',
        'add_fund_by_admin'    => 'Admin Bonus',
        'order_place'          => 'Order Place',
        'order_refund'         => 'Order Refund',
        'loyalty_point'        => 'Loyalty Point',
        'due_payment_for_order' => 'Due Payment',
        'referral_earning'     => 'Referral',
    ];

    public function summary(int $customerId): WalletSummaryDTO
    {
        if ($customerId <= 0) {
            return $this->emptySummary();
        }

        $user = User::query()->select(['id', 'wallet_balance', 'loyalty_point', 'created_at'])->find($customerId);
        if (!$user) {
            return $this->emptySummary();
        }

        $totalOrders = Order::query()
            ->where('customer_id', $customerId)
            ->where('is_guest', 0)
            ->count();

        $joinedDaysAgo = $user->created_at
            ? max(0, (int) Carbon::parse($user->created_at)->diffInDays(now()))
            : 0;

        return WalletSummaryDTO::fromArray([
            // wallet_balance is stored in the default currency — convert for
            // display; loyaltyPoint is a point count, not money, so leave it.
            'balance'          => $this->money($user->wallet_balance),
            'loyaltyPoint'     => (float) $user->loyalty_point,
            'joinedDaysAgo'    => $joinedDaysAgo,
            'totalOrders'      => $totalOrders,
            'transactionTypes' => $this->distinctTransactionTypes($customerId),
        ]);
    }

    public function transactions(int $customerId, ?string $type, int $perPage, int $page): PaginatedResult
    {
        if ($customerId <= 0) {
            return PaginatedResult::fromPaginator(new LengthAwarePaginator([], 0, $perPage, $page, ['pageName' => 'walletPage']));
        }

        $query = WalletTransaction::query()->where('user_id', $customerId);

        $filterSlug = $this->normaliseType($type);
        if ($filterSlug !== null) {
            $query->where('transaction_type', $filterSlug);
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page, pageName: 'walletPage')
            ->through(fn (WalletTransaction $txn) => $this->mapTransaction($txn));

        return PaginatedResult::fromPaginator($paginator);
    }

    public function initiateAddFund(int $customerId, float $amount, string $methodKey, string $paymentPlatform = 'web'): array
    {
        // 6Valley has no wallet_payments bridge for the module's add-fund flow,
        // and the storefront wallet feature is disabled by config.
        return [
            'success'         => false,
            'walletPaymentId' => null,
            'paymentRedirect' => null,
            'errors'          => [['code' => 'unavailable', 'message' => translate('Wallet_top_up_is_currently_unavailable') ?: 'Wallet top-up is currently unavailable.']],
        ];
    }

    public function addFundResult(int $walletPaymentId): ?array
    {
        return null;
    }

    private function emptySummary(): WalletSummaryDTO
    {
        return WalletSummaryDTO::fromArray([
            'balance'          => 0.0,
            'loyaltyPoint'     => 0.0,
            'joinedDaysAgo'    => 0,
            'totalOrders'      => 0,
            'transactionTypes' => [],
        ]);
    }

    private function distinctTransactionTypes(int $customerId): array
    {
        $slugs = WalletTransaction::query()
            ->where('user_id', $customerId)
            ->whereNotNull('transaction_type')
            ->distinct()
            ->pluck('transaction_type')
            ->all();

        $rows = [];
        foreach ($slugs as $slug) {
            $rows[] = ['slug' => (string) $slug, 'label' => $this->labelFor((string) $slug)];
        }
        usort($rows, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $rows;
    }

    private function mapTransaction(WalletTransaction $txn): array
    {
        // Ledger amounts are stored in the default currency — convert for display.
        $credit = $this->money($txn->credit);
        $debit = $this->money($txn->debit);
        $direction = (float) $txn->credit >= (float) $txn->debit ? 'credit' : 'debit';
        $slug = (string) ($txn->transaction_type ?? '');

        return [
            'id'            => (int) $txn->id,
            'transactionId' => (string) $txn->transaction_id,
            'type'          => $slug,
            'typeLabel'     => $this->labelFor($slug),
            'credit'        => $credit,
            'debit'         => $debit,
            'amount'        => $direction === 'credit' ? $credit : $debit,
            'direction'     => $direction,
            'balance'       => $this->money($txn->balance),
            'reference'     => $txn->reference !== null ? (string) $txn->reference : null,
            'date'          => $txn->created_at ? Carbon::parse($txn->created_at)->format('d M Y, h:iA') : '',
        ];
    }

    /**
     * Convert a default-currency amount to the active web currency for display.
     */
    private function money(float|int|null $amount): float
    {
        return (float) webCurrencyConverterOnlyDigit(amount: (float) ($amount ?? 0));
    }

    private function normaliseType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $trimmed = trim($type);
        if ($trimmed === '' || strtolower($trimmed) === 'all') {
            return null;
        }
        return $trimmed;
    }

    private function labelFor(string $slug): string
    {
        return self::TYPE_LABELS[$slug] ?? ucwords(str_replace('_', ' ', $slug));
    }
}
