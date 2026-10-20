<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\WalletTransaction;
use App\Utils\Helpers;
use App\Models\AddFundBonusCategories;
use App\Models\CustomerWalletWithdrawalRequest;
use App\Models\WithdrawalMethod;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function App\Utils\payment_gateways;

class UserWalletController extends Controller
{

    public function index(Request $request): View|RedirectResponse
    {
        $walletStatus = getWebConfig(name: 'wallet_status');
        if ($walletStatus == 1) {
            $transactionTypes = $this->getSelectTransactionTypes(types: $request->get('types', []));
            $totalWalletBalance = auth('customer')->user()->wallet_balance ?? 0;
            $heldWithdrawalBalance = (float) CustomerWalletWithdrawalRequest::query()
                ->where('customer_id', auth('customer')->id())
                ->whereIn('status', ['pending', 'under_review', 'approved'])
                ->sum('held_amount');
            $withdrawableWalletBalance = max(0, (float) $totalWalletBalance - $heldWithdrawalBalance);

            $walletTransactionList = $this->getWalletTransactionList(request: $request, types: $transactionTypes);
            $paymentGatewayList = payment_gateways();
            $addFundBonusList = $this->getAddFundBonusList();

            $filterCount = count($request['types']??[]) + (int)!empty($request['transaction_range']) + (int)!empty($request['filter_by']);

            if ($request->has('flag') && $request['flag'] == 'success') {
                Toastr::success(translate('add_fund_to_wallet_success'));
                return redirect()->route('wallet');
            } else if ($request->has('flag') && $request['flag'] == 'fail') {
                Toastr::error(translate('add_fund_to_wallet_unsuccessful'));
                return redirect()->route('wallet');
            }

            $digitalPaymentStatus = getWebConfig(name: 'digital_payment');
            $addFundsToWallet = getWebConfig(name: 'add_funds_to_wallet');
            $addFundsToWalletStatus = $addFundsToWallet && count($paymentGatewayList) > 0 && ($digitalPaymentStatus['status'] ?? 0);

            return view(VIEW_FILE_NAMES['user_wallet'], [
                'addFundsToWalletStatus' => $addFundsToWalletStatus,
                'totalWalletBalance' => $totalWalletBalance,
                'withdrawableWalletBalance' => $withdrawableWalletBalance,
                'heldWithdrawalBalance' => $heldWithdrawalBalance,
                'walletTransactionList' => $walletTransactionList,
                'paymentGatewayList' => $paymentGatewayList,
                'addFundBonusList' => $addFundBonusList,
                'transactionTypes' => $request->get('types', []),
                'filterCount' => $filterCount,
                'filterBy' => $request['filter_by'] ?? '',
                'transactionRange' => $request['transaction_range'] ?? '',
            ]);

        } else {
            Toastr::warning(translate('access_denied!'));
            return redirect()->route('home');
        }
    }

    public function myWalletAccount(): View
    {
        return view(VIEW_FILE_NAMES['wallet_account']);
    }

