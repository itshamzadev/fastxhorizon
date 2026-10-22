<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PartnerProgramService;
use App\Notifications\PartnerProgramNotification;
use Illuminate\Http\Request;

class PartnerProgramController extends Controller
{
    public function __construct(private readonly PartnerProgramService $service) {}

    public function apply(string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        return view('partner.program', ['program' => $program, 'profile' => auth('customer')->user() ? $this->service->profileForUser(auth('customer')->user(), $program) : null]);
    }

    public function submit(Request $request, string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        $request->validate(['display_name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:30'], 'city' => ['nullable', 'string', 'max:80'], 'province' => ['nullable', 'string', 'max:80'], 'address' => ['nullable', 'string', 'max:500'], 'sales_channel' => ['nullable', 'string', 'max:255'], 'reason' => ['nullable', 'string', 'max:1000']]);
        if (!$this->service->setting($program, 'enabled', false) || !$this->service->setting($program, 'applications_enabled', false)) return back()->withErrors(['program' => 'This program is not accepting applications currently.']);
        try { $application = $this->service->submitApplication(auth('customer')->user(), $program, $request->all()); } catch (\DomainException $e) { return back()->withErrors(['program' => $e->getMessage()])->withInput(); }
        auth('customer')->user()->notify(new PartnerProgramNotification($program, $application->status));
        return redirect()->route("{$program}.apply")->with('success', 'Your application was submitted for review.');
    }

    public function dashboard(string $program)
    {
        $profile = request()->attributes->get('partner_profile');
        $profile->loadCount(['commissions', 'payouts']);
        $balance = $this->service->availableBalance($profile);
        return view('partner.dashboard', compact('program', 'profile', 'balance'));
    }

    public function payout(Request $request, string $program)
    {
        $profile = request()->attributes->get('partner_profile');
        $request->validate(['amount' => ['required', 'numeric', 'min:0.001'], 'payout_method' => ['required', 'string', 'max:50'], 'account_details' => ['required', 'string', 'max:1000']]);
        try { $this->service->requestPayout($profile, $request->all()); } catch (\DomainException $e) { return back()->withErrors(['amount' => $e->getMessage()]); }
        return back()->with('success', 'Payout request submitted for review.');
    }
}
