@extends('layouts.admin.app')
@section('title', ucfirst($program).' Audit Logs')
@section('content')
<div class="content container-fluid"><div class="mb-3"><h2>{{ ucfirst($program) }} Audit Logs</h2><p class="text-muted">Administrative actions for this partner program.</p></div><div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Partner</th><th>Details</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ optional($event->created_at)->toDateTimeString() }}</td><td>{{ $event->admin->email ?? $event->admin->name ?? 'System' }}</td><td>{{ ucfirst(str_replace('_', ' ', $event->action)) }}</td><td>{{ $event->profile->code ?? '—' }}</td><td><small>{{ IlluminateSupportStr::limit(json_encode($event->metadata ?: [], JSON_UNESCAPED_SLASHES), 120) }}</small></td></tr>@empty<tr><td colspan="5">No audit events found.</td></tr>@endforelse</tbody></table></div>{{ $events->links() }}</div></div>
@endsection
