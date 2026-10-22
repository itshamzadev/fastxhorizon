@extends('layouts.front-end.app')

@section('title', 'Seller Sign In | FastXHorizon')

@section('content')
    <div class="container py-4 py-lg-5 my-4 text-align-direction">
        <div class="row justify-content-center">
            <div class="col-md-9 login-card">
                <div class="d-flex justify-content-center align-items-center flex-column">
                    <img src="{{ theme_asset(path: 'public/assets/front-end/img/icons/user-vector.svg') }}" alt="" class="w-70px">
                    <h2 class="text-center font-bold text-capitalize fs-20 my-4 fs-18-mobile">Seller Sign In</h2>
                </div>
                @if($errors->any())<div class="alert alert-danger mb-4">{{ $errors->first() }}</div>@endif
                <div class="position-relative">
                    <div class="row justify-content-center align-items-center g-4 or-sign-in-with-row">
                        <div class="col-md-6">
                            <form autocomplete="off" class="customer-centralize-login-form mt-2" action="{{ route('vendor.auth.login') }}" method="post">
                                @csrf
                                <div class="form-group"><label class="form-label font-semibold">Email / Phone <span class="input-required-icon">*</span></label><input class="form-control text-align-direction" type="text" name="email" value="{{ old('email') }}" placeholder="email@address.com or phone" required autocomplete="username" inputmode="text"></div>
                                <div class="form-group"><label class="form-label font-semibold">{{ translate('password') }} <span class="input-required-icon">*</span></label><div class="password-toggle rtl"><input class="form-control text-align-direction" type="password" name="password" placeholder="{{ translate('8+_characters_required') }}" required autocomplete="current-password"><label class="password-toggle-btn"><input class="custom-control-input" type="checkbox"><i class="tio-hidden password-toggle-indicator"></i><span class="sr-only">{{ translate('show_password') }}</span></label></div></div>
                                @php($rememberId = rand(111, 999))
                                <div class="form-group d-flex flex-wrap justify-content-between mb-1"><div class="rtl"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" name="remember" id="seller-remember{{ $rememberId }}"><label class="custom-control-label text-primary" for="seller-remember{{ $rememberId }}">{{ translate('remember_me') }}</label></div></div><a class="font-size-sm text-primary text-underline" href="{{ route('vendor.auth.forgot-password.index') }}">{{ translate('forgot_password') }}?</a></div>
                                <button type="submit" class="btn btn--primary btn-block btn-shadow font-semi-bold">Sign in as Seller</button>
                            </form>
                        </div>
                        <div class="or-sign-in-with"><span>{{ translate('Or Sign in with') }}</span></div>
                        <div class="col-md-6"><div class="text-center text-black-50 fs-14 my-3">New seller? <a class="text-primary text-underline" href="{{ route('vendor.auth.registration.index') }}">Become a Seller</a></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
