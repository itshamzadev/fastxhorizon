@extends('layouts.admin.app')
@php use Illuminate\Support\Str; @endphp
@section('title', 'Mail Delivery Logs')
@section('content')
<div class="alert alert-light border">Only failed messages can be retried. SMTP acceptance is logged as <strong>Sent</strong>; it does not claim inbox delivery.</div>
<div class="content container-fluid"><h2 class="h1 mb-3">Delivery Logs</h2><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Recipient</th><th>Type</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Queued</th><th>Sent/Failed</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->recipient }}</td><td>{{ Str::headline($log->type) }}</td><td>{{ Str::limit($log->subject, 55) }}</td><td><span class="badge bg-{{ $log->status === 'sent' ? 'success' : ($log->status === 'failed' ? 'danger' : 'warning') }}">{{ Str::headline($log->status) }}</span></td><td>{{ $log->attempts }}</td><td>{{ $log->queued_at }}</td><td>{{ $log->sent_at ?: $log->failed_at }}</td></tr>@empty<tr><td colspan="7" class="text-center py-4">No mail delivery records yet.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $logs->links() }}</div></div></div>
@endsection
