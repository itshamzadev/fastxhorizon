@extends('layouts.admin.app')
@php use Illuminate\Support\Str; @endphp
@section('title', 'Email Preview')
@section('content')
<div class="content container-fluid"><div class="d-flex justify-content-between mb-3"><h2 class="h1 mb-0">{{ Str::headline($template->template_name) }}</h2><a class="btn btn-outline-secondary" href="{{ route('admin.mail.templates') }}">Back</a></div><div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span>Rendered sample preview</span><form method="POST" action="{{ route('admin.mail.templates.test', $template->id) }}" class="d-flex gap-2">@csrf<input class="form-control" type="email" name="email" placeholder="test@example.com" required><button class="btn btn-primary">Send Test</button></form></div><div class="card-body bg-light"><div class="mx-auto bg-white p-4" style="max-width:680px">{!! $html !!}</div></div></div></div>
@endsection
