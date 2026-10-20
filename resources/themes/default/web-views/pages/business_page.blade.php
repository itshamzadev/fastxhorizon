@extends('layouts.front-end.app')

@section('title', ($companyProfile['legal_name'] ?? 'FASTXHORIZON (PRIVATE) LIMITED') . ' | About Us')

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/about-page.css') }}">
@endpush

@section('content')
    @php
        $aboutHero = $businessPage->hero_image && str_starts_with($businessPage->hero_image, 'theme:') ? theme_asset(path: 'public/assets/front-end/img/' . substr($businessPage->hero_image, 6)) : null;
        $aboutIntro = $businessPage->supporting_image_1 && str_starts_with($businessPage->supporting_image_1, 'theme:') ? theme_asset(path: 'public/assets/front-end/img/' . substr($businessPage->supporting_image_1, 6)) : null;
        $aboutGlobal = $businessPage->supporting_image_2 && str_starts_with($businessPage->supporting_image_2, 'theme:') ? theme_asset(path: 'public/assets/front-end/img/' . substr($businessPage->supporting_image_2, 6)) : null;
    @endphp
    <main class="fxh-about-page">
        <section class="fxh-about-hero">
            <div class="container">
                <div class="fxh-about-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}">{{ translate('Home') }}</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ translate('about_us') }}</span>
                </div>

                <div class="row align-items-center g-4 g-lg-5">
                    <div class="col-lg-6">
                        <div class="fxh-about-eyebrow">{{ strtoupper($companyProfile['legal_name'] ?? 'FASTXHORIZON (PRIVATE) LIMITED') }}</div>
                        <h1 class="fxh-about-hero__title">Building the future of smarter online shopping.</h1>
                        <p class="fxh-about-hero__copy">
                            {{ $companyProfile['brand_name'] }} is a modern ecommerce company connecting customers and sellers through a trusted,
                            technology-led marketplace built for discovery, convenience, and long-term growth.
                        </p>
                        <div class="fxh-about-actions">
                            <a class="fxh-about-button fxh-about-button--primary" href="{{ route('products') }}">
                                Explore Marketplace <i class="fa fa-arrow-right" aria-hidden="true"></i>
                            </a>
                            <a class="fxh-about-button fxh-about-button--secondary" href="{{ route('contacts') }}">
                                Contact Us
                            </a>
                        </div>
                        <div class="fxh-about-hero__note">
                            <span class="fxh-about-status-dot" aria-hidden="true"></span>
                            Founded {{ $companyProfile['founded_year'] ?? '2026' }} · A growing digital marketplace designed around people and possibility
                        </div>
                        <div class="fxh-about-company-meta">
                            <span><i class="fa fa-calendar-o" aria-hidden="true"></i> Founded {{ $companyProfile['founded_year'] ?? '2026' }}</span>
                            @if(!empty($companyProfile['registration_number']) && !empty($companyProfile['registration_type']))
                                <span><i class="fa fa-check-circle-o" aria-hidden="true"></i> {{ $companyProfile['registration_type'] }}: {{ $companyProfile['registration_number'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="fxh-about-hero__visual" aria-label="FastXHorizon marketplace platform illustration" role="img">
                            @if($aboutHero)<img class="fxh-about-hero__photo" src="{{ $aboutHero }}" alt="{{ $companyProfile['brand_name'] }} marketplace operations" fetchpriority="high">@endif
                            <div class="fxh-about-visual-glow fxh-about-visual-glow--one"></div>
                            <div class="fxh-about-visual-glow fxh-about-visual-glow--two"></div>
                            <div class="fxh-about-orbit fxh-about-orbit--one"></div>
                            <div class="fxh-about-orbit fxh-about-orbit--two"></div>

                            <div class="fxh-about-dashboard">
                                <div class="fxh-about-dashboard__topbar">
                                    <span class="fxh-about-dashboard__brand"><span></span> FastXHorizon</span>
                                    <span class="fxh-about-dashboard__menu"><i></i><i></i><i></i></span>
                                </div>
                                <div class="fxh-about-dashboard__welcome">Your marketplace, connected.</div>
                                <div class="fxh-about-dashboard__search"><i class="fa fa-search" aria-hidden="true"></i> Find something useful</div>
                                <div class="fxh-about-dashboard__tiles">
                                    <div class="fxh-about-dashboard__tile fxh-about-dashboard__tile--blue"><i class="fa fa-shopping-bag" aria-hidden="true"></i><span>Discover</span></div>
                                    <div class="fxh-about-dashboard__tile fxh-about-dashboard__tile--cyan"><i class="fa fa-users" aria-hidden="true"></i><span>Connect</span></div>
                                    <div class="fxh-about-dashboard__tile fxh-about-dashboard__tile--light"><i class="fa fa-line-chart" aria-hidden="true"></i><span>Grow</span></div>
                                </div>
                                <div class="fxh-about-dashboard__footer-line"><span></span><span></span><span></span></div>
                            </div>

                            <div class="fxh-about-float-card fxh-about-float-card--trust">
                                <i class="fa fa-shield" aria-hidden="true"></i>
                                <span><strong>Built on trust</strong><small>For every journey</small></span>
                            </div>
                            <div class="fxh-about-float-card fxh-about-float-card--growth">
                                <i class="fa fa-arrow-up" aria-hidden="true"></i>
                                <span><strong>Made to grow</strong><small>For every seller</small></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="container">@include('web-views.pages.partials._policy-navigation')</div>

        <section class="fxh-about-section fxh-about-intro">
            <div class="container">
                <div class="row align-items-center g-4 g-lg-5">
                    <div class="col-lg-7">
                        <div class="fxh-about-eyebrow">WHO WE ARE</div>
                        <h2 class="fxh-about-heading">A marketplace built for momentum.</h2>
                        <p class="fxh-about-lead">
                            {{ $companyProfile['legal_name'] ?? 'FASTXHORIZON (PRIVATE) LIMITED' }} is a modern ecommerce company focused on making online shopping simpler,
                            faster, and more accessible.
                        </p>
                        <p class="fxh-about-copy">
                            Through our multi-vendor marketplace, customers can discover products from trusted sellers across
                            multiple categories, compare options, and shop with confidence in one connected digital experience.
                        </p>
                    </div>
                    <div class="col-lg-5">
                        <div class="fxh-about-proof-card {{ $aboutIntro ? 'fxh-about-proof-card--photo' : '' }}">
                            @if($aboutIntro)<img src="{{ $aboutIntro }}" loading="lazy" alt="People collaborating on a modern commerce platform">@endif
                            <div class="fxh-about-proof-card__icon"><i class="fa fa-compass" aria-hidden="true"></i></div>
                            <div>
                                <span class="fxh-about-proof-card__label">Our direction</span>
                                <h3>Make digital commerce feel more human.</h3>
                                <p>Clear choices, dependable service, and better opportunities for the people behind every order.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if($aboutGlobal)
            <section class="fxh-about-section fxh-about-global">
        <div class="container"><div class="row align-items-center g-4 g-lg-5"><div class="col-lg-6"><img class="fxh-about-global__image" src="{{ $aboutGlobal }}" loading="lazy" alt="Global commerce and connected delivery routes"></div><div class="col-lg-6"><div class="fxh-about-eyebrow">CONNECTED COMMERCE</div><h2 class="fxh-about-heading">Designed for commerce that can travel further.</h2><p class="fxh-about-copy">{{ $companyProfile['brand_name'] }} is growing from Pakistan toward eligible international destinations, with the technology, seller relationships, and customer clarity needed to make cross-border commerce feel more connected.</p></div></div></div>
            </section>
        @endif

        <section class="fxh-about-section fxh-about-mission">
            <div class="container">
                <div class="text-center fxh-about-section-heading">
                    <div class="fxh-about-eyebrow">PURPOSE WITH DIRECTION</div>
                    <h2 class="fxh-about-heading">What keeps us moving forward</h2>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <article class="fxh-about-purpose-card fxh-about-purpose-card--mission">
                            <div class="fxh-about-purpose-card__icon"><i class="fa fa-bullseye" aria-hidden="true"></i></div>
                            <div>
                                <span class="fxh-about-card-kicker">Our mission</span>
                                <h3>Make every interaction simpler and more dependable.</h3>
                                <p>We are creating a reliable, customer-focused ecommerce ecosystem that brings buyers and sellers together through technology, convenience, and trust.</p>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-6">
                        <article class="fxh-about-purpose-card fxh-about-purpose-card--vision">
                            <div class="fxh-about-purpose-card__icon"><i class="fa fa-eye" aria-hidden="true"></i></div>
                            <div>
                                <span class="fxh-about-card-kicker">Our vision</span>
                                <h3>Become a trusted digital marketplace at scale.</h3>
                                <p>We want to help customers shop confidently while giving businesses and sellers the tools to reach new audiences and grow online.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="fxh-about-section fxh-about-platform">
            <div class="container">
                <div class="row align-items-end g-4 mb-4 mb-lg-5">
                    <div class="col-lg-7">
                        <div class="fxh-about-eyebrow">THE PLATFORM</div>
                        <h2 class="fxh-about-heading">More than a storefront.<br><span>A connected commerce platform.</span></h2>
                    </div>
                    <div class="col-lg-5">
                        <p class="fxh-about-copy mb-0">From product discovery to seller growth, FastXHorizon brings the essential parts of online commerce into one accessible experience.</p>
                    </div>
                </div>
                <div class="row g-3 g-lg-4">
                    <div class="col-sm-6 col-lg-3">
                        <article class="fxh-about-feature-card"><div class="fxh-about-feature-card__icon"><i class="fa fa-shopping-bag" aria-hidden="true"></i></div><h3>Discover</h3><p>Explore products across everyday categories in one place.</p></article>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <article class="fxh-about-feature-card"><div class="fxh-about-feature-card__icon"><i class="fa fa-store" aria-hidden="true"></i></div><h3>Enable sellers</h3><p>Give businesses a practical route to reach more customers online.</p></article>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <article class="fxh-about-feature-card"><div class="fxh-about-feature-card__icon"><i class="fa fa-random" aria-hidden="true"></i></div><h3>Connect</h3><p>Make browsing, comparison, ordering, and fulfilment feel connected.</p></article>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <article class="fxh-about-feature-card"><div class="fxh-about-feature-card__icon"><i class="fa fa-line-chart" aria-hidden="true"></i></div><h3>Grow</h3><p>Build a scalable foundation for a stronger digital marketplace.</p></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="fxh-about-section fxh-about-why">
            <div class="container">
                <div class="row align-items-center g-4 g-lg-5">
                    <div class="col-lg-5">
                        <div class="fxh-about-eyebrow fxh-about-eyebrow--light">WHY FASTXHORIZON</div>
                        <h2 class="fxh-about-heading fxh-about-heading--light">Commerce that keeps people at the centre.</h2>
                        <p class="fxh-about-copy fxh-about-copy--light">We are building the kind of marketplace people can return to: useful for customers, practical for sellers, and ready to evolve with the way commerce is changing.</p>
                    </div>
                    <div class="col-lg-7">
                        <div class="fxh-about-benefits">
                            <div class="fxh-about-benefit"><span class="fxh-about-benefit__number">01</span><div><h3>Wide product variety</h3><p>One destination for the products and categories customers use every day.</p></div></div>
                            <div class="fxh-about-benefit"><span class="fxh-about-benefit__number">02</span><div><h3>A smoother experience</h3><p>Clear discovery and a more confident path from browsing to purchase.</p></div></div>
                            <div class="fxh-about-benefit"><span class="fxh-about-benefit__number">03</span><div><h3>Opportunity for sellers</h3><p>Tools and visibility that help businesses build their digital presence.</p></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="fxh-about-section fxh-about-audience">
            <div class="container">
                <div class="text-center fxh-about-section-heading">
                    <div class="fxh-about-eyebrow">ONE PLATFORM, TWO SIDES OF GROWTH</div>
                    <h2 class="fxh-about-heading">Useful for customers. Valuable for sellers.</h2>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <article class="fxh-about-audience-card fxh-about-audience-card--customer"><div class="fxh-about-audience-card__top"><div class="fxh-about-audience-card__icon"><i class="fa fa-user" aria-hidden="true"></i></div><span>For customers</span></div><h3>Everything you need to shop with confidence.</h3><ul><li>Discover products from multiple categories</li><li>Compare options and make informed choices</li><li>Manage orders through a convenient digital experience</li></ul><a href="{{ route('products') }}">Start exploring <i class="fa fa-arrow-right" aria-hidden="true"></i></a></article>
                    </div>
                    <div class="col-lg-6">
                        <article class="fxh-about-audience-card fxh-about-audience-card--seller"><div class="fxh-about-audience-card__top"><div class="fxh-about-audience-card__icon"><i class="fa fa-store" aria-hidden="true"></i></div><span>For sellers</span></div><h3>A practical place to build your next chapter online.</h3><ul><li>List products and reach new audiences</li><li>Grow your online presence with a marketplace home</li><li>Manage products and orders in one platform</li></ul><a href="{{ route('vendor.auth.registration.index') }}">Become a seller <i class="fa fa-arrow-right" aria-hidden="true"></i></a></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="fxh-about-section fxh-about-values">
            <div class="container">
                <div class="row align-items-end g-4 mb-4 mb-lg-5">
                    <div class="col-lg-7"><div class="fxh-about-eyebrow">WHAT DRIVES US</div><h2 class="fxh-about-heading">Principles behind the platform.</h2></div>
                    <div class="col-lg-5"><p class="fxh-about-copy mb-0">The marketplace is only as strong as the standards behind it. These values guide how FastXHorizon serves, builds, and grows.</p></div>
                </div>
                <div class="row g-3 g-lg-4">
                    <div class="col-6 col-lg-4"><article class="fxh-about-value-card"><i class="fa fa-shield" aria-hidden="true"></i><h3>Trust</h3><p>Earn confidence through clarity and consistency.</p></article></div>
                    <div class="col-6 col-lg-4"><article class="fxh-about-value-card"><i class="fa fa-lightbulb-o" aria-hidden="true"></i><h3>Innovation</h3><p>Keep improving the way people experience commerce.</p></article></div>
                    <div class="col-6 col-lg-4"><article class="fxh-about-value-card"><i class="fa fa-universal-access" aria-hidden="true"></i><h3>Accessibility</h3><p>Make digital opportunity easier to reach.</p></article></div>
                    <div class="col-6 col-lg-4"><article class="fxh-about-value-card"><i class="fa fa-line-chart" aria-hidden="true"></i><h3>Growth</h3><p>Create room for customers and sellers to move forward.</p></article></div>
                    <div class="col-6 col-lg-4"><article class="fxh-about-value-card"><i class="fa fa-check-circle" aria-hidden="true"></i><h3>Reliability</h3><p>Focus on dependable experiences at every touchpoint.</p></article></div>
                    <div class="col-6 col-lg-4"><article class="fxh-about-value-card"><i class="fa fa-heart" aria-hidden="true"></i><h3>Customer focus</h3><p>Listen closely and build around real needs.</p></article></div>
                </div>
            </div>
        </section>

        @if(isset($leadership) && $leadership->isNotEmpty())
            <section class="fxh-about-section fxh-about-leadership">
                <div class="container">
                    <div class="text-center fxh-about-section-heading"><div class="fxh-about-eyebrow">THE PEOPLE BEHIND THE COMPANY</div><h2 class="fxh-about-heading">Leadership</h2></div>
                    <div class="row g-4 justify-content-center fxh-about-leadership__grid">
                        @foreach($leadership as $leader)
                            @php($leaderPhoto = $leader->photo && str_starts_with($leader->photo, 'public/') ? dynamicAsset(path: $leader->photo) : ($leader->photo ? getStorageImages(path: $leader->photo, type: 'company') : null))
                            <div class="col-sm-6 col-lg-5"><article class="fxh-about-leader-card">
                                <div class="fxh-about-leader-card__photo">@if($leaderPhoto)<img src="{{ $leaderPhoto }}" alt="{{ $leader->name }}" loading="lazy">@else<div class="fxh-about-leader-card__placeholder" aria-hidden="true"><i class="fa fa-user"></i></div>@endif</div>
                                <div class="fxh-about-leader-card__body"><span>{{ $leader->role }}</span><h3>{{ $leader->name }}</h3><p>{{ normalizeCompanyIdentityText($leader->bio) }}</p></div>
                            </article></div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="fxh-about-ecosystem">
            <div class="container">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7"><div class="fxh-about-eyebrow fxh-about-eyebrow--light">THE BIGGER PICTURE</div><h2 class="fxh-about-heading fxh-about-heading--light">A growing ecosystem, designed for what comes next.</h2></div>
                    <div class="col-lg-5"><div class="fxh-about-ecosystem-points"><span><i class="fa fa-check" aria-hidden="true"></i> Multi-category marketplace</span><span><i class="fa fa-check" aria-hidden="true"></i> Customer-first platform</span><span><i class="fa fa-check" aria-hidden="true"></i> Scalable seller ecosystem</span></div></div>
                </div>
            </div>
        </section>

        <section class="fxh-about-cta">
            <div class="container">
                <div class="fxh-about-cta__inner">
                    <div><div class="fxh-about-eyebrow">LET'S MOVE FORWARD</div><h2>Better commerce starts with a better connection.</h2><p>Explore the marketplace, discover something useful, or join the FastXHorizon seller community.</p></div>
                    <div class="fxh-about-actions"><a class="fxh-about-button fxh-about-button--primary" href="{{ route('products') }}">Start Shopping <i class="fa fa-arrow-right" aria-hidden="true"></i></a><a class="fxh-about-button fxh-about-button--secondary" href="{{ route('vendor.auth.registration.index') }}">Become a Seller</a></div>
                </div>
                @php($aboutWhatsappDigits = preg_replace('/\D+/', '', (string) ($companyProfile['phone'] ?? '923265680129')))
                <div class="fxh-about-contact-strip"><span><i class="fa fa-envelope-o"></i> {{ $companyProfile['email'] ?? 'info@fastxhorizon.com' }}</span><a href="https://wa.me/{{ $aboutWhatsappDigits }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-whatsapp"></i> {{ $companyProfile['phone'] ?? '+92 326 5680129' }}</a><span><i class="fa fa-globe"></i> {{ $companyProfile['website'] ?? 'www.fastxhorizon.com' }}</span></div>
            </div>
        </section>
    </main>
@endsection
