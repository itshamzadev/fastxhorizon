<?php

namespace App\Builder;

use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use App\Utils\CustomerManager;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\LoyaltyPointProvider as LoyaltyPointProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\LoyaltySummaryDTO;

/**
 * 6Valley host adapter for the storefront loyalty-point surface.
 *
 * Reads the customer's `users.loyalty_point`, the loyalty business settings and
 * the shared `loyalty_point_transactions` ledger. Point→wallet conversion is
 * delegated to the host CustomerManager helpers (same path the legacy site and
 * mobile API use), so the exchange-rate math and both ledger writes stay
 * identical across surfaces.
 */
class LoyaltyPointProvider implements LoyaltyPointProviderContract
{
    private const TYPE_LABELS = [
        'point_to_wallet' => 'Point To Wallet',
        'order_place'     => 'Order Place',
        'refund_order'    => 'Order Refund',
    ];

    public function summary(int $customerId): LoyaltySummaryDTO
    {
        $settings = $this->loyaltySettings();

        $balance = 0.0;
        $walletBalance = 0.0;
        if ($customerId > 0) {
            $user = User::query()->select(['id', 'loyalty_point', 'wallet_balance'])->find($customerId);
            $balance = (float) ($user->loyalty_point ?? 0);
            $walletBalance = (float) ($user->wallet_balance ?? 0);
        }

        return LoyaltySummaryDTO::fromArray([
            // balance is a point count (leave); walletBalance is default-currency money.
            'balance'           => $balance,
            'walletBalance'     => $this->money($walletBalance),
            'exchangeRate'      => (float) ($settings['exchange_rate'] ?? 0),
            'minimumPoint'      => (int) ($settings['minimum_point'] ?? 0),
            'itemPurchasePoint' => (float) ($settings['item_purchase_point'] ?? 0),
            'status'            => (bool) (int) ($settings['status'] ?? 0),
        ]);
    }

    public function transactions(int $customerId, int $perPage, int $page): PaginatedResult
    {
        if ($customerId <= 0) {
            return PaginatedResult::fromPaginator(new LengthAwarePaginator([], 0, $perPage, $page, ['pageName' => 'loyaltyPage']));
        }

        $paginator = LoyaltyPointTransaction::query()
            ->where('user_id', $customerId)
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page, pageName: 'loyaltyPage')
            ->through(fn (LoyaltyPointTransaction $txn) => $this->mapTransaction($txn));

        return PaginatedResult::fromPaginator($paginator);
    }

    public function convertToWallet(int $customerId, int $point): array
    {
        if ($customerId <= 0) {
            return $this->convertError('auth', translate('Please_sign_in_to_convert_points') ?: 'Please sign in to convert points.');
        }

        $settings = $this->loyaltySettings();

        if ((int) ($settings['status'] ?? 0) !== 1) {
            return $this->convertError('loyalty_disabled', translate('Loyalty_point_feature_is_currently_disabled') ?: 'Loyalty point feature is currently disabled.');
        }

        $minimum = (int) ($settings['minimum_point'] ?? 0);
        $rate = (int) ($settings['exchange_rate'] ?? 0);
        if ($rate <= 0) {
            return $this->convertError('exchange_rate', translate('Exchange_rate_is_not_configured') ?: 'Exchange rate is not configured.');
        }
        if ($point <= 0) {
            return $this->convertError('point', translate('Please_enter_a_valid_point_amount') ?: 'Please enter a valid point amount.');
        }
        if ($point < $minimum) {
            return $this->convertError('point', translate('Minimum_points_required_to_convert') . " ({$minimum})");
        }

        $user = User::query()->find($customerId);
        if (!$user || (int) $user->loyalty_point < $point) {
            return $this->convertError('point', translate('Insufficient_loyalty_point_balance') ?: 'Insufficient loyalty point balance.');
        }

        try {
            DB::transaction(function () use ($customerId, $point) {
                $locked = User::query()->lockForUpdate()->find($customerId);
                if ((int) $locked->loyalty_point < $point) {
                    throw new \RuntimeException('insufficient_point');
                }

                $walletTransaction = CustomerManager::create_wallet_transaction($locked->id, (float) $point, 'loyalty_point', null);
                if (!$walletTransaction) {
                    throw new \RuntimeException('wallet_transaction_failed');
                }

                CustomerManager::create_loyalty_point_transaction($locked->id, $walletTransaction->transaction_id, $point, 'point_to_wallet');
            });

            $fresh = User::query()->select(['id', 'loyalty_point', 'wallet_balance'])->find($customerId);

            return [
                'success'          => true,
                'pointsConverted'  => $point,
                // walletCredit / newWalletBalance are money (default currency) — convert;
                // newBalance is a point count, left as-is.
                'walletCredit'     => $this->money($point / $rate),
                'newBalance'       => (float) ($fresh->loyalty_point ?? 0),
                'newWalletBalance' => $this->money($fresh->wallet_balance ?? 0),
                'errors'           => [],
            ];
        } catch (\Throwable $exception) {
            $code = $exception->getMessage() === 'insufficient_point' ? 'point' : 'transfer_failed';
            $message = $code === 'point'
                ? (translate('Insufficient_loyalty_point_balance') ?: 'Insufficient loyalty point balance.')
                : (translate('Failed_to_convert') ?: 'Failed to convert. Please try again.');
            return $this->convertError($code, $message);
        }
    }

    private function loyaltySettings(): array
    {
        return [
            'status'              => getWebConfig(name: 'loyalty_point_status'),
            'exchange_rate'       => getWebConfig(name: 'loyalty_point_exchange_rate'),
            'minimum_point'       => getWebConfig(name: 'loyalty_point_minimum_point'),
            'item_purchase_point' => getWebConfig(name: 'loyalty_point_item_purchase_point'),
        ];
    }

    private function mapTransaction(LoyaltyPointTransaction $txn): array
    {
        $credit = (float) $txn->credit;
        $debit = (float) $txn->debit;
        $direction = $credit >= $debit ? 'credit' : 'debit';
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
            'balance'       => (float) $txn->balance,
            'reference'     => $txn->reference !== null ? (string) $txn->reference : null,
            'date'          => $txn->created_at ? Carbon::parse($txn->created_at)->format('d M Y, h:iA') : '',
        ];
    }

    private function labelFor(string $slug): string
    {
        return self::TYPE_LABELS[$slug] ?? ucwords(str_replace('_', ' ', $slug));
    }

    /**
     * Convert a default-currency amount to the active web currency for display.
     */
    private function money(float|int|null $amount): float
    {
        return (float) webCurrencyConverterOnlyDigit(amount: (float) ($amount ?? 0));
    }

    private function convertError(string $code, string $message): array
    {
        return [
            'success'          => false,
            'pointsConverted'  => null,
            'walletCredit'     => null,
            'newBalance'       => null,
            'newWalletBalance' => null,
            'errors'           => [['code' => $code, 'message' => $message]],
        ];
    }
}
