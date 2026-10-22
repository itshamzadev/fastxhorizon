@extends('layouts.fast-auth')

@section('title', 'Seller Login | FastXHorizon')
@section('auth_title', 'Seller Login')
@section('auth_subtitle', 'Access your FastXHorizon Seller account.')
@section('auth_portal', 'seller')

@section('auth_content')
    @if($errors->any())
        <div class="fx-auth-alert error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('vendor.auth.login') }}" method="post" id="vendor-login-form">
        @csrf
        <div class="mb-3">
            <label for="signingVendorEmail">Email</label>
            <input type="email" class="form-control" name="email" id="signingVendorEmail" value="{{ old('email') }}" required autocomplete="email">
        </div>
        <div class="mb-3">
            <label for="signingVendorPassword"><span class="d-flex justify-content-between align-items-center">Password <a href="{{ route('vendor.auth.forgot-password.index') }}">Forgot password?</a></span></label>
            <input type="password" class="form-control" name="password" id="signingVendorPassword" required autocomplete="current-password">
        </div>
        <div class="fx-auth-remember">
            <label for="seller-remember"><input id="seller-remember" type="checkbox" name="remember" value="1"> Remember me</label>
            <a href="{{ route('vendor.auth.registration.index') }}">Become a Seller</a>
        </div>
        <button type="submit" id="vendorLoginBtn" class="fx-auth-primary">Login as Seller</button>
    </form>
@endsection
