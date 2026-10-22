@extends('layouts.fast-auth')

@section('title', ($program === 'reseller' ? 'Become a FastXHorizon Reseller' : 'Join the FastXHorizon Affiliate Program'))
@section('auth_title', $program === 'reseller' ? 'Become a FastXHorizon Reseller' : 'Join the FastXHorizon Affiliate Program')
@section('auth_subtitle', 'Create your account and submit your '.($program === 'reseller' ? 'reseller' : 'affiliate').' application.')
@section('auth_portal', $program)

@section('auth_content')
    <div class="fx-auth-steps" aria-label="Application progress">
        <span class="is-active">1. Account</span><span>2. Application</span><span>3. Review</span>
    </div>

    @if($errors->any())
        <div class="fx-auth-alert error" role="alert">
            {{ $errors->first() }}
            @if(session('existing_account_portal'))
                <a class="d-block mt-2" href="{{ session('existing_account_portal') }}">Sign in to continue your application</a>
            @endif
        </div>
    @endif

    <form method="post" action="{{ route($program.'.register.submit') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6"><label for="partner-first-name">First name</label><input id="partner-first-name" class="form-control" name="f_name" value="{{ old('f_name') }}" required autocomplete="given-name"></div>
            <div class="col-md-6"><label for="partner-last-name">Last name</label><input id="partner-last-name" class="form-control" name="l_name" value="{{ old('l_name') }}" autocomplete="family-name"></div>
            <div class="col-md-6"><label for="partner-email">Email</label><input id="partner-email" class="form-control" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
            <div class="col-md-6"><label for="partner-phone">Mobile</label><input id="partner-phone" class="form-control" name="phone" value="{{ old('phone') }}" required autocomplete="tel"></div>
            <div class="col-md-6"><label for="partner-password">Password</label><input id="partner-password" class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
            <div class="col-md-6"><label for="partner-confirm-password">Confirm password</label><input id="partner-confirm-password" class="form-control" type="password" name="con_password" required minlength="8" autocomplete="new-password"></div>
        </div>
        <button class="fx-auth-primary mt-4" type="submit">Create account and continue</button>
    </form>

    <div class="fx-auth-links mt-3">Already have a FastXHorizon account? <a href="{{ route($program.'.login') }}">Sign in to continue</a>.</div>
@endsection
