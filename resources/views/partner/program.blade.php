@extends(theme_root_path() === 'theme_aster' ? 'theme-views.layouts.app' : 'layouts.front-end.app')

@section('title', 'FastXHorizon '.ucfirst($program).' Program')

@section('content')
<section class="py-5 bg-section-secondary">
    <div class="container py-lg-4">
        <div class="row align-items-center g-4 mb-5">
            <div class="col-lg-7">
                <span class="badge rounded-pill bg-primary text-white mb-3">FastXHorizon Partner Network</span>
                <h1 class="display-5 fw-bold mb-3">Build with the FastXHorizon {{ ucfirst($program) }} Program</h1>
                <p class="fs-5 text-muted mb-4">Promote products you believe in, share your unique link, and grow with a marketplace built for customers and partners.</p>
                @if(!$enabled)
                    <div class="alert alert-info">The {{ $program }} program is coming soon. Applications are not currently available.</div>
                @elseif(!$applicationsOpen)
                    <div class="alert alert-warning">Applications are currently closed. Please check back later.</div>
                @elseif($application && in_array($application->status, ['pending', 'submitted', 'under_review', 'needs_information'], true))
                    <div class="alert alert-info">Your {{ $program }} application ({{ $application->reference }}) is {{ str_replace('_', ' ', $application->status) }}.</div>
                @elseif($profile)
                    <a href="{{ route($program.'.dashboard') }}" class="btn btn-primary px-4">Open your {{ ucfirst($program) }} dashboard</a>
                @elseif(auth('customer')->check())
                    <a href="#application" class="btn btn-primary px-4">Apply to join</a>
                @else
                    <a href="{{ route('customer.auth.login') }}?redirect={{ urlencode(route($program.'.apply')) }}" class="btn btn-primary px-4">Log in to apply</a>
                @endif
            </div>
            <div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4 p-4"><h5>Simple, transparent growth</h5><p class="text-muted mb-0">Share. Track. Earn. Commission rates are configured by FastXHorizon and shown after approval.</p></div></div>
        </div>
        <div class="row g-4 mb-5">
            <div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-4 p-4"><h5>How it works</h5><p class="text-muted mb-0">Apply, receive approval, and use your unique partner link to introduce customers to FastXHorizon.</p></div></div>
            <div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-4 p-4"><h5>Who can join</h5><p class="text-muted mb-0">Creators, community builders, online sellers, and professionals with a genuine audience or sales channel.</p></div></div>
            <div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-4 p-4"><h5>Reliable payouts</h5><p class="text-muted mb-0">Approved earnings move through the FastXHorizon payout process.</p></div></div>
        </div>
        @if(auth('customer')->check() && $applicationsOpen && !$profile && (!$application || !in_array($application->status, ['pending', 'submitted', 'under_review', 'needs_information'], true)))
            <div id="application" class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                <h2 class="h4 mb-1">Apply as a {{ ucfirst($program) }}</h2><p class="text-muted mb-4">Tell us how you plan to promote FastXHorizon.</p>
                @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <form method="post" enctype="multipart/form-data" action="{{ route($program.'.apply.submit') }}">@csrf
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="display_name" value="{{ old('display_name', auth('customer')->user()->name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="{{ auth('customer')->user()->email }}" disabled></div>
                        <div class="col-md-6"><label class="form-label">Mobile number</label><input class="form-control" name="phone" value="{{ old('phone', auth('customer')->user()->phone) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Country</label><input class="form-control" name="country" value="{{ old('country', 'Pakistan') }}" required></div>
                        <div class="col-md-4"><label class="form-label">Province/State</label><input class="form-control" name="province" value="{{ old('province') }}"></div>
                        <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city') }}" required></div>
                        <div class="col-md-4"><label class="form-label">National ID (optional)</label><input class="form-control" name="national_id" value="{{ old('national_id') }}"></div>
                        <div class="col-12"><label class="form-label">Full address</label><textarea class="form-control" name="address" rows="2" required>{{ old('address') }}</textarea></div>
                        @if($program === 'reseller')
                            <div class="col-md-6"><label class="form-label">Store / trading name</label><input class="form-control" name="store_name" value="{{ old('store_name') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Business type</label><select class="form-select" name="business_type" required><option value="">Select</option>@foreach(['individual'=>'Individual / Unregistered Business','sole_proprietorship'=>'Sole Proprietorship','partnership'=>'Partnership','private_limited'=>'Private Limited Company','single_member'=>'Single Member Company','public_limited'=>'Public Limited Company','other'=>'Other'] as $key=>$label)<option value="{{ $key }}" @selected(old('business_type')===$key)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Is your business legally registered?</label><select class="form-select" name="company_registered" required><option value="0">No</option><option value="1" @selected(old('company_registered')==='1')>Yes</option></select></div>
                            <div class="col-md-6"><label class="form-label">Legal registered name (if registered)</label><input class="form-control" name="company_name" value="{{ old('company_name') }}"></div>
                            <div class="col-md-4"><label class="form-label">Registration number</label><input class="form-control" name="registration_number" value="{{ old('registration_number') }}"></div><div class="col-md-4"><label class="form-label">Registration authority</label><input class="form-control" name="registration_authority" value="{{ old('registration_authority') }}"></div><div class="col-md-4"><label class="form-label">Registration country</label><input class="form-control" name="registration_country" value="{{ old('registration_country') }}"></div>
                            <div class="col-md-4"><label class="form-label">Registration date</label><input class="form-control" type="date" name="registration_date" value="{{ old('registration_date') }}"></div><div class="col-md-4"><label class="form-label">Tax / NTN number</label><input class="form-control" name="tax_number" value="{{ old('tax_number') }}"></div><div class="col-md-4"><label class="form-label">Business license type</label><input class="form-control" name="business_license_type" value="{{ old('business_license_type') }}"></div>
                            <div class="col-md-6"><label class="form-label">Primary registration certificate (PDF/JPG/PNG, max 5MB)</label><input class="form-control" type="file" name="primary_document"></div>
                            <div class="col-md-6"><label class="form-label">Monthly orders</label><select class="form-select" name="monthly_orders" required>@foreach(['starting'=>'Just starting','1_50'=>'1–50','51_200'=>'51–200','201_500'=>'201–500','501_1000'=>'501–1000','1000_plus'=>'1000+'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Primary sales channel</label><input class="form-control" name="primary_channel" value="{{ old('primary_channel') }}" required></div><div class="col-md-6"><label class="form-label">Store website (optional)</label><input class="form-control" type="url" name="store_website" value="{{ old('store_website') }}"></div>
                            <div class="col-12"><label class="form-label">How do you currently sell? Select at least one</label><div class="d-flex flex-wrap gap-3">@foreach(['own_website'=>'Own Website','physical_store'=>'Physical Store','facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok','daraz'=>'Daraz','whatsapp'=>'WhatsApp','other_marketplace'=>'Other Marketplace'] as $key=>$label)<label><input type="checkbox" name="promotion_channels[]" value="{{ $key }}"> {{ $label }}</label>@endforeach</div></div>
                            <div class="col-12"><label class="form-label">Business description and reason for joining</label><textarea class="form-control" name="business_description" rows="3" required>{{ old('business_description') }}</textarea></div>
                        @else
                            <div class="col-md-6"><label class="form-label">Affiliate type</label><select class="form-select" name="affiliate_type" required><option value="">Select</option>@foreach(['creator'=>'Individual Creator','influencer'=>'Influencer','blogger'=>'Blogger / Publisher','youtuber'=>'YouTuber','social_page'=>'Social Media Page','community'=>'Community Owner','website'=>'Website Owner','agency'=>'Marketing Agency','company'=>'Company','other'=>'Other'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Brand/channel name (optional)</label><input class="form-control" name="brand_name" value="{{ old('brand_name') }}"></div>
                            <div class="col-md-6"><label class="form-label">Applying as a registered company?</label><select class="form-select" name="company_registered" required><option value="0">No</option><option value="1" @selected(old('company_registered')==='1')>Yes</option></select></div><div class="col-md-6"><label class="form-label">Registration certificate (optional)</label><input class="form-control" type="file" name="primary_document"></div>
                            <div class="col-md-4"><label class="form-label">Legal business name (if applicable)</label><input class="form-control" name="company_name" value="{{ old('company_name') }}"></div><div class="col-md-4"><label class="form-label">Registration number</label><input class="form-control" name="registration_number" value="{{ old('registration_number') }}"></div><div class="col-md-4"><label class="form-label">Registration authority</label><input class="form-control" name="registration_authority" value="{{ old('registration_authority') }}"></div>
                            <div class="col-12"><label class="form-label">Promotion channels (select at least one)</label><div class="d-flex flex-wrap gap-3">@foreach(['youtube'=>'YouTube','tiktok'=>'TikTok','instagram'=>'Instagram','facebook'=>'Facebook','x'=>'X / Twitter','website'=>'Website / Blog','whatsapp'=>'WhatsApp','telegram'=>'Telegram','email'=>'Email Newsletter'] as $key=>$label)<label><input type="checkbox" name="promotion_channels[]" value="{{ $key }}"> {{ $label }}</label>@endforeach</div></div>
                            <div class="col-md-4"><label class="form-label">Audience size</label><select class="form-select" name="audience_size"><option value="under_1000">Under 1,000</option><option value="1000_5000">1,000–5,000</option><option value="5001_10000">5,001–10,000</option><option value="10001_50000">10,001–50,000</option><option value="50001_100000">50,001–100,000</option><option value="100000_plus">100,000+</option></select></div><div class="col-md-4"><label class="form-label">Audience country/region</label><input class="form-control" name="audience_region"></div><div class="col-md-4"><label class="form-label">Content niche</label><input class="form-control" name="content_niche"></div>
                            <div class="col-12"><label class="form-label">How do you plan to promote FastXHorizon?</label><textarea class="form-control" name="promotion_plan" rows="3" required>{{ old('promotion_plan') }}</textarea></div>
                        @endif
                        <div class="col-12"><label class="form-label">Additional notes</label><textarea class="form-control" name="reason" rows="3">{{ old('reason') }}</textarea></div>
                        <div class="col-12"><label><input type="checkbox" name="terms_accepted" value="1" required> I confirm the information is accurate and agree to the FastXHorizon {{ ucfirst($program) }} Program Terms.</label></div>
                    </div><button class="btn btn-primary mt-4" type="submit">Submit application</button>
                </form>
            </div>
        @endif
    </div>
</section>
@endsection
