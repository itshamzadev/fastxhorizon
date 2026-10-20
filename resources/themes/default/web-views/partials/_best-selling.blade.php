<div class="col-lg-6 px-max-md-0">
    <div class="card card __shadow h-100">
        <div class="card-body p-xl-35">
            <div class="row d-flex justify-content-between align-items-center mx-1 mb-3">
                <div class="d-flex gap-1 align-items-center">
                    <img loading="lazy" class="size-30" src="{{ theme_asset(path: 'public/assets/front-end/png/best-sellings.png') }}" alt="{{ translate('best_sellings') }}">
                    <h2 class="font-bold pl-1 mb-0 fs-16">{{ translate('best_sellings') }}</h2>
                </div>
                <a class="text-capitalize view-all-btn-text" href="{{ route('best-selling-products') }}">
                    <span class="view-btn-text">{{ translate('View_All') }}</span>
                    <i class="czi-arrow-{{ session('direction') === 'rtl' ? 'left' : 'right' }}"></i>
                </a>
            </div>
            <div class="row row-cols-2 row-cols-md-3 g-2 fxh-compact-product-grid">
                @foreach($bestSellProduct as $key => $bestSellItem)
                    @if($bestSellItem && $key < 6)
                        <div class="col">
                            @include('web-views.partials._compact-product-card', ['product' => $bestSellItem])
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
