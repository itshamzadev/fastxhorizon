@extends('layouts.admin.app')
@section('title', 'Newsletter Subscribers')
@section('content')
<div class="content container-fluid"><div class="d-flex justify-content-between mb-3"><h2 class="h1 mb-0">Newsletter Subscribers</h2><span class="badge bg-secondary align-self-center">Unsubscribed: {{ $unsubscribed }}</span></div><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Email</th><th>Subscribed At</th></tr></thead><tbody>@forelse($subscribers as $subscriber)<tr><td>{{ $subscriber->email }}</td><td>{{ $subscriber->created_at }}</td></tr>@empty<tr><td colspan="2" class="text-center py-4">No subscribers found.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $subscribers->links() }}</div></div></div>
@endsection
