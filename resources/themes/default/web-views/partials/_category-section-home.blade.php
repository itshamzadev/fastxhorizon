@if ($categories->count() > 0 )
    <section class="container py-0 rtl px-0 px-md-3">
        <div class="__inline-62">
            <div>
                <div class="card __shadow h-100 max-md-shadow-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <h2 class="categories-title m-0 letter-spacing-0 h5 fw-bold">
                                <span class="fw-bold">{{ translate('categories')}}</span>
                            </h2>
                            <div>
                                <a class="text-capitalize view-all-btn-text" href="{{ route('categories') }}">
                                    <span class="view-btn-text">{{ translate('View_All') }}</span>
                                    <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                                </a>
                            </div>
                        </div>
                        <div class="owl-theme owl-carousel categories--slider mt-3" data-slide-items="{{ $categories->count() }}">
                            @foreach($categories as $category)
                                <div class="text-center m-0 __cate-item w-100">
                                    <a href="{{ route('category-products', ['slug' => $category['slug']]) }}" class="d-flex flex-column align-items-center">
                                        <div class="__img mw-100 h-auto">
                                            <img loading="lazy" alt="{{ $category->name }}"
                                                 src="{{ getStorageImages(path: $category->icon_full_url, type: 'category') }}">
                                        </div>
                                        <h3 class="text-center line--limit-2 small mt-2 letter-spacing-0">{{ $category->name }}</h3>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
