@if(isset($product) && $product)
    @php
        $cardPricing = getProductPricingBreakdown(product: $product, price: $product->unit_price ?? 0);
        $cardDiscountValue = (float) $cardPricing['total_discount'];
        $cardCurrentPrice = (float) $cardPricing['current_price'];
        $cardCurrentPriceString = webCurrencyConverter(amount: $cardCurrentPrice);
        $cardOriginalPrice = (float) ($product->unit_price ?? 0);
        $cardHasDiscount = $cardDiscountValue > 0 && $cardOriginalPrice > $cardCurrentPrice;
        $cardDiscountType = $cardPricing['flash_deal_product']?->discount_type
            ?? getProductPriceByType(product: $product, type: 'discount_type', result: 'string');
        $cardDiscountLabel = null;
        $cardEffectivePercentage = $cardOriginalPrice > 0 ? round(($cardDiscountValue / $cardOriginalPrice) * 100) : 0;
        if ($cardDiscountValue > 0 && $cardOriginalPrice > $cardCurrentPrice && $cardEffectivePercentage <= 0) {
            $cardDiscountLabel = webCurrencyConverter(amount: $cardDiscountValue);
        }
        $cardReviewCount = isset($product->reviews_count)
            ? (int) $product->reviews_count
            : (method_exists($product, 'relationLoaded') && $product->relationLoaded('reviews') ? $product->reviews->count() : 0);
        $cardAverageRating = isset($product->reviews_avg_rating)
            ? (float) $product->reviews_avg_rating
            : (method_exists($product, 'relationLoaded') && $product->relationLoaded('reviews') && $cardReviewCount > 0 ? (float) $product->reviews->avg('rating') : 0);
        $cardHasRating = $cardReviewCount > 0 && $cardAverageRating > 0;
        $cardHasVariants = (int) ($product->variant_product ?? 0) === 1 || !empty(json_decode($product->choice_options ?? '[]', true));
        $cardIsPhysical = ($product->product_type ?? 'physical') === 'physical';
        $cardIsPhysicalProduct = ($product->product_type ?? null) === 'physical';
        $cardIsDigitalProduct = ($product->product_type ?? null) === 'digital';
        $cardIsOutOfStock = $cardIsPhysical && (int) ($product->current_stock ?? 0) <= 0;
        $cardCanAddDirectly = !$cardHasVariants && !$cardIsOutOfStock;
        $cardId = 'fxh-product-card-form-' . $product->id;
        $cardWishlistActive = function_exists('isProductInWishList') && isProductInWishList((string) $product->id);
        $cardShop = $product->shop ?? $product->seller?->shop;
        $cardStoreName = $cardShop?->name ?: ($product->added_by === 'admin' ? getCompanyIdentity('brand') : translate('seller'));
        $cardStoreUrl = $cardShop?->slug ? route('vendor-shop', ['slug' => $cardShop->slug]) : null;
    @endphp

    <article class="fxh-product-card h-100" data-product-id="{{ $product->id }}">
        <div class="fxh-product-card__media">
            <a class="fxh-product-card__image-link" href="{{ route('product', $product->slug) }}" aria-label="{{ $product->name }}">
                <img loading="lazy" class="fxh-product-card__image"
                     src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'product') }}"
                     alt="{{ $product->name }}">
            </a>

            @if($cardIsPhysicalProduct || $cardIsDigitalProduct)
                <span class="fxh-product-card__type-indicator {{ $cardIsDigitalProduct ? 'fxh-product-card__type-indicator--digital' : 'fxh-product-card__type-indicator--physical' }}"
                      title="{{ translate($cardIsDigitalProduct ? 'Digital_Product' : 'Physical_Product') }}"
                      aria-label="{{ translate($cardIsDigitalProduct ? 'Digital_Product' : 'Physical_Product') }}"
                      role="img">
                    <img src="{{ theme_asset(path: $cardIsDigitalProduct ? 'public/assets/front-end/img/icons/digital-product.svg' : 'public/assets/front-end/img/icons/physical-product.svg') }}"
                         class="fxh-product-card__type-icon"
                         alt="">
                </span>
            @endif

            @if($cardHasDiscount)
                <span class="fxh-product-card__discount" aria-label="{{ $cardEffectivePercentage > 0 ? $cardEffectivePercentage . '% off' : 'Discount available' }}">
                    @if($cardEffectivePercentage > 0)
                        -{{ $cardEffectivePercentage }}%
                    @else
                        -{{ $cardDiscountLabel }}
                    @endif
                </span>
            @endif

            <button type="button"
                    class="fxh-product-card__wishlist product-action-add-wishlist {{ $cardWishlistActive ? 'is-active' : '' }}"
                    data-product-id="{{ $product->id }}"
                    aria-label="{{ $cardWishlistActive ? translate('removed_from_wishlist') : translate('add_to_wishlist') }}"
                    aria-pressed="{{ $cardWishlistActive ? 'true' : 'false' }}">
                <i class="fa {{ $cardWishlistActive ? 'fa-heart' : 'fa-heart-o' }} wishlist_icon_{{ $product->id }}" aria-hidden="true"></i>
            </button>
        </div>

        <div class="fxh-product-card__body">
            @if($cardStoreUrl)
                <a class="fxh-product-card__store" href="{{ $cardStoreUrl }}" aria-label="{{ $cardStoreName }}">{{ $cardStoreName }}</a>
            @else
                <span class="fxh-product-card__store">{{ $cardStoreName }}</span>
            @endif

            <h3 class="fxh-product-card__title">
                <a class="fxh-product-card__title-link" href="{{ route('product', $product->slug) }}">{{ $product->name }}</a>
                <span class="fxh-product-card__tooltip" role="tooltip">{{ $product->name }}</span>
            </h3>

            @if($cardHasRating)
                <div class="fxh-product-card__rating" aria-label="{{ number_format($cardAverageRating, 1) }} out of 5 from {{ $cardReviewCount }} reviews">
                    <i class="fa fa-star" aria-hidden="true"></i>
                    <span>{{ number_format($cardAverageRating, 1) }}</span>
                    <span class="fxh-product-card__review-count">({{ $cardReviewCount }})</span>
                </div>
            @endif

            <div class="fxh-product-card__price-row">
                <span class="fxh-product-card__price">{{ $cardCurrentPriceString }}</span>
                @if($cardHasDiscount)
                    <del class="fxh-product-card__old-price">{{ webCurrencyConverter(amount: $cardOriginalPrice) }}</del>
                @endif
            </div>

            <div class="fxh-product-card__action">
                @if($cardIsOutOfStock)
                    <button type="button" class="fxh-product-card__cta is-disabled" disabled aria-disabled="true">
                        {{ translate('out_of_stock') }}
                    </button>
                @elseif($cardHasVariants)
                    <a class="fxh-product-card__cta" href="{{ route('product', $product->slug) }}">
                        Choose options
                    </a>
                @elseif($cardCanAddDirectly)
                    <form id="{{ $cardId }}" class="fxh-product-card__form">
                        @csrf
                        <input type="hidden" name="id" value="{{ $product->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="product_variation_code" value="">
                        <input type="hidden" name="shipping_method_exist" value="0">
                        <input type="hidden" name="key" value="" class="product-exist-in-cart-list">
                        <button type="button" class="fxh-product-card__cta fxh-product-card__add-to-cart" data-form="#{{ $cardId }}">
                            {{ translate('add_to_cart') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </article>
@endif
