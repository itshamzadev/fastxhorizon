@extends('layouts.fast-auth')
@section('title', ucfirst($program).' Login | FastXHorizon')
@section('auth_title', ucfirst($program).' Login')
@section('auth_subtitle', 'Access your FastXHorizon '.($program === 'reseller' ? 'Reseller' : 'Affiliate').' account.')
@section('auth_portal', $program)
@section('auth_content')
@if($errors->any())<div class="fx-auth-alert error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('customer.auth.login') }}">@csrf
    <input type="hidden" name="login_type" value="manual-login"><input type="hidden" name="partner_portal" value="{{ $program }}"><input type="hidden" name="keep_customer_login_redirect_url" value="{{ route($program.'.apply') }}">
    <div class="mb-3"><label for="partner-identity">Email or phone</label><input id="partner-identity" class="form-control" name="user_identity" value="{{ old('user_identity') }}" required autofocus></div>
    <div class="mb-3"><label for="partner-password">Password</label><input id="partner-password" class="form-control" type="password" name="password" required></div>
    <div class="fx-auth-remember"><label for="partner-remember"><input id="partner-remember" type="checkbox" name="remember" value="1"> Remember me</label><a href="{{ route('customer.auth.recover-password') }}">Forgot password?</a></div>
    <button class="fx-auth-primary" type="submit">Login as {{ ucfirst($program) }}</button>
</form>
<hr class="fx-auth-divider"><div class="fx-auth-links">New to this program? <a href="{{ route($program.'.register') }}">{{ $program === 'reseller' ? 'Become a Reseller' : 'Join Affiliate Program' }}</a></div>
@endsection
