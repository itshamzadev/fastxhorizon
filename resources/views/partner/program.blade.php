@extends('theme-views.layouts.app')

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
                @elseif($application && $application->status === 'pending')
                    <div class="alert alert-info">Your {{ $program }} application is pending review.</div>
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
        @if(auth('customer')->check() && $applicationsOpen && !$profile && (!$application || $application->status !== 'pending'))
            <div id="application" class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
                <h2 class="h4 mb-1">Apply as a {{ ucfirst($program) }}</h2><p class="text-muted mb-4">Tell us how you plan to promote FastXHorizon.</p>
                @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <form method="post" action="{{ route($program.'.apply.submit') }}">@csrf
                    <div class="row g-3"><div class="col-md-6"><label class="form-label">Display name</label><input class="form-control" name="display_name" value="{{ old('display_name', auth('customer')->user()->name) }}" required></div><div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', auth('customer')->user()->phone) }}"></div><div class="col-md-6"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city') }}"></div><div class="col-md-6"><label class="form-label">Province</label><input class="form-control" name="province" value="{{ old('province') }}"></div><div class="col-12"><label class="form-label">Sales channel</label><input class="form-control" name="sales_channel" value="{{ old('sales_channel') }}" placeholder="Website, social page, community, or other channel"></div><div class="col-12"><label class="form-label">Why would you like to join?</label><textarea class="form-control" name="reason" rows="4">{{ old('reason') }}</textarea></div></div><button class="btn btn-primary mt-4" type="submit">Submit application</button>
                </form>
            </div>
        @endif
    </div>
</section>
@endsection
