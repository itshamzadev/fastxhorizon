@extends('layouts.admin.app')
@section('title', ucfirst($program))
@section('content')
<div class="content container-fluid"><h2>{{ ucfirst($program) }} Profiles</h2><div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>User</th><th>Code</th><th>Status</th><th>Approved</th></tr></thead><tbody>@forelse($profiles as $profile)<tr><td>{{ $profile->user->email ?? '—' }}</td><td>{{ $profile->code }}</td><td>{{ ucfirst($profile->status) }}</td><td>{{ optional($profile->approved_at)->toDateTimeString() }}</td></tr>@empty<tr><td colspan="4">No approved profiles found.</td></tr>@endforelse</tbody></table></div>{{ $profiles->links() }}</div></div>
@endsection
