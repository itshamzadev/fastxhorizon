@extends('layouts.front-end.app')

@section('title', 'Withdraw Funds | ' . getCompanyIdentity('brand') . ' Wallet')

@section('content')
    <div class="container py-3 py-md-4 user-profile-container px-5px">
        <div class="row">
            @include('web-views.partials._profile-aside')
            <section class="col-lg-9 __customer-profile px-0">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Withdraw Funds</h4>
                                <p class="text-muted mb-0">Request a secure withdrawal from your eligible {{ getCompanyIdentity('brand') }} Wallet balance.</p>
                            </div>
                            <a href="{{ route('wallet') }}" class="btn btn-outline-primary">Back to Wallet</a>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Wallet Balance</small><strong class="fs-18">{{ webCurrencyConverter(amount: $walletBalance) }}</strong></div></div>
                            <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Withdrawable Balance</small><strong class="fs-18 text-primary">{{ webCurrencyConverter(amount: $withdrawableBalance) }}</strong></div></div>
                            <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Pending Held</small><strong class="fs-18">{{ webCurrencyConverter(amount: $held) }}</strong></div></div>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif

                        @if ($methods->isEmpty())
                            <div class="alert alert-info">Wallet withdrawals are temporarily unavailable because no withdrawal method has been enabled.</div>
                        @elseif($withdrawableBalance <= 0)
                            <div class="alert alert-info">There is no withdrawable balance available at the moment.</div>
                        @else
                            <form method="POST" action="{{ route('wallet-withdrawal.request') }}" novalidate>
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                                        <input type="number" name="amount" min="0.01" max="{{ $withdrawableBalance }}" step="0.01" value="{{ old('amount') }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Withdrawal Method <span class="text-danger">*</span></label>
                                        <select name="method" id="wallet-withdrawal-method" class="form-control" required>
                                            <option value="">Select a method</option>
                                            @foreach($methods as $method)<option value="{{ $method->id }}" {{ old('method') == $method->id ? 'selected' : '' }}>{{ $method->method_name }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>

                                @foreach($methods as $method)
                                    <div class="withdrawal-method-fields border rounded p-3 mt-3" data-method-id="{{ $method->id }}" style="display:none">
                                        <h6 class="mb-3">{{ $method->method_name }} details</h6>
                                        <div class="row g-3">
                                            @foreach(($method->method_fields ?? []) as $field)
                                                @php($key = $field['input_name'] ?? 'detail')
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ ucwords(str_replace('_', ' ', $key)) }} @if(!empty($field['is_required']))<span class="text-danger">*</span>@endif</label>
                                                    <input type="{{ ($field['input_type'] ?? 'string') === 'number' ? 'text' : (($field['input_type'] ?? 'string') === 'email' ? 'email' : 'text') }}" name="details[{{ $key }}]" value="{{ old('details.'.$key) }}" placeholder="{{ $field['placeholder'] ?? '' }}" class="form-control" @if(!empty($field['is_required'])) required @endif>
                                                    @error('details.'.$key)<small class="text-danger">{{ $message }}</small>@enderror
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                                <div class="mt-3">
                                    <label class="form-label">Optional note</label>
                                    <textarea name="customer_note" rows="3" class="form-control" maxlength="1000">{{ old('customer_note') }}</textarea>
                                </div>
                                <div class="alert alert-light border mt-3 mb-3">Your requested amount is held while this request is pending. It cannot be spent again. Promotional or non-withdrawable credits are not eligible.</div>
                                <button class="btn btn-primary px-4" type="submit">Submit Withdrawal Request</button>
                            </form>
                        @endif

                        <hr class="my-4">
                        <h5 class="mb-3">Withdrawal History</h5>
                        @forelse($requests as $withdrawal)
                            <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div><strong>{{ $withdrawal->reference }}</strong><div class="small text-muted">{{ $withdrawal->created_at->format('d M Y, h:i A') }} · {{ $withdrawal->method }}</div></div>
                                <div class="text-md-right"><strong>{{ webCurrencyConverter(amount: $withdrawal->amount) }}</strong><div class="small text-capitalize">{{ str_replace('_', ' ', $withdrawal->status) }}</div></div>
                                @if($withdrawal->status === 'pending')
                                    <form method="POST" action="{{ route('wallet-withdrawal.cancel', $withdrawal->id) }}">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button></form>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted border rounded p-4 text-center">No withdrawal requests yet.</div>
                        @endforelse
                        {{ $requests->links() }}
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('script')
<script>
    (function () {
        const select = document.getElementById('wallet-withdrawal-method');
        const panels = document.querySelectorAll('.withdrawal-method-fields');
        function showFields() {
            panels.forEach(panel => {
                const active = select && panel.dataset.methodId === select.value;
                panel.style.display = active ? 'block' : 'none';
                panel.querySelectorAll('input').forEach(input => input.disabled = !active);
            });
        }
        if (select) { select.addEventListener('change', showFields); showFields(); }
    })();
</script>
@endpush
