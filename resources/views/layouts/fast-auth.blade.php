<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FastXHorizon Authentication')</title>
    <style>
        :root { --fx-primary: #1677ff; --fx-ink: #16202a; --fx-muted: #6b7280; --fx-border: #e5e7eb; }
        .fx-auth-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:32px 16px; background:linear-gradient(135deg,#f5f8fc 0%,#eef4fb 100%); color:var(--fx-ink); font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        .fx-auth-card { width:100%; max-width:560px; background:#fff; border:1px solid rgba(22,119,255,.08); border-radius:24px; box-shadow:0 18px 55px rgba(31,55,80,.12); padding:36px; }
        .fx-auth-logo { display:block; max-width:210px; max-height:58px; width:auto; height:auto; margin:0 auto 24px; object-fit:contain; }
        .fx-auth-heading { text-align:center; margin-bottom:24px; }
        .fx-auth-heading h1 { color:var(--fx-ink); font-size:28px; font-weight:700; margin:0 0 8px; }
        .fx-auth-heading p { color:var(--fx-muted); margin:0; line-height:1.55; }
        .fx-auth-card form { width:100%; }
        .fx-auth-card label { display:block; width:100%; color:#374151; font-size:14px; font-weight:600; margin:0 0 7px; }
        .fx-auth-card .form-control, .fx-auth-card .form-select { display:block; width:100%; min-height:48px; box-sizing:border-box; border:1px solid var(--fx-border); border-radius:12px; padding:11px 14px; box-shadow:none; }
        .fx-auth-card .form-control:focus, .fx-auth-card .form-select:focus { border-color:var(--fx-primary); box-shadow:0 0 0 3px rgba(22,119,255,.12); }
        .fx-auth-card input[type="checkbox"] { width:16px; height:16px; margin:0 7px 0 0; accent-color:var(--fx-primary); vertical-align:middle; }
        .fx-auth-remember { display:flex; width:100%; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin:0 0 18px; font-size:14px; }
        .fx-auth-remember label { display:flex; width:auto; align-items:center; margin:0; font-weight:500; white-space:nowrap; }
        .fx-auth-remember a { margin-left:auto; white-space:nowrap; }
        .fx-auth-primary { width:100%; min-height:48px; border:0; border-radius:12px; background:var(--fx-primary); color:#fff; font-weight:700; padding:12px 18px; transition:opacity .2s,transform .2s; }
        .fx-auth-primary:hover { color:#fff; opacity:.92; transform:translateY(-1px); }
        .fx-auth-links { text-align:center; color:var(--fx-muted); font-size:14px; }
        .fx-auth-links a { color:var(--fx-primary); text-decoration:none; font-weight:600; }
        .fx-auth-links a:hover { text-decoration:underline; }
        .fx-auth-switcher { border-top:1px solid var(--fx-border); margin-top:24px; padding-top:18px; text-align:center; font-size:13px; color:var(--fx-muted); }
        .fx-auth-switcher a { display:inline-block; margin:6px 7px 0; color:#4b5563; text-decoration:none; }
        .fx-auth-switcher a.is-current { color:var(--fx-primary); font-weight:700; }
        .fx-auth-alert { border-radius:12px; padding:11px 14px; margin-bottom:18px; font-size:14px; }
        .fx-auth-alert.error { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
        .fx-auth-alert.success { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
        .fx-auth-divider { border:0; border-top:1px solid var(--fx-border); margin:24px 0 18px; }
        .fx-auth-steps { display:flex; gap:8px; margin:0 0 22px; color:var(--fx-muted); font-size:12px; }
        .fx-auth-steps span { flex:1; padding:8px 6px; border-bottom:2px solid var(--fx-border); text-align:center; }
        .fx-auth-steps span.is-active { border-color:var(--fx-primary); color:var(--fx-primary); font-weight:700; }
        @media (max-width:575px) { .fx-auth-page { padding:18px 12px; align-items:flex-start; } .fx-auth-card { margin-top:14px; padding:26px 20px; border-radius:18px; } .fx-auth-heading h1 { font-size:24px; } .fx-auth-logo { max-width:180px; margin-bottom:20px; } }
    </style>
    @yield('auth_head')
</head>
<body>
<main class="fx-auth-page">
    <section class="fx-auth-card" aria-labelledby="fx-auth-title">
        @php($fxPortal = trim((string) trim($__env->yieldContent('auth_portal'))))
        @php($fxLogo = function_exists('getWebConfig') ? getWebConfig(name: 'company_web_logo') : null)
        <a href="{{ route('home') }}"><img class="fx-auth-logo" src="{{ $fxLogo ? getStorageImages(path: $fxLogo, type: 'backend-logo') : asset('fastxhorizon.png') }}" alt="FastXHorizon"></a>
        <div class="fx-auth-heading"><h1 id="fx-auth-title">@yield('auth_title')</h1><p>@yield('auth_subtitle')</p></div>
        @yield('auth_content')
        @if($fxPortal !== '')
            <nav class="fx-auth-switcher" aria-label="Authentication portal switcher">Looking for another portal?<br>
                <a class="{{ $fxPortal === 'customer' ? 'is-current' : '' }}" href="{{ route('customer.auth.login') }}">Customer</a>
                <a class="{{ $fxPortal === 'seller' ? 'is-current' : '' }}" href="{{ route('vendor.auth.login') }}">Seller</a>
                <a class="{{ $fxPortal === 'reseller' ? 'is-current' : '' }}" href="{{ route('reseller.login') }}">Reseller</a>
                <a class="{{ $fxPortal === 'affiliate' ? 'is-current' : '' }}" href="{{ route('affiliate.login') }}">Affiliate</a>
            </nav>
        @endif
    </section>
</main>
@yield('auth_scripts')
</body>
</html>
