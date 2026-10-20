@extends('layouts.admin.app')

@section('title', 'Company Leadership')

@section('content')
<div class="content container-fluid">
    <div class="mb-4"><h2 class="h1 mb-1">Company Leadership</h2><p class="text-muted mb-0">Profiles are hidden by default. Publish only verified people and approved biographies.</p></div>
    <div class="card mb-4"><div class="card-body">
        <form method="post" action="{{ route('admin.pages-and-media.leadership.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Name</label><input name="name" required class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Role</label><input name="role" required class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Order</label><input name="display_order" type="number" min="0" value="0" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Photo (optional)</label><input name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
                <div class="col-12"><label class="form-label">Bio (optional)</label><textarea name="bio" rows="2" class="form-control"></textarea></div>
                <div class="col-md-6"><label class="form-label">Website / Portfolio URL (optional)</label><input name="portfolio_url" type="url" placeholder="https://example.com" class="form-control"></div>
                <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1"><span class="form-check-label">Show publicly after verification</span></label></div>
                <div class="col-12"><button class="btn btn-primary">Add Leadership Profile</button></div>
            </div>
        </form>
    </div></div>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Role</th><th>Order</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($profiles as $profile)
        <tr><td>{{ $profile->name }}</td><td>{{ $profile->role }}</td><td>{{ $profile->display_order }}</td><td>{{ $profile->is_active ? 'Published' : 'Inactive' }}</td><td>
            <details><summary class="btn btn-sm btn-outline-primary">Edit</summary><form class="mt-3" method="post" action="{{ route('admin.pages-and-media.leadership.update', $profile->id) }}" enctype="multipart/form-data">@csrf
                <input name="name" required class="form-control mb-2" value="{{ $profile->name }}"><input name="role" required class="form-control mb-2" value="{{ $profile->role }}"><input name="display_order" type="number" min="0" class="form-control mb-2" value="{{ $profile->display_order }}"><textarea name="bio" rows="2" class="form-control mb-2">{{ $profile->bio }}</textarea><input name="portfolio_url" type="url" placeholder="https://example.com" class="form-control mb-2" value="{{ $profile->portfolio_url }}"><input name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control mb-2"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $profile->is_active ? 'checked' : '' }}><span class="form-check-label">Published</span></label><button class="btn btn-primary btn-sm">Save</button>
            </form></details>
            <form method="post" action="{{ route('admin.pages-and-media.leadership.destroy', $profile->id) }}" class="d-inline">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this profile?')">Remove</button></form>
        </td></tr>
    @empty <tr><td colspan="5" class="text-center py-5">No leadership profiles are published. This is intentional until verified information is supplied.</td></tr> @endforelse
    </tbody></table></div></div>
</div>
@endsection
