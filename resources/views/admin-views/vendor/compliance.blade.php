@extends('layouts.admin.app')

@section('title', 'Seller Compliance')

@section('content')
<div class="content container-fluid">
    <div class="mb-4"><h2 class="h1 mb-1">Seller Compliance</h2><p class="text-muted mb-0">Verified records accumulate points. Reversal is auditable; reaching the configured suspension threshold suspends the seller account.</p></div>
    <div class="card mb-4"><div class="card-body"><form method="post" action="{{ route('admin.seller-compliance.store') }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Seller</label><select name="seller_id" required class="form-select"><option value="">Select seller</option>@foreach($sellers as $seller)<option value="{{ $seller->id }}">{{ trim($seller->f_name.' '.$seller->l_name) }} — {{ $seller->email }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Points</label><select name="points" class="form-select"><option>1</option><option>3</option><option>5</option><option>7</option></select></div>
        <div class="col-md-3"><label class="form-label">Source</label><select name="source" required class="form-select"><option value="manual">Manual</option><option value="refund">Refund</option><option value="cancellation">Cancellation</option><option value="privacy">Privacy</option><option value="prohibited_listing">Prohibited listing</option><option value="customer_complaint">Customer complaint</option></select></div>
        <div class="col-md-4"><label class="form-label">Reason</label><input name="reason" required maxlength="500" class="form-control"></div>
        <div class="col-12"><textarea name="evidence" rows="2" placeholder="Evidence or case reference" class="form-control"></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Record Verified Compliance Point</button></div>
    </div></form></div></div>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Seller</th><th>Points</th><th>Source</th><th>Reason</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
    @forelse($records as $record)<tr><td>{{ trim(($record->seller?->f_name ?? '').' '.($record->seller?->l_name ?? '')) ?: '#'.$record->seller_id }}</td><td>{{ $record->points }}</td><td>{{ str_replace('_', ' ', ucfirst($record->source)) }}</td><td>{{ $record->reason }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->created_at?->timezone('Asia/Karachi')->format('d M Y, g:i A') }} PKT</td><td>@if(!$record->reversed_at)<details><summary class="btn btn-sm btn-outline-danger">Reverse</summary><form method="post" action="{{ route('admin.seller-compliance.reverse', $record->id) }}" class="mt-2">@csrf<textarea name="reversal_reason" required class="form-control mb-2" placeholder="Reason"></textarea><button class="btn btn-sm btn-danger">Confirm</button></form></details>@else Reversed @endif</td></tr>@empty<tr><td colspan="7" class="text-center py-5">No compliance records.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $records->links() }}</div></div>
</div>
@endsection
