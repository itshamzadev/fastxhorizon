@extends('layouts.admin.app')

@section('title', ucfirst($program).' Program Settings')

@php
    $setting = fn (string $key) => $settings->get($program.'_'.$key, $defaults[$key] ?? null);
    $selectedMethods = $setting('payout_methods');
    $selectedMethods = is_string($selectedMethods) ? (json_decode($selectedMethods, true) ?: []) : ($selectedMethods ?: []);
    $currency = getWebConfig(name: 'currency_symbol') ?: getWebConfig(name: 'currency_code') ?: 'PKR';
@endphp

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div><h2 class="mb-1">{{ ucfirst($program) }} Program</h2><p class="text-muted mb-0">Control public availability, partner earnings, attribution, and payouts.</p></div>
        <span class="badge {{ (int) $setting('enabled') === 1 ? 'badge-success' : 'badge-secondary' }}">{{ (int) $setting('enabled') === 1 ? 'Enabled' : 'Disabled' }}</span>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Pending applications</small><h3 class="mb-0">{{ $summary['pending_applications'] }}</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Approved {{ ucfirst($program) }}s</small><h3 class="mb-0">{{ $summary['approved_profiles'] }}</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Pending payout requests</small><h3 class="mb-0">{{ $summary['pending_payouts'] }}</h3></div></div></div>
    </div>

    <form method="post" action="{{ route('admin.partner-programs.settings.update', $program) }}">
        @csrf
        <div class="row g-4">
            <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h4 class="mb-0">Program status</h4></div><div class="card-body">
                <div class="form-group"><label for="enabled">Enable Program</label><select id="enabled" name="enabled" class="form-control"><option value="1" @selected((int) old('enabled', $setting('enabled')) === 1)>Enabled</option><option value="0" @selected((int) old('enabled', $setting('enabled')) === 0)>Disabled</option></select><small class="text-muted">Disabled programs show a branded coming-soon state publicly.</small></div>
                <div class="form-group mb-0"><label for="applications_enabled">Accept New Applications</label><select id="applications_enabled" name="applications_enabled" class="form-control"><option value="1" @selected((int) old('applications_enabled', $setting('applications_enabled')) === 1)>Open</option><option value="0" @selected((int) old('applications_enabled', $setting('applications_enabled')) === 0)>Closed</option></select></div>
            </div></div></div>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h4 class="mb-0">Commission</h4></div><div class="card-body">
                <div class="form-group"><label for="commission_type">Commission Type</label><select id="commission_type" name="commission_type" class="form-control"><option value="percentage" @selected(old('commission_type', $setting('commission_type')) === 'percentage')>Percentage</option><option value="fixed" @selected(old('commission_type', $setting('commission_type')) === 'fixed')>Fixed amount</option></select></div>
                <div class="form-group mb-0"><label for="commission_amount">Commission Rate / Amount</label><input id="commission_amount" name="commission_amount" class="form-control" type="number" step="0.001" min="0" max="100" value="{{ old('commission_amount', $setting('commission_amount')) }}" required><small class="text-muted">Percentage values must be between 0 and 100. Existing commission snapshots are not changed.</small></div>
            </div></div></div>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h4 class="mb-0">Attribution</h4></div><div class="card-body"><label for="attribution_days">Attribution Window in Days</label><input id="attribution_days" name="attribution_days" class="form-control" type="number" min="1" max="365" value="{{ old('attribution_days', $setting('attribution_days')) }}" required><small class="text-muted">Maximum supported window: 365 days.</small></div></div></div>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h4 class="mb-0">Payout</h4></div><div class="card-body"><label for="minimum_payout">Minimum Payout ({{ $currency }})</label><input id="minimum_payout" name="minimum_payout" class="form-control mb-3" type="number" step="0.001" min="0" value="{{ old('minimum_payout', $setting('minimum_payout')) }}" required><label>Supported Payout Methods</label><div class="d-flex flex-wrap gap-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="payout_methods[]" value="bank_transfer" @checked(in_array('bank_transfer', old('payout_methods', $selectedMethods), true))> Bank Transfer</label><label class="form-check"><input class="form-check-input" type="checkbox" name="payout_methods[]" value="mobile_wallet" @checked(in_array('mobile_wallet', old('payout_methods', $selectedMethods), true))> Mobile Wallet</label></div><small class="text-muted d-block mt-2">Select at least one method for an enabled program.</small></div></div></div>
        </div>
        <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary px-4" type="submit">Save {{ ucfirst($program) }} Settings</button></div>
    </form>
</div>
@endsection
