@extends('layouts.front-end.app')

@section('title', translate('temporarily_unavailable'))

@section('content')
<section class="container rtl py-5">
    <div class="text-center mb-4">
        <span class="text-uppercase small text-primary fw-bold">{{ translate('fast_x_horizon') }}</span>
        <h1 class="font-bold mt-2">{{ translate('temporarily_unavailable') }}</h1>
        <p class="text-muted mb-0">{{ translate('these_products_are_currently_unavailable') }}</p>
    </div>
    @if($products->count())
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 g-2">
            @foreach($products as $product)
                <div class="col">@include('web-views.partials._compact-product-card', ['product' => $product])</div>
            @endforeach
        </div>
        <div class="mt-4 d-flex justify-content-center">{{ $products->links() }}</div>
    @else
        <div class="text-center py-5 text-muted">{{ translate('no_products_found') }}</div>
    @endif
</section>
@endsection

