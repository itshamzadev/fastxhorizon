@extends('theme-views.layouts.app')

@section('title', 'FastXHorizon '.ucfirst($program).' Dashboard')

@section('content')
<section class="py-5 bg-section-secondary">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><span class="text-primary fw-semibold">FastXHorizon Partner Network</span><h1 class="h2 mb-1">{{ ucfirst($program) }} Dashboard</h1><p class="text-muted mb-0">Manage your link, performance, and payout requests.</p></div>
            <span class="badge rounded-pill {{ $profile->status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($profile->status) }}</span>
        </div>
        <div class="row g-3 mb-4">
            @foreach([
                ['Partner code', $profile->code, 'text-primary'],
                [$program === 'affiliate' ? 'Clicks' : 'Attributed orders', $clicks, ''],
                ['Pending commission', number_format($pending, 3), ''],
                ['Available commission', number_format($available, 3), 'text-success'],
                ['Paid commission', number_format($paid, 3), ''],
                ['Payout requests', $profile->payouts_count, '']
            ] as [$label, $value, $class])
                <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm rounded-4 p-3 h-100"><small class="text-muted d-block mb-1">{{ $label }}</small><strong class="fs-5 {{ $class }}">{{ $value }}</strong></div></div>
            @endforeach
        </div>
        <div class="row g-4">
            <div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4 p-4 h-100"><h2 class="h5">Your {{ $program }} link</h2><p class="text-muted">Share this link with your audience. Attribution is handled by the FastXHorizon backend.</p><div class="input-group"><input id="partner-link" class="form-control" value="{{ url('/?'.($program === 'reseller' ? 'reseller' : 'ref').'='.$profile->code) }}" readonly><button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('partner-link').value).then(()=>this.textContent='Copied')">Copy link</button></div><small class="text-success d-block mt-2">Commission rates are configured by FastXHorizon and remain subject to qualifying order status.</small></div></div>
            <div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4 p-4"><h2 class="h5">Request a payout</h2><p class="text-muted small">Available earnings only. Account details are stored privately.</p>@if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif<form method="post" action="{{ route($program.'.payout') }}">@csrf<input class="form-control mb-2" name="amount" type="number" step="0.001" min="0.001" placeholder="Amount" required><input class="form-control mb-2" name="payout_method" placeholder="Bank transfer or mobile wallet" required><textarea class="form-control mb-2" name="account_details" placeholder="Payout account details" required></textarea><button class="btn btn-primary w-100" type="submit">Submit request</button></form></div></div>
        </div>
        <div class="card border-0 shadow-sm rounded-4 p-4 mt-4"><h2 class="h5 mb-3">Recent {{ $program === 'affiliate' ? 'conversions' : 'attributed sales' }}</h2>@forelse($recentCommissions as $commission)<div class="d-flex flex-wrap justify-content-between gap-2 border-bottom py-2"><span>{{ $commission->order?->id ? 'Order #'.$commission->order->id : 'Commission record' }}</span><span class="text-muted">{{ ucfirst($commission->status) }} · {{ number_format((float) $commission->amount, 3) }}</span></div>@empty<p class="text-muted mb-0">No attributed activity yet. Your qualifying activity will appear here.</p>@endforelse</div>
    </div>
</section>
@endsection