    public function withdrawal(Request $request): View|RedirectResponse
    {
        if ((int) getWebConfig('wallet_withdrawal_enabled') !== 1) {
            Toastr::info('Wallet withdrawals are not currently enabled.');
            return redirect()->route('wallet');
        }

        $customer = auth('customer')->user();
        $requests = CustomerWalletWithdrawalRequest::query()
            ->where('customer_id', auth('customer')->id())
            ->latest()
            ->paginate(10);
        $held = (float) CustomerWalletWithdrawalRequest::where('customer_id', $customer->id)->whereIn('status', ['pending', 'under_review', 'approved'])->sum('held_amount');
        $walletBalance = (float) ($customer->wallet_balance ?? 0);
        $withdrawableBalance = max(0, $walletBalance - $held);
        $methods = WithdrawalMethod::query()->where('is_active', 1)->where('customer_status', 1)->orderBy('method_name')->get();

        return view('web-views.users-profile.wallet-withdrawal', compact('requests', 'methods', 'walletBalance', 'withdrawableBalance', 'held'));
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        if ((int) getWebConfig('wallet_withdrawal_enabled') !== 1) {
            return redirect()->route('wallet')->withErrors(['amount' => 'Wallet withdrawals are not currently enabled.']);
        }

        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'method' => 'required|integer', 'details' => 'required|array', 'customer_note' => 'nullable|string|max:1000', 'idempotency_key' => 'required|string|max:80']);
        $customerId = auth('customer')->id();
        $method = WithdrawalMethod::query()->whereKey($data['method'])->where('is_active', 1)->where('customer_status', 1)->first();
        if (!$method) return back()->withErrors(['method' => 'The selected withdrawal method is unavailable.'])->withInput();
        foreach (($method->method_fields ?? []) as $field) {
            $key = $field['input_name'] ?? null;
            if ($key && !empty($field['is_required']) && blank($data['details'][$key] ?? null)) return back()->withErrors(['details.' . $key => 'This field is required.'])->withInput();
        }
        $min = (float) getWebConfig('wallet_withdrawal_min_amount');
        $max = (float) getWebConfig('wallet_withdrawal_max_amount');
        $amount = round((float) $data['amount'], 2);
        if ($min > 0 && $amount < $min) return back()->withErrors(['amount' => 'The amount is below the minimum withdrawal limit.'])->withInput();
        if ($max > 0 && $amount > $max) return back()->withErrors(['amount' => 'The amount exceeds the maximum withdrawal limit.'])->withInput();
        try {
            DB::transaction(function () use ($data, $method, $amount, $customerId) {
                $customer = \App\Models\User::query()->lockForUpdate()->findOrFail($customerId);
                $duplicate = CustomerWalletWithdrawalRequest::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($duplicate) return;
                $dailyLimit = (float) getWebConfig('wallet_withdrawal_daily_limit');
                if ($dailyLimit > 0) {
                    $dailyTotal = (float) CustomerWalletWithdrawalRequest::query()
                        ->where('customer_id', $customer->id)
                        ->whereDate('created_at', now()->toDateString())
                        ->whereNotIn('status', ['rejected', 'cancelled'])
                        ->sum('amount');
                    if (round($dailyTotal + $amount, 2) > $dailyLimit) {
                        throw new \RuntimeException('The daily withdrawal limit has been reached.');
                    }
                }
                $held = (float) CustomerWalletWithdrawalRequest::where('customer_id', $customer->id)->whereIn('status', ['pending', 'under_review', 'approved'])->sum('held_amount');
                $withdrawable = round((float) ($customer->wallet_balance ?? 0) - $held, 2);
                if ($amount > $withdrawable) throw new \RuntimeException('The requested amount exceeds your withdrawable wallet balance.');
                $details = $data['details'];
                if (isset($details['mobile_number'])) $details['mobile_number'] = $this->normalizePakistanPhone($details['mobile_number']);
                CustomerWalletWithdrawalRequest::create(['customer_id'=>$customer->id,'amount'=>$amount,'held_amount'=>$amount,'withdrawable_amount'=>$withdrawable,'currency_code'=>'PKR','method'=>$method->method_name,'payment_details'=>json_encode($details),'reference'=>'FXH-WD-'.strtoupper((string) Str::ulid()),'idempotency_key'=>$data['idempotency_key'],'status'=>'pending','customer_note'=>$data['customer_note'] ?? null]);
            }, 3);
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        Toastr::success('Wallet withdrawal request submitted for review.');
        return redirect()->route('wallet-withdrawal');
    }

    public function cancelWithdrawal(int $id): RedirectResponse
    {
        try {
            DB::transaction(function () use ($id): void {
                $withdrawal = CustomerWalletWithdrawalRequest::query()
                    ->where('customer_id', auth('customer')->id())
                    ->lockForUpdate()
                    ->findOrFail($id);
                if ($withdrawal->status !== 'pending') {
                    throw new \RuntimeException('This withdrawal can no longer be cancelled.');
                }
                $withdrawal->update(['status' => 'cancelled', 'released_at' => now()]);
            }, 3);
        } catch (\Throwable $exception) {
            return back()->withErrors(['withdrawal' => $exception->getMessage()]);
        }
        Toastr::success('Wallet withdrawal cancelled and the held amount was released.');
        return back();
    }

    private function normalizePakistanPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone));
        if (str_starts_with($phone, '+92')) return '+92' . ltrim(substr($phone, 3), '0');
        if (str_starts_with($phone, '0092')) return '+92' . ltrim(substr($phone, 4), '0');
        if (str_starts_with($phone, '92')) return '+92' . ltrim(substr($phone, 2), '0');
        return '+92' . ltrim($phone, '0');
    }

    private function getWalletTransactionList(object|array $request, array $types)
    {
        $startDate = '';
        $endDate = '';
        if (isset($request['transaction_range']) && !empty($request['transaction_range'])) {
            $dates = explode(' - ', $request['transaction_range']);
            if (count($dates) !== 2 || !checkDateFormatInMDY($dates[0]) || !checkDateFormatInMDY($dates[1])) {
                Toastr::error(translate('Invalid_date_range_format'));
                return back();
            }
            $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->format('Y-m-d') . ' 00:00:00';
            $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->format('Y-m-d') . ' 23:59:59';
        }
        return WalletTransaction::where('user_id', auth('customer')->id())
            ->when($request->has('filter_by') && in_array($request['filter_by'], ['debit', 'credit']), function ($query) use ($request) {
                $query->when($request['filter_by'] == 'debit', function ($query) {
                    $query->where('debit', '!=', 0);
                })->when($request['filter_by'] == 'credit', function ($query) {
                    $query->where('debit', '=', 0);
                });
            })
            ->when(!empty($startDate) && !empty($endDate), function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when(!empty($types) || in_array('added_via_payment_method', $request['types'] ?? []) || in_array('earned_by_referral', $request['types'] ?? []), function ($query) use ($types, $request) {
                $query->where(function ($query) use ($types, $request) {
                    return $query->when(!empty($types), function ($query) use ($types, $request) {
                        return $query->when(!in_array('earned_by_referral', $types), function ($query) use ($types) {
                                return $query->where('reference', '!=', 'earned_by_referral');
                            })->whereIn('transaction_type', $types);
                    })->when(in_array('added_via_payment_method', $request['types'] ?? []), function ($query) use ($types, $request) {
                        return $query->orWhere('reference', 'add_funds_to_wallet');
                    })->when(in_array('earned_by_referral', $request['types'] ?? []), function ($query) use ($types, $request) {
                       return $query->orWhere('reference', 'earned_by_referral');
                    })->when(in_array('add_fund_by_admin', $request['types'] ?? []), function ($query) use ($types, $request) {
                       return $query->orWhere('transaction_type', 'add_fund_by_admin')
                           ->orWhere(function ($query) use ($types, $request) {
                               return $query->whereNull('reference');
                           });
                    })->when(in_array('due_payment_for_order', $request['types'] ?? []), function ($query) use ($types, $request) {
                       return $query->orWhere('transaction_type', 'due_payment_for_order');
                    });
                });
            })
            ->latest()
            ->paginate(10)->appends(request()->query());
    }

    public function getAddFundBonusList()
    {
        return AddFundBonusCategories::where('is_active', 1)
            ->whereDate('start_date_time', '<=', date('Y-m-d'))
            ->whereDate('end_date_time', '>=', date('Y-m-d'))
            ->get();
    }

    public function getSelectTransactionTypes($types): array
    {
        $typeMapping = [
            'order_refund' => 'order_refund',
            'order_place' => 'order_place',
            'loyalty_point' => 'loyalty_point',
            'add_fund' => 'add_fund',
            'add_fund_by_admin' => 'add_fund_by_admin',
            'due_payment_for_order' => 'due_payment_for_order',
            'return_order_amount_by_admin' => 'return_order_amount_by_admin',
            'admin_debit' => 'admin_debit',
        ];

        $transactionTypes = [];
        foreach ($typeMapping as $key => $value) {
            if (in_array($key, $types)) {
                $transactionTypes[] = $value;
            }
        }
        return $transactionTypes ?? [];
    }
}
