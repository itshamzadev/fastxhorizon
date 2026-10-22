@extends('layouts.admin.app')
@section('title', ucfirst($program).' Payouts')
@section('content')
<div class="content container-fluid"><h2>{{ ucfirst($program) }} Payout Requests</h2><div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>Partner</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>@forelse($payouts as $payout)<tr><td>{{ $payout->profile->code ?? '—' }}</td><td>{{ $payout->amount }}</td><td>{{ $payout->payout_method }}</td><td>{{ ucfirst($payout->status) }}</td></tr>@empty<tr><td colspan="4">No payout requests found.</td></tr>@endforelse</tbody></table></div>{{ $payouts->links() }}</div></div>
@endsection
