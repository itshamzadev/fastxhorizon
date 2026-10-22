<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PartnerProgramService;
use App\Notifications\PartnerProgramNotification;
use App\Models\PartnerApplicationDocument;
use App\Support\PartnerProgramPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PartnerProgramController extends Controller
{
    public function __construct(private readonly PartnerProgramService $service) {}

    public function apply(string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        return view('partner.program', ['program' => $program, 'profile' => PartnerProgramPresentation::profile($program), 'application' => PartnerProgramPresentation::application($program), 'enabled' => PartnerProgramPresentation::enabled($program), 'applicationsOpen' => PartnerProgramPresentation::applicationsOpen($program)]);
    }

    public function submit(Request $request, string $program)
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
        $registered = $request->boolean('company_registered');
        $channels = $program === 'reseller'
            ? ['own_website', 'physical_store', 'facebook', 'instagram', 'tiktok', 'daraz', 'other_marketplace', 'whatsapp', 'other']
            : ['youtube', 'tiktok', 'instagram', 'facebook', 'x', 'website', 'whatsapp', 'telegram', 'email', 'other'];
        $rules = [
            'display_name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:100'], 'city' => ['required', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'], 'address' => ['required', 'string', 'max:500'],
            'national_id' => ['nullable', 'string', 'max:100'], 'company_registered' => ['required', 'boolean'],
            'terms_accepted' => ['accepted'], 'company_name' => ['nullable', 'string', 'max:180'],
            'registration_number' => ['nullable', 'string', 'max:120'], 'registration_authority' => ['nullable', 'string', 'max:150'],
            'registration_country' => ['nullable', 'string', 'max:100'], 'registration_date' => ['nullable', 'date'],
            'tax_number' => ['nullable', 'string', 'max:120'], 'business_license_type' => ['nullable', 'string', 'max:120'],
            'business_license_name' => ['nullable', 'string', 'max:150'], 'business_license_number' => ['nullable', 'string', 'max:120'],
            'business_license_authority' => ['nullable', 'string', 'max:150'], 'business_license_expiry' => ['nullable', 'date'],
            'primary_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
        if ($program === 'reseller') {
            $rules += ['business_type' => ['required', 'in:individual,sole_proprietorship,partnership,private_limited,single_member,public_limited,other'], 'store_name' => ['required', 'string', 'max:180'], 'monthly_orders' => ['required', 'in:starting,1_50,51_200,201_500,501_1000,1000_plus'], 'promotion_channels' => ['required', 'array', 'min:1'], 'promotion_channels.*' => ['string', 'in:' . implode(',', $channels)], 'primary_channel' => ['required', 'string', 'max:80'], 'store_website' => ['nullable', 'url', 'max:500'], 'marketplace_url' => ['nullable', 'url', 'max:500'], 'business_description' => ['required', 'string', 'max:2000']];
            if ($registered) { $rules['company_name'] = ['required', 'string', 'max:180']; $rules['registration_number'] = ['required', 'string', 'max:120']; $rules['registration_authority'] = ['required', 'string', 'max:150']; $rules['registration_country'] = ['required', 'string', 'max:100']; $rules['registration_date'] = ['required', 'date']; $rules['primary_document'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']; }
        } else {
            $rules += ['affiliate_type' => ['required', 'string', 'max:80'], 'promotion_channels' => ['required', 'array', 'min:1'], 'promotion_channels.*' => ['string', 'in:' . implode(',', $channels)], 'audience_size' => ['nullable', 'string', 'max:50'], 'audience_region' => ['nullable', 'string', 'max:150'], 'content_niche' => ['nullable', 'string', 'max:100'], 'promotion_plan' => ['required', 'string', 'max:2000'], 'website_url' => ['nullable', 'url', 'max:500'], 'youtube_url' => ['nullable', 'url', 'max:500'], 'tiktok_url' => ['nullable', 'url', 'max:500'], 'instagram_url' => ['nullable', 'url', 'max:500'], 'facebook_url' => ['nullable', 'url', 'max:500'], 'x_url' => ['nullable', 'url', 'max:500']];
            if ($registered) { $rules['company_name'] = ['required', 'string', 'max:180']; $rules['registration_number'] = ['required', 'string', 'max:120']; $rules['registration_authority'] = ['required', 'string', 'max:150']; }
        }
        $validated = $request->validate($rules);
        if (!$this->service->setting($program, 'enabled', false) || !$this->service->setting($program, 'applications_enabled', false)) return back()->withErrors(['program' => 'This program is not accepting applications currently.']);
        $validated['business_data'] = collect($validated)->only(['company_name', 'business_type', 'company_registered', 'registration_number', 'registration_authority', 'registration_country', 'registration_date', 'tax_number', 'business_license_type', 'business_license_name', 'business_license_number', 'business_license_authority', 'business_license_expiry', 'affiliate_type'])->all();
        $validated['sales_data'] = collect($validated)->only(['store_name', 'brand_name', 'monthly_orders', 'promotion_channels', 'primary_channel', 'store_website', 'marketplace_url', 'website_url', 'youtube_url', 'tiktok_url', 'instagram_url', 'facebook_url', 'x_url', 'audience_size', 'audience_region', 'content_niche', 'promotion_plan', 'business_description'])->all();
        try { $application = $this->service->submitApplication(auth('customer')->user(), $program, $validated); } catch (\DomainException $e) { return back()->withErrors(['program' => $e->getMessage()])->withInput(); }
        foreach (array_filter([$request->file('primary_document'), ...($request->file('supporting_documents') ?: [])]) as $file) {
            $path = $file->storeAs('partner-applications/' . $program . '/' . $application->id, (string) Str::uuid() . '.' . strtolower($file->extension()), 'local');
            PartnerApplicationDocument::create(['uuid' => (string) Str::uuid(), 'partner_application_id' => $application->id, 'document_type' => $file === $request->file('primary_document') ? 'registration_certificate' : 'supporting_document', 'disk' => 'local', 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
        }
        auth('customer')->user()->notify(new PartnerProgramNotification($program, 'submitted', '', $application->reference));
        return redirect()->route("{$program}.apply")->with('success', 'Your application was submitted for review.');
    }

    public function dashboard(string $program)
    {
        $profile = request()->attributes->get('partner_profile');
        $profile->loadCount(['commissions', 'payouts']);
        $balance = $this->service->availableBalance($profile);
        $pending = (float) $profile->commissions()->where('status', 'pending')->sum('amount');
        $available = (float) $profile->commissions()->where('status', 'available')->sum('amount');
        $paid = (float) $profile->payouts()->where('status', 'paid')->sum('amount');
        $clicks = $profile->attributions()->whereNull('order_id')->count();
        $recentCommissions = $profile->commissions()->with('order')->latest()->limit(5)->get();
        return view('partner.dashboard', compact('program', 'profile', 'balance', 'pending', 'available', 'paid', 'clicks', 'recentCommissions'));
    }

    public function payout(Request $request, string $program)
    {
        $profile = request()->attributes->get('partner_profile');
        $request->validate(['amount' => ['required', 'numeric', 'min:0.001'], 'payout_method' => ['required', 'string', 'max:50'], 'account_details' => ['required', 'string', 'max:1000']]);
        try { $this->service->requestPayout($profile, $request->all()); } catch (\DomainException $e) { return back()->withErrors(['amount' => $e->getMessage()]); }
        return back()->with('success', 'Payout request submitted for review.');
    }
}
