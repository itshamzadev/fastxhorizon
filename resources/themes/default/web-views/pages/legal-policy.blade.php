@extends('layouts.front-end.app')

@section('title', normalizeCompanyIdentityText($businessPage->seo_title ?: $businessPage->title) . ' | ' . getCompanyIdentity('brand'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/legal-policy.css') }}">
@endpush

@section('content')
    @php
        $visuals = [
            'terms-and-conditions' => ['theme' => 'terms', 'eyebrow' => 'Marketplace agreement', 'summary' => 'A clear framework for customers, sellers, products, orders, and responsible commerce.', 'hero' => $businessPage->hero_image, 'alt' => 'Professional business documents on a desk'],
            'privacy-policy' => ['theme' => 'privacy', 'eyebrow' => 'Privacy & trust', 'summary' => 'How ' . getCompanyIdentity('brand') . ' uses information responsibly to operate a secure marketplace.', 'hero' => $businessPage->hero_image, 'alt' => 'Secure digital commerce and privacy technology'],
            'refund-policy' => ['theme' => 'refund', 'eyebrow' => 'Customer resolution', 'summary' => 'A straightforward path from refund request to review, approval, and wallet credit.', 'hero' => $businessPage->hero_image, 'alt' => 'Customer reviewing an online payment'],
            'return-policy' => ['theme' => 'return', 'eyebrow' => 'Returns & reverse logistics', 'summary' => 'Know what is eligible, what to prepare, and how a return moves toward resolution.', 'hero' => $businessPage->hero_image, 'alt' => 'Packages prepared for ecommerce delivery and returns'],
            'cancellation-policy' => ['theme' => 'cancellation', 'eyebrow' => 'Order control', 'summary' => 'Understand when an order can be cancelled and what happens after fulfilment begins.', 'hero' => $businessPage->hero_image, 'alt' => 'Order processing and fulfilment planning'],
        ];
        $visual = $visuals[$businessPage->slug] ?? $visuals['terms-and-conditions'];
        $visual['summary'] = normalizeCompanyIdentityText($visual['summary']);
        $safeDescription = normalizeCompanyIdentityText($businessPage->description);
        $safeDescription = preg_replace('/<(script|style|iframe|object|embed)[^>]*>.*?<\/\1>/is', '', $safeDescription);
        $safeDescription = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $safeDescription);
        $toc = [];
        $headingIndex = 0;
        $safeDescription = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/is', function ($match) use (&$toc, &$headingIndex) {
            $id = 'policy-section-' . (++$headingIndex);
            $toc[] = ['id' => $id, 'label' => trim(strip_tags($match[2]))];
            return '<h2 id="' . $id . '">' . $match[2] . '</h2>';
        }, $safeDescription);
        $isPreview = request('source') === 'admin' && $businessPage->policy_status !== 'published';
    @endphp

    <main class="fxh-policy-page fxh-policy-page--{{ $visual['theme'] }}">
        @include('web-views.pages.partials._policy-hero', ['visual' => $visual])
        <div class="container">@include('web-views.pages.partials._policy-navigation')</div>
        @if($isPreview)<div class="container"><div class="fxh-policy-preview">Draft preview — this version is not public.</div></div>@endif
        <section class="container fxh-policy-layout">
            <aside class="fxh-policy-toc" aria-label="On this page"><span class="fxh-eyebrow">On this page</span>@forelse($toc as $item)<a href="#{{ $item['id'] }}">{{ $item['label'] }}</a>@empty<span>Policy overview</span>@endforelse</aside>
            <article class="fxh-policy-reading">
                <div class="fxh-policy-intro"><span class="fxh-eyebrow">At a glance</span><p>{{ normalizeCompanyIdentityText($businessPage->at_a_glance ?: $visual['summary']) }}</p></div>
                @if($businessPage->slug === 'privacy-policy')
                    <div class="fxh-summary-grid"><div><strong>What we collect</strong><span>Information needed to operate your account and order.</span></div><div><strong>Why we use it</strong><span>Service delivery, support, security, and compliance.</span></div><div><strong>Seller access</strong><span>Only order information needed for fulfilment.</span></div><div><strong>Location</strong><span>Country-level signals may support currency localization.</span></div></div>
                    @include('web-views.pages.partials._policy-callout', ['tone' => 'privacy', 'icon' => 'fi fi-rr-shield-check', 'title' => 'Privacy by purpose', 'text' => getCompanyIdentity('brand') . ' does not sell personal information to advertisers. Data is shared only where needed to operate the marketplace or meet a legal obligation.'])
                @elseif($businessPage->slug === 'refund-policy')
                    <h2 class="fxh-visual-heading">A considered resolution, step by step.</h2>
                    @include('web-views.pages.partials._process-timeline', ['steps' => [['title' => 'Submit request', 'text' => 'Share the order item and reason.'], ['title' => 'Review', 'text' => 'The relevant team checks eligibility and evidence.'], ['title' => 'Approval', 'text' => 'An approved amount is confirmed.'], ['title' => 'Wallet credit', 'text' => 'The eligible refund is credited according to the current workflow.']]])
                    @include('web-views.pages.partials._policy-callout', ['tone' => 'refund', 'icon' => 'fi fi-rr-wallet', 'title' => 'Refunds go to ' . getCompanyIdentity('brand') . ' Wallet', 'text' => 'Approved refunds are credited to the customer wallet according to the applicable order and refund rules. Cash withdrawal is not promised by this page.'])
                @elseif($businessPage->slug === 'return-policy')
                    <div class="fxh-return-window"><strong>7-Day Return Request Window</strong><span>Submit eligible requests within the configured return period.</span></div>
                    <div class="fxh-compare-grid"><div class="is-positive"><h3>Eligible examples</h3><p>✓ Damaged, wrong, or defective products<br>✓ Unused change-of-mind items where permitted</p></div><div class="is-negative"><h3>Usually not eligible</h3><p>✕ Materially used working products<br>✕ Delivered digital goods or configured non-returnable items</p></div></div>
                    @include('web-views.pages.partials._process-timeline', ['steps' => [['title' => 'Start return', 'text' => 'Select the relevant delivered item.'], ['title' => 'Upload evidence', 'text' => 'Add clear photos or supporting details.'], ['title' => 'Review', 'text' => 'Seller or ' . getCompanyIdentity('brand') . ' reviews the request.'], ['title' => 'Inspection', 'text' => 'The returned item is assessed.'], ['title' => 'Resolution', 'text' => 'A return, replacement, or refund outcome follows.']]])
                @elseif($businessPage->slug === 'cancellation-policy')
                    @include('web-views.pages.partials._process-timeline', ['steps' => [['title' => 'Order placed', 'text' => 'Cancellation is normally available before confirmation.'], ['title' => 'Order confirmed', 'text' => 'Cancellation becomes restricted as preparation begins.'], ['title' => 'Fulfilment', 'text' => 'Delivery or return/refund rules apply.']]])
                    @include('web-views.pages.partials._policy-callout', ['tone' => 'warning', 'icon' => 'fi fi-rr-info', 'title' => 'A clear expectation for prepaid orders', 'text' => 'If a seller cannot fulfil a prepaid order and cancellation is confirmed, the customer receives the applicable full eligible refund.'])
                @else
                    <div class="fxh-terms-callouts"><div><strong>Marketplace role</strong><span>{{ getCompanyIdentity('brand') }} provides the technology and operating framework for marketplace commerce.</span></div><div><strong>Seller responsibility</strong><span>Sellers remain responsible for accurate listings, fulfilment, and compliant conduct.</span></div><div><strong>Responsible use</strong><span>Customers and sellers are expected to use accounts and services lawfully.</span></div></div>
                @endif
                <div class="fxh-policy-richtext">{!! $safeDescription !!}</div>
                @if(in_array($businessPage->slug, ['refund-policy', 'return-policy']))
                    <div class="fxh-policy-action"><span class="fxh-eyebrow">Need help with an order?</span><h2>Start a {{ $businessPage->slug === 'return-policy' ? 'return' : 'refund' }} request</h2><p>Choose an eligible delivered item to continue with the secure request form.</p>
                        @if(auth('customer')->check() && $eligibleReturnItems->isNotEmpty())<form method="get" action="{{ route('refund-request', ['id' => $eligibleReturnItems->first()->id]) }}"><select name="id" aria-label="Eligible order item" onchange="this.form.action='{{ url('/refund-request') }}/'+this.value"><option value="">Select an eligible order item</option>@foreach($eligibleReturnItems as $item)<option value="{{ $item->id }}">#{{ $item->order_id }} · {{ $item->product?->name ?? 'Order item' }}</option>@endforeach</select><button type="submit">Continue <i class="fa fa-arrow-right"></i></button></form>@else<a class="fxh-policy-button" href="{{ auth('customer')->check() ? route('account-oder') : route('customer.auth.login') }}">{{ auth('customer')->check() ? 'View My Orders' : 'Sign In to Continue' }} <i class="fa fa-arrow-right"></i></a>@endif
                    </div>
                @endif
            </article>
        </section>
        @include('web-views.pages.partials._policy-related', ['businessPage' => $businessPage])
        @include('web-views.pages.partials._policy-contact', ['companyContact' => $companyContact])
    </main>
@endsection
