@extends('layouts.admin.app')

@section('title', 'Customer Wallet Withdrawals')

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 mb-1">Customer Wallet Withdrawals</h2>
                <p class="text-muted mb-0">Review held wallet funds and record payout references.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Request</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method / Destination</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $withdrawal)
                        @php
                            $details = is_array($withdrawal->payment_details)
                                ? $withdrawal->payment_details
                                : (json_decode($withdrawal->payment_details ?? '[]', true) ?: []);
                            $maskedDetails = collect($details)->map(function ($value, $key) {
                                $value = (string) $value;
                                if (in_array($key, ['account_number_iban', 'mobile_number', 'swift_bic'], true) && strlen($value) > 4) {
                                    return str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -4);
                                }
                                return $value;
                            });
                            $badge = match($withdrawal->status) {
                                'paid' => 'success',
                                'rejected', 'cancelled' => 'danger',
                                'approved' => 'primary',
                                'under_review' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $withdrawal->reference }}</div>
                                @if($withdrawal->customer_note)
                                    <small class="text-muted">{{ $withdrawal->customer_note }}</small>
                                @endif
                            </td>
                            <td>
                                <div>{{ $withdrawal->customer?->f_name }} {{ $withdrawal->customer?->l_name }}</div>
                                <small class="text-muted">{{ $withdrawal->customer?->email }}</small>
                            </td>
                            <td class="fw-semibold">{{ webCurrencyConverter(amount: $withdrawal->amount) }}</td>
                            <td>
                                <div class="fw-semibold">{{ $withdrawal->method }}</div>
                                @foreach($maskedDetails as $key => $value)
                                    <small class="d-block text-muted">{{ str_replace('_', ' ', ucfirst($key)) }}: {{ $value }}</small>
                                @endforeach
                            </td>
                            <td><span class="badge bg-{{ $badge }} text-capitalize">{{ str_replace('_', ' ', $withdrawal->status) }}</span></td>
                            <td>{{ $withdrawal->created_at?->format('d M Y, h:i A') }}</td>
                            <td class="text-end" style="min-width: 240px">
                                @if(!in_array($withdrawal->status, ['paid', 'rejected', 'cancelled'], true))
                                    <form method="post" action="{{ route('admin.customer.wallet-withdrawals.status', $withdrawal->id) }}">
                                        @csrf
                                        <div class="d-flex flex-column gap-2">
                                            <select name="status" class="form-control form-control-sm" required>
                                                @if($withdrawal->status === 'pending')
                                                    <option value="under_review">Under Review</option>
                                                @endif
                                                <option value="approved" @selected($withdrawal->status === 'approved')>Approve</option>
                                                <option value="rejected">Reject</option>
                                                @if($withdrawal->status === 'approved')
                                                    <option value="paid">Mark Paid</option>
                                                @endif
                                            </select>
                                            @if($withdrawal->status === 'approved')
                                                <input type="text" name="payment_reference" class="form-control form-control-sm" placeholder="Payment reference (required for paid)">
                                            @endif
                                            <textarea name="admin_note" class="form-control form-control-sm" rows="2" placeholder="Review note / rejection reason">{{ $withdrawal->admin_note }}</textarea>
                                            <button class="btn btn-primary btn-sm" type="submit">Save status</button>
                                        </div>
                                    </form>
                                @else
                                    <small class="text-muted">No further action</small>
                                    @if($withdrawal->payment_reference)
                                        <small class="d-block text-muted">Ref: {{ $withdrawal->payment_reference }}</small>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No customer wallet withdrawal requests yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($requests->hasPages())
                <div class="p-3">{{ $requests->links() }}</div>
            @endif
        </div>
    </div>
@endsection
