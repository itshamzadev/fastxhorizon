<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\PartnerProfile;
use App\Models\PartnerPayoutRequest;
use App\Models\PartnerLedgerTransaction;
use App\Models\PartnerAuditEvent;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\PartnerProgramNotification;
use App\Services\PartnerProgramService;
use Illuminate\Http\Request;

class PartnerProgramController extends Controller
{
    public function applications(string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        return view('admin-views.partner.applications', ['program' => $program, 'applications' => PartnerApplication::with('user')->where('program', $program)->latest()->paginate(25)]);
    }

    public function approve(Request $request, string $program, PartnerApplication $application, PartnerProgramService $service)
    {
        abort_unless($application->program === $program, 404);
        $profile = $service->approveApplication($application, (int) auth('admin')->id());
        $application->user?->notify(new PartnerProgramNotification($program, 'approved', $profile->code));
        PartnerAuditEvent::create(['admin_id' => auth('admin')->id(), 'program' => $program, 'action' => 'application_approved', 'metadata' => ['application_id' => $application->id]]);
        return back()->with('success', ucfirst($program) . ' application approved.');
    }

    public function reject(Request $request, string $program, PartnerApplication $application)
    {
        abort_unless($application->program === $program, 404);
        $application->update(['status' => 'rejected', 'admin_notes' => $request->input('admin_notes'), 'reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
        $application->user?->notify(new PartnerProgramNotification($program, 'rejected'));
        PartnerAuditEvent::create(['admin_id' => auth('admin')->id(), 'program' => $program, 'action' => 'application_rejected', 'metadata' => ['application_id' => $application->id]]);
        return back()->with('success', ucfirst($program) . ' application rejected.');
    }

    public function profiles(string $program)
    {
        return view('admin-views.partner.profiles', ['program' => $program, 'profiles' => PartnerProfile::with('user')->where('program', $program)->latest()->paginate(25)]);
    }

    public function payouts(string $program)
    {
        return view('admin-views.partner.payouts', ['program' => $program, 'payouts' => PartnerPayoutRequest::with('profile.user')->where('program', $program)->latest()->paginate(25)]);
    }

    public function updatePayout(Request $request, string $program, PartnerPayoutRequest $payout)
    {
        abort_unless($payout->program === $program, 404);
        $request->validate(['status' => ['required', 'in:approved,processing,paid,rejected'], 'admin_note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $payout) {
            $payout = PartnerPayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status === 'paid') return;
            $payout->update(['status' => $request->status, 'admin_note' => $request->admin_note, 'reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
            if ($request->status === 'paid') PartnerLedgerTransaction::firstOrCreate(['payout_request_id' => $payout->id, 'type' => 'payout'], ['uuid' => (string) Str::uuid(), 'partner_profile_id' => $payout->partner_profile_id, 'amount' => -abs((float) $payout->amount), 'reference' => 'payout:' . $payout->id, 'note' => 'Partner payout completed.']);
            PartnerAuditEvent::create(['admin_id' => auth('admin')->id(), 'partner_profile_id' => $payout->partner_profile_id, 'program' => $payout->program, 'action' => 'payout_status_updated', 'metadata' => ['payout_id' => $payout->id, 'status' => $request->status]]);
        });
        return back()->with('success', 'Payout status updated.');
    }

    public function settings(string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        $defaults = config("fastxhorizon.partner_programs.{$program}", []);
        $settings = BusinessSetting::whereIn('type', collect($defaults)->keys()->map(fn ($key) => $program . '_' . $key))->pluck('value', 'type');
        $summary = [
            'pending_applications' => PartnerApplication::where('program', $program)->where('status', 'pending')->count(),
            'approved_profiles' => PartnerProfile::where('program', $program)->where('status', 'approved')->count(),
            'pending_payouts' => PartnerPayoutRequest::where('program', $program)->where('status', 'pending')->count(),
        ];
        return view('admin-views.partner.settings', compact('program', 'settings', 'defaults', 'summary'));
    }

    public function updateSettings(Request $request, string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'applications_enabled' => ['required', 'boolean'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_amount' => ['required', 'numeric', 'min:0'],
            'attribution_days' => ['required', 'integer', 'min:1', 'max:365'],
            'minimum_payout' => ['required', 'numeric', 'min:0'],
            'payout_methods' => ['required', 'array', 'min:1'],
            'payout_methods.*' => ['string', 'in:bank_transfer,mobile_wallet'],
        ]);
        if ($validated['commission_type'] === 'percentage' && (float) $validated['commission_amount'] > 100) {
            return back()->withErrors(['commission_amount' => 'Percentage commission must be between 0 and 100.'])->withInput();
        }

        $keys = ['enabled', 'applications_enabled', 'commission_type', 'commission_amount', 'attribution_days', 'minimum_payout', 'payout_methods'];
        $before = BusinessSetting::whereIn('type', collect($keys)->map(fn ($key) => $program . '_' . $key))->pluck('value', 'type');
        $beforeValues = [];
        foreach ($keys as $key) {
            $raw = $before->get($program . '_' . $key, config("fastxhorizon.partner_programs.{$program}.{$key}"));
            $beforeValues[$key] = $key === 'payout_methods' && is_string($raw) ? (json_decode($raw, true) ?: []) : $raw;
        }
        DB::transaction(function () use ($program, $validated, $keys, $beforeValues) {
            foreach ($keys as $key) {
                $value = $validated[$key];
                BusinessSetting::updateOrCreate(
                    ['type' => $program . '_' . $key],
                    ['value' => is_array($value) ? json_encode(array_values($value), JSON_UNESCAPED_SLASHES) : (string) $value]
                );
            }
            PartnerAuditEvent::create([
                'admin_id' => auth('admin')->id(),
                'program' => $program,
                'action' => 'settings_updated',
                'metadata' => ['old' => $beforeValues, 'new' => $validated],
            ]);
        });
        $warning = null;
        if ((float) $validated['commission_amount'] === 0.0) $warning = ucfirst($program) . ' commission is currently 0%. Approved partners will not earn commission until a rate is configured.';
        elseif ((float) $validated['minimum_payout'] === 0.0) $warning = 'Minimum payout is 0. Payouts may be requested without a threshold.';
        $redirect = back()->with('success', ucfirst($program) . ' program settings updated successfully.');
        return $warning ? $redirect->with('warning', $warning) : $redirect;
    }
}
