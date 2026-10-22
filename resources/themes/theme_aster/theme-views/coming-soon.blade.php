@extends('theme-views.layouts.app')

@section('title', 'FastXHorizon | Store Coming Soon')

@section('content')
    <main class="main-content py-5">
        <section class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-9 col-lg-7">
                    <div class="bg-white border rounded-3 text-center px-4 py-5 px-md-5 shadow-sm">
                        <img src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}"
                             alt="{{ $web_config['company_name'] }}"
                             class="img-fluid mb-4"
                             style="max-width: 240px; max-height: 90px; object-fit: contain;">
                        <h1 class="mb-3">Store Coming Soon</h1>
                        <p class="text-muted mb-0">
                            We are preparing something special for you. Please check back soon.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
