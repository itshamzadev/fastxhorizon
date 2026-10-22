@extends(theme_root_path() === 'theme_aster' ? 'theme-views.layouts.app' : 'layouts.front-end.app')

@section('title', ($program === 'reseller' ? 'Reseller Sign Up' : 'Affiliate Sign Up').' | FastXHorizon')

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/plugin/intl-tel-input/css/intlTelInput.css') }}">
@endpush

@section('content')
    <div class="container py-4 __inline-7 text-align-direction">
        <div class="login-card">
            <div class="mx-auto __max-w-760">
                <h2 class="text-center h4 mb-2 font-bold text-capitalize fs-18-mobile">
                    {{ $program === 'reseller' ? 'Reseller Sign Up' : 'Affiliate Sign Up' }}
                </h2>
                <p class="text-center text-muted mb-4">Create your FastXHorizon account to continue your {{ $program }} application.</p>

                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }} @if(session('existing_account_portal'))<a class="d-block text-underline" href="{{ session('existing_account_portal') }}">Sign in to continue your application</a>@endif</div>
                @endif

                <div class="small text-muted text-center mb-4">Account <span class="mx-2">›</span> Application <span class="mx-2">›</span> Review</div>
                <form class="needs-validation_" action="{{ route($program.'.register.submit') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-sm-6"><div class="form-group"><label class="form-label font-semibold">{{ translate('first_name') }} <span class="input-required-icon">*</span></label><input class="form-control text-align-direction" value="{{ old('f_name') }}" type="text" name="f_name" placeholder="{{ translate('Ex') }}: {{ translate('Jhone') }}" required></div></div>
                        <div class="col-sm-6"><div class="form-group"><label class="form-label font-semibold">{{ translate('last_name') }} <span class="input-required-icon">*</span></label><input class="form-control text-align-direction" value="{{ old('l_name') }}" type="text" name="l_name" placeholder="{{ translate('ex') }}: {{ translate('Doe') }}" required></div></div>
                        <div class="col-sm-6"><div class="form-group"><label class="form-label font-semibold">{{ translate('email_address') }} <span class="input-required-icon">*</span></label><input class="form-control text-align-direction" type="email" value="{{ old('email') }}" name="email" placeholder="{{ translate('enter_email_address') }}" autocomplete="off" required></div></div>
                        <div class="col-sm-6"><div class="form-group"><label class="form-label font-semibold">{{ translate('phone_number') }} <span class="input-required-icon">*</span></label><input class="form-control phone-input-with-country-picker" type="tel" value="{{ old('phone') }}" name="phone" placeholder="{{ translate('enter_phone_number') }}" required></div></div>
                        <div class="col-sm-6"><div class="form-group"><label class="form-label font-semibold">{{ translate('password') }} <span class="input-required-icon">*</span></label><div class="password-toggle rtl"><input class="form-control text-align-direction" name="password" type="password" placeholder="{{ translate('minimum_8_characters_long') }}" required><label class="password-toggle-btn"><input class="custom-control-input" type="checkbox"><i class="tio-hidden password-toggle-indicator"></i><span class="sr-only">{{ translate('show_password') }}</span></label></div></div></div>
                        <div class="col-sm-6"><div class="form-group"><label class="form-label font-semibold">{{ translate('confirm_password') }} <span class="input-required-icon">*</span></label><div class="password-toggle rtl"><input class="form-control text-align-direction" name="con_password" type="password" placeholder="{{ translate('minimum_8_characters_long') }}" required><label class="password-toggle-btn"><input class="custom-control-input" type="checkbox"><i class="tio-hidden password-toggle-indicator"></i><span class="sr-only">{{ translate('show_password') }}</span></label></div></div></div>
                    </div>
                    <div class="form-group mt-2"><label class="custom-control custom-checkbox m-0 d-flex"><input type="checkbox" class="custom-control-input" name="terms_accepted" value="1" required><span class="custom-control-label"><span>{{ translate('i_agree_to_Your') }}</span> <a class="font-size-sm text-primary text-force-underline" target="_blank" href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}">{{ translate('terms_and_condition') }}</a></span></label></div>
                    <div class="web-direction"><div class="mx-auto mt-4 __max-w-356"><button class="w-100 btn btn--primary" type="submit">{{ $program === 'reseller' ? 'Continue as Reseller' : 'Continue as Affiliate' }}</button></div></div>
                </form>
                <div class="text-black-50 mt-3 text-center"><small>{{ translate('Already_have_account ') }}? <a class="text-primary text-underline" href="{{ route($program.'.login') }}">{{ translate('sign_in') }}</a></small></div>
            </div>
        </div>
    </div>
@endsection
