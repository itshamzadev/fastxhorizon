<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PartnerApplication;
use App\Models\PartnerAttribution;
use App\Models\PartnerCommission;
use App\Models\PartnerCommissionRule;
use App\Models\PartnerProfile;
use App\Models\PartnerLedgerTransaction;
use App\Models\PartnerPayoutRequest;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerProgramService
{
    public function setting(string $program, string $key, mixed $default = null): mixed
    {
        $value = BusinessSetting::where('type', $program . '_' . $key)->value('value');
        if ($value === null) return $default ?? config("fastxhorizon.partner_programs.{$program}.{$key}");
        if (in_array($key, ['enabled', 'applications_enabled'], true)) return (bool) (int) $value;
        if (in_array($key, ['commission_amount', 'minimum_payout'], true)) return (float) $value;
        if ($key === 'attribution_days') return (int) $value;
        if ($key === 'payout_methods') return json_decode($value, true) ?: [];
        return $value;
    }
    public function submitApplication(object $user, string $program, array $data): PartnerApplication
    {
        return DB::transaction(function () use ($user, $program, $data) {
            $existing = PartnerProfile::where(['user_id' => $user->id, 'program' => $program])->first();
            if ($existing && in_array($existing->status, ['pending', 'approved'], true)) {
                throw new \DomainException('You already have an active application for this program.');
            }
            $pending = PartnerApplication::where('user_id', $user->id)->where('program', $program)->whereIn('status', ['pending', 'submitted', 'under_review', 'needs_information'])->lockForUpdate()->first();
            if ($pending) throw new \DomainException('You already have an active application for this program.');
            return PartnerApplication::create([
                'user_id' => $user->id, 'program' => $program,
                'reference' => $this->uniqueApplicationReference($program),
                'display_name' => $data['display_name'], 'phone' => $data['phone'] ?? $user->phone,
                'city' => $data['city'] ?? null, 'province' => $data['province'] ?? null,
                'country' => $data['country'] ?? null, 'national_id' => $data['national_id'] ?? null,
                'address' => $data['address'] ?? null, 'sales_channel' => $data['sales_channel'] ?? null,
                'reason' => $data['reason'] ?? null, 'business_data' => $data['business_data'] ?? null,
                'sales_data' => $data['sales_data'] ?? null, 'status' => 'submitted', 'submitted_at' => now(),
                'terms_accepted_at' => now(), 'terms_version' => $data['terms_version'] ?? '2026-10-22',
            ]);
        });
    }

    public function approveApplication(PartnerApplication $application, int $adminId): PartnerProfile
    {
        return DB::transaction(function () use ($application, $adminId) {
            $application = PartnerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status === 'approved') return PartnerProfile::where(['user_id' => $application->user_id, 'program' => $application->program])->firstOrFail();
            $code = $this->uniqueCode($application->program, $application->user_id);
            $profile = PartnerProfile::updateOrCreate(
                ['user_id' => $application->user_id, 'program' => $application->program],
                ['uuid' => (string) Str::uuid(), 'code' => $code, 'display_name' => $application->display_name, 'phone' => $application->phone, 'city' => $application->city, 'province' => $application->province, 'address' => $application->address, 'status' => 'approved', 'approved_at' => now(), 'suspended_at' => null]
            );
            $application->update(['status' => 'approved', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'approved_at' => now()]);
            return $profile;
        });
    }

    public function profileForUser(object $user, string $program): ?PartnerProfile
    {
        return PartnerProfile::where(['user_id' => $user->id, 'program' => $program])->first();
    }

    public function captureOrderAttribution(Order $order): void
    {
        if (!$order->customer_id) return;
        foreach (['reseller', 'affiliate'] as $program) {
            $data = session("fastxhorizon_partner_{$program}");
            $code = $data['code'] ?? request()->cookie("fastxhorizon_{$program}");
            if (!$code) continue;
            $profile = PartnerProfile::where(['program' => $program, 'code' => $code, 'status' => 'approved'])->first();
            if (!$profile || $profile->user_id === (int) $order->customer_id) continue;
            PartnerAttribution::updateOrCreate(['partner_profile_id' => $profile->id, 'order_id' => $order->id, 'program' => $program], ['user_id' => $order->customer_id, 'code' => $profile->code, 'first_at' => now(), 'last_at' => now(), 'landing_url' => request()->fullUrl(), 'expires_at' => now()->addDays((int) $this->setting($program, 'attribution_days', 30))]);
            $this->createCommission($order, $profile, $program);
        }
    }

    public function createCommission(Order $order, PartnerProfile $profile, string $program): ?PartnerCommission
    {
        return DB::transaction(function () use ($order, $profile, $program) {
            if (PartnerCommission::where(['partner_profile_id' => $profile->id, 'order_id' => $order->id, 'program' => $program])->exists()) return null;
            $base = max(0, (float) $order->order_amount - (float) $order->shipping_cost - (float) $order->total_tax_amount);
            $rule = PartnerCommissionRule::where('program', $program)->where('is_active', true)->whereNull('product_id')->whereNull('category_id')->orderByDesc('priority')->first();
            $type = $rule?->commission_type ?? $this->setting($program, 'commission_type', 'percentage');
            $rate = (float) ($rule?->amount ?? $this->setting($program, 'commission_amount', 0));
            $amount = $type === 'fixed' ? $rate : ($base * $rate / 100);
            return PartnerCommission::create(['uuid' => (string) Str::uuid(), 'partner_profile_id' => $profile->id, 'order_id' => $order->id, 'program' => $program, 'base_amount' => $base, 'commission_type' => $type, 'rate' => $rate, 'amount' => round($amount, 3), 'status' => 'pending']);
        });
    }

    public function reconcileOrderStatus(Order $order): void
    {
        if (!$order->id) return;
        DB::transaction(function () use ($order) {
            $commissions = PartnerCommission::where('order_id', $order->id)->lockForUpdate()->get();
            foreach ($commissions as $commission) {
                $status = strtolower((string) $order->order_status);
                if (in_array($status, ['canceled', 'cancelled', 'returned', 'refunded'], true) && $commission->status !== 'reversed') {
                    $commission->update(['status' => 'reversed', 'reversed_at' => now()]);
                    PartnerLedgerTransaction::firstOrCreate(['commission_id' => $commission->id, 'type' => 'reversal'], ['uuid' => (string) Str::uuid(), 'partner_profile_id' => $commission->partner_profile_id, 'amount' => -abs((float) $commission->amount), 'reference' => 'order:' . $order->id, 'note' => 'Commission reversed after order status change.']);
                } elseif (in_array($status, ['delivered', 'completed'], true) && $commission->status === 'pending') {
                    $commission->update(['status' => 'available', 'available_at' => now()]);
                    PartnerLedgerTransaction::firstOrCreate(['commission_id' => $commission->id, 'type' => 'commission'], ['uuid' => (string) Str::uuid(), 'partner_profile_id' => $commission->partner_profile_id, 'amount' => $commission->amount, 'reference' => 'order:' . $order->id, 'note' => 'Commission made available after order completion.']);
                }
            }
        });
    }

    public function availableBalance(PartnerProfile $profile): float
    {
        return max(0, (float) $profile->ledger()->sum('amount') - (float) $profile->payouts()->whereIn('status', ['pending', 'approved', 'processing', 'paid'])->sum('amount'));
    }

    public function requestPayout(PartnerProfile $profile, array $data): PartnerPayoutRequest
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile = PartnerProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $minimum = (float) $this->setting($profile->program, 'minimum_payout', 0);
            $balance = $this->availableBalance($profile);
            $amount = (float) $data['amount'];
            if ($amount < $minimum || $amount > $balance) throw new \DomainException('The requested payout is not available.');
            return PartnerPayoutRequest::create(['uuid' => (string) Str::uuid(), 'partner_profile_id' => $profile->id, 'program' => $profile->program, 'amount' => $amount, 'payout_method' => $data['payout_method'], 'account_details' => $data['account_details'], 'status' => 'pending']);
        });
    }

    private function uniqueCode(string $program, int $userId): string
    {
        $prefix = $program === 'reseller' ? 'RS' : 'AF';
        do { $code = $prefix . strtoupper(Str::random(8)); } while (PartnerProfile::where('code', $code)->exists());
        return $code;
    }

    private function uniqueApplicationReference(string $program): string
    {
        do {
            $reference = 'FX-' . ($program === 'reseller' ? 'RS' : 'AF') . '-' . strtoupper(Str::random(10));
        } while (PartnerApplication::where('reference', $reference)->exists());

        return $reference;
    }
}
