@extends(theme_root_path() === 'theme_aster' ? 'theme-views.layouts.app' : 'layouts.front-end.app')

@section('title', ucfirst($program).' Sign In | FastXHorizon')

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/plugin/intl-tel-input/css/intlTelInput.css') }}">
@endpush

@section('content')
    <div class="container py-4 py-lg-5 my-4 text-align-direction">
        <div class="row justify-content-center">
            <div class="col-md-9 login-card">
                <div class="d-flex justify-content-center align-items-center flex-column">
                    <img src="{{ theme_asset(path: 'public/assets/front-end/img/icons/user-vector.svg') }}" alt="" class="w-70px">
                    <h2 class="text-center font-bold text-capitalize fs-20 my-4 fs-18-mobile">
                        {{ ucfirst($program) }} Sign In
                    </h2>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
                @endif

                <div class="position-relative">
                    <div class="row justify-content-center align-items-center g-4 or-sign-in-with-row">
                        <div class="col-md-6">
                            <form autocomplete="off" class="customer-centralize-login-form mt-2" action="{{ route('customer.auth.login') }}" method="post">
                                @csrf
                                <input type="hidden" name="keep_customer_login_redirect_url" value="{{ route($program.'.apply') }}">
                                <input type="hidden" name="login_type" value="manual-login">
                                <input type="hidden" name="partner_portal" value="{{ $program }}">
                                @include('web-views.customer-views.auth.partials._email')
                                @include('web-views.customer-views.auth.partials._password')
                                @php($rememberId = rand(111, 999))
                                <div class="form-group d-flex flex-wrap justify-content-between mb-1">
                                    <div class="rtl">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="remember" id="partner-remember{{ $rememberId }}">
                                            <label class="custom-control-label text-primary" for="partner-remember{{ $rememberId }}">{{ translate('remember_me') }}</label>
                                        </div>
                                    </div>
                                    <a class="font-size-sm text-primary text-underline" href="{{ route('customer.auth.recover-password') }}">{{ translate('forgot_password') }}?</a>
                                </div>
                                <button class="btn btn--primary btn-block btn-shadow font-semi-bold" type="submit">Sign in as {{ ucfirst($program) }}</button>
                            </form>
                        </div>

                        <div class="or-sign-in-with"><span>{{ translate('Or Sign in with') }}</span></div>

                        <div class="col-md-6">
                            <div class="text-center text-black-50 fs-14 my-3">
                                {{ $program === 'reseller' ? 'New to the Reseller Program?' : 'New to the Affiliate Program?' }}
                                <a class="text-primary text-underline" href="{{ route($program.'.register') }}">{{ $program === 'reseller' ? 'Become a Reseller' : 'Join Now' }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
