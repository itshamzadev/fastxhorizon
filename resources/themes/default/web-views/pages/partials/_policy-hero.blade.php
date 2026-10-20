@php
    $hero = $visual['hero'] ?? null;
    $heroUrl = $hero && str_starts_with($hero, 'theme:')
        ? theme_asset(path: 'public/assets/front-end/img/' . substr($hero, 6))
        : ($hero ? dynamicStorage(path: 'storage/app/public/company-pages/' . $hero) : null);
@endphp
<section class="fxh-policy-hero fxh-policy-hero--{{ $visual['theme'] ?? 'default' }}">
    <div class="container">
        <nav class="fxh-policy-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span>{{ normalizeCompanyIdentityText($businessPage->title) }}</span></nav>
        <div class="fxh-policy-hero__grid">
            <div class="fxh-policy-hero__copy">
                <span class="fxh-eyebrow">{{ $visual['eyebrow'] ?? getCompanyIdentity('brand') }}</span>
                <h1>{{ normalizeCompanyIdentityText($businessPage->hero_title ?: $businessPage->title) }}</h1>
                <p>{{ normalizeCompanyIdentityText($businessPage->hero_subtitle ?: ($visual['summary'] ?? '')) }}</p>
                @if($businessPage->published_at)
                    <div class="fxh-policy-updated"><span>Last updated</span> {{ $businessPage->published_at->timezone('Asia/Karachi')->format('d M Y, g:i A') }} PKT · {{ $businessPage->published_at->utc()->format('d M Y, g:i A') }} UTC</div>
                @endif
            </div>
            @if($heroUrl)<div class="fxh-policy-hero__media"><img src="{{ $heroUrl }}" width="800" height="500" alt="{{ $visual['alt'] ?? $businessPage->title }}" fetchpriority="high"></div>@endif
        </div>
    </div>
</section>
