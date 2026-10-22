<?php

namespace App\Http\Controllers\Web;

use App\Traits\CacheManagerTrait;
use App\Traits\EmailTemplateTrait;
use App\Traits\InHouseTrait;
use App\Utils\BrandManager;
use App\Utils\CategoryManager;
use App\Utils\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DealOfTheDay;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Review;
use App\Services\ProductRecommendationService;
use App\Utils\ProductManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Auction\app\Enums\AuctionStatus;

class HomeController extends Controller
{
    use InHouseTrait, EmailTemplateTrait;
    use CacheManagerTrait;

    public function __construct(
        private readonly Product      $product,
        private readonly Order        $order,
        private readonly OrderDetail  $orderDetails,
        private readonly Category     $category,
        private readonly Seller       $seller,
        private readonly Review       $review,
        private readonly DealOfTheDay $dealOfTheDay,
        private readonly Banner       $banner,
    )
    {
    }


    public function index(): View
    {
        $themeName = theme_root_path();

        // Keep the complete storefront, account, seller, admin and API
        // application available. Only the public landing page is temporarily
        // replaced while the store is being prepared for launch.
        return view($themeName === 'theme_aster'
            ? 'theme-views.coming-soon'
            : 'web-views.coming-soon');
    }

    public function outOfStock(): View
    {
        $products = $this->product->active()
            ->unavailablePhysical()
            ->with(['seller.shop', 'shop', 'reviews'])
            ->latest('id')
            ->paginate(24);

        return view('web-views.pages.out-of-stock', compact('products'));
    }

    public function default_theme(): View
    {
        $brands = $this->cachePriorityWiseBrandList();
        $homeCategories = $this->cacheHomeCategoriesList();
        $topRatedProducts = $this->cacheTopRatedProductList();
        $latestProductsList = $this->cacheHomePageLatestProductList()->take(8);
        $bestSellProduct = $this->cacheBestSellProductList();
        $recommendedProduct = $this->cacheHomePageRandomSingleProductItem();
        $bannerTypeMainBanner = $this->cacheBannerForTypeMainBanner();
        $bannerTypeMainSectionBanner = $this->cacheBannerTable(bannerType: 'Main Section Banner');
        $topVendorsList = ProductManager::getPriorityWiseTopVendorQuery($this->cacheHomePageTopVendorsList());
        $bannerTypeFooterBanner = $this->cacheBannerTable(bannerType: 'Footer Banner', dataLimit: 10);
        $clearanceSaleProducts = $this->cacheHomePageClearanceSaleProducts();

        $categorySetup = getHomepageCommerceConfig('categories');
        $categories = $categorySetup['enabled']
            ? CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataForm: 'home_page')?->take(max(12, (int) $categorySetup['product_limit']))
            : collect();
        $userId = Auth::guard('customer')->user() ? Auth::guard('customer')->id() : 0;
        $dealSetup = getHomepageCommerceConfig('deals');
        $flashDeal = $dealSetup['enabled']
            ? ProductManager::getPriorityWiseFlashDealsProductsQuery(userId: $userId, dataLimit: max(1, (int) $dealSetup['product_limit']))
            : ['flashDeal' => null, 'flashDealProducts' => collect()];
        if (!$flashDeal['flashDeal'] || collect($flashDeal['flashDealProducts'] ?? [])->isEmpty()) {
            $flashDeal = ['flashDeal' => null, 'flashDealProducts' => collect()];
        }
        $current_date = date('Y-m-d H:i:s');

        $filterPurchasable = function ($items) {
            $keep = fn ($product) => $product instanceof Product && $product->isPurchasable();
            if ($items instanceof IlluminateContractsPaginationLengthAwarePaginator) {
                $items->setCollection($items->getCollection()->filter($keep)->values());
                return $items;
            }
            return collect($items ?? [])->filter($keep)->values();
        };

        $topRatedProducts = $filterPurchasable($topRatedProducts);
        $latestProductsList = $filterPurchasable($latestProductsList);
        $bestSellProduct = $filterPurchasable($bestSellProduct);

        $bestSellProduct = $bestSellProduct->count() == 0 ? $latestProductsList : $bestSellProduct;
        $topRatedProducts = $topRatedProducts->count() == 0 ? $bestSellProduct : $topRatedProducts;

        $featuredSetup = getHomepageCommerceConfig('featured');
        $featuredProductsList = $featuredSetup['enabled']
            ? ProductManager::getPriorityWiseFeaturedProductsQuery(query: $this->product->active()->publiclyAvailable()->with(['seller.shop', 'shop', 'flashDealProducts.flashDeal', 'clearanceSale' => function ($query) {
            return $query->active();
        }]), dataLimit: max(1, (int) $featuredSetup['product_limit']))
            : collect();
        $newArrivalProducts = ProductManager::getPriorityWiseNewArrivalProductsQuery(query: $this->product->active()->with(['seller.shop', 'shop', 'clearanceSale' => function ($query) {
            return $query->active();
        }]), dataLimit: 8);

        $dealOfTheDay = DealOfTheDay::with(['product' => function ($query) {
            return $query->active()->with(['seller.shop', 'shop', 'clearanceSale' => function ($query) {
                return $query->active();
            }]);
        }])
            ->join('products', 'products.id', '=', 'deal_of_the_days.product_id')
            ->select('deal_of_the_days.*', 'products.unit_price')
            ->where('products.status', 1)
            ->where('deal_of_the_days.status', 1)
            ->first();

        $featuredProductsList = $filterPurchasable($featuredProductsList);
        $newArrivalProducts = $filterPurchasable($newArrivalProducts);
        $clearanceSaleProducts = $filterPurchasable($clearanceSaleProducts);
        $flashDeal['flashDealProducts'] = $filterPurchasable($flashDeal['flashDealProducts'] ?? []);
        if (collect($flashDeal['flashDealProducts'])->isEmpty()) {
            $flashDeal = ['flashDeal' => null, 'flashDealProducts' => collect()];
        }
        $bestSellProduct = $bestSellProduct->count() === 0 ? $latestProductsList : $bestSellProduct;
        $topRatedProducts = $topRatedProducts->count() === 0 ? $bestSellProduct : $topRatedProducts;
        if ($dealOfTheDay?->product && ($dealOfTheDay->product->product_type ?? 'physical') === 'physical'
            && !$dealOfTheDay->product->isPurchasable()) {
            $dealOfTheDay->setRelation('product', null);
        }

        if ($recommendedProduct
            && ($recommendedProduct->product_type ?? 'physical') === 'physical'
            && !$recommendedProduct->isPurchasable()) {
            $recommendedProduct = null;
        }

        $robotsMetaContentData = \App\Models\RobotsMetaContent::where('page_name', 'default')->first();

        $auctionProducts = getWebConfig(name: 'auction_feature_status') ? $this->getHomePageAuctionProductsList() : null;
        if ($auctionProducts && auth('customer')->check()) {
            $auctionProducts->load(['myBid', 'myParticipation']);
        }

        $recommendation = app(ProductRecommendationService::class);
        if ($recommendation->isActive()) {
            $flashDealProducts = collect($flashDeal['flashDealProducts'] ?? []);
            $recommendation->prime(array_merge(
                $featuredProductsList->pluck('id')->all(),
                $topRatedProducts->pluck('id')->all(),
                $bestSellProduct->pluck('id')->all(),
                $newArrivalProducts->pluck('id')->all(),
                $latestProductsList->pluck('id')->all(),
                $clearanceSaleProducts->pluck('id')->all(),
                $flashDealProducts->pluck('id')->all(),
                getFeaturedDealsProductList()->pluck('id')->all(),
                $homeCategories->flatMap(fn($category) => collect($category['products'] ?? [])->pluck('id'))->all(),
            ));
            $featuredProductsList = $recommendation->reorder($featuredProductsList);
            $topRatedProducts = $recommendation->reorder($topRatedProducts);
            $bestSellProduct = $recommendation->reorder($bestSellProduct);
            $newArrivalProducts = $recommendation->reorder($newArrivalProducts);
            $latestProductsList = $recommendation->reorder($latestProductsList);
            $clearanceSaleProducts = $recommendation->reorder($clearanceSaleProducts);
            if ($flashDealProducts->isNotEmpty()) {
                $flashDeal['flashDealProducts'] = $recommendation->reorder($flashDealProducts);
            }
            $this->personalizeCategoryProducts($homeCategories, $recommendation);
        }

        return view(VIEW_FILE_NAMES['home'],
            compact(
                'flashDeal', 'featuredProductsList', 'topRatedProducts', 'bestSellProduct', 'latestProductsList', 'categories', 'brands',
                'dealOfTheDay', 'topVendorsList', 'homeCategories', 'bannerTypeMainBanner', 'bannerTypeMainSectionBanner',
                'current_date', 'recommendedProduct', 'bannerTypeFooterBanner', 'newArrivalProducts', 'clearanceSaleProducts', 'robotsMetaContentData', 'auctionProducts', 'featuredSetup'
            )
        );
    }

    public function theme_aster(): View
    {
        $moreVendors = $this->cacheHomePageMoreVendorsList();
        $homeCategories = $this->cacheHomeCategoriesList();
        $latestProductsList = $this->cacheHomePageLatestProductList();
        $randomSingleProduct = $this->cacheHomePageRandomSingleProductItem();
        $topVendorsList = ProductManager::getPriorityWiseTopVendorQuery(query: $this->cacheHomePageTopVendorsList());
        $clearanceSaleProducts = $this->cacheHomePageClearanceSaleProducts();

        $categories = CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataLimit: 11);
        $userId = Auth::guard('customer')->user() ? Auth::guard('customer')->id() : 0;
        $flashDeal = ProductManager::getPriorityWiseFlashDealsProductsQuery(userId: $userId);
        $current_date = date('Y-m-d H:i:s');

        $findWhatYouNeedCategoriesData = Cache::remember(FIND_WHAT_YOU_NEED_CATEGORIES_LIST, CACHE_FOR_3_HOURS, function () {
            return $this->category->where('parent_id', 0)
                ->with(['childes' => function ($query) {
                    $query->with(['subCategoryProduct' => function ($query) {
                        return $query->active();
                    }]);
                }])
                ->with(['product' => function ($query) {
                    return $query->active()->withCount(['orderDetails']);
                }])
                ->get();
        });

        $findWhatYouNeedCategoriesData = CategoryManager::getPriorityWiseCategorySortQuery(query: $findWhatYouNeedCategoriesData);

        $findWhatYouNeedCategoriesData->map(function ($category) {
            $category->product_count = $category->product->count();
            unset($category->product);
            $category->childes?->map(function ($sub_category) {
                $sub_category->subCategoryProduct_count = $sub_category->subCategoryProduct->count();
                unset($sub_category->subCategoryProduct);
            });
            return $category;
        });
        $findWhatYouNeedCategories = $findWhatYouNeedCategoriesData->toArray();

        $getCategories = [];
        foreach ($findWhatYouNeedCategories as $category) {
            $slice = array_slice($category['childes'], 0, 4);
            $category['childes'] = $slice;
            $getCategories[] = $category;
        }

        $final_category = [];
        foreach ($getCategories as $category) {
            if (count($category['childes']) > 0) {
                $final_category[] = $category;
            }
        }
        $category_slider = array_chunk($final_category, 4);

        $featuredProductsList = $this->product->active()->with(['seller.shop', 'shop', 'flashDealProducts.flashDeal', 'clearanceSale' => function ($query) {
                return $query->active();
            }])
            ->where('featured', 1)
            ->withCount(['orderDetails']);
        $featuredProductsList = ProductManager::getPriorityWiseFeaturedProductsQuery(query: $featuredProductsList, dataLimit: 10);

        $featuredProductsList?->map(function ($product) use ($current_date) {
            $flashDealStatus = 0;
            $flash_deal_end_date = 0;
            if (count($product->flashDealProducts) > 0) {
                $flash_deal = $product->flashDealProducts[0]->flashDeal;
                if ($flash_deal) {
                    $start_date = date('Y-m-d H:i:s', strtotime($flash_deal->start_date));
                    $end_date = date('Y-m-d H:i:s', strtotime($flash_deal->end_date));
                    $flashDealStatus = $flash_deal->status == 1 && (($current_date >= $start_date) && ($current_date <= $end_date)) ? 1 : 0;
                    $flash_deal_end_date = $flash_deal->end_date;
                }
            }
            $product['flash_deal_status'] = $flashDealStatus;
            $product['flash_deal_end_date'] = $flash_deal_end_date;
            return $product;
        });
        $bestSellProduct = Product::active()->with([
            'reviews', 'rating', 'seller.shop',
            'flashDealProducts.flashDeal',
        ])->withCount(['reviews']);

        $orderDetails = Cache::remember(CACHE_ORDER_DETAILS_TABLE, CACHE_FOR_3_HOURS, function () {
            return OrderDetail::with('product')
                ->select('product_id', DB::raw('COUNT(product_id) as count'))
                ->groupBy('product_id')
                ->get();
        });

        $getOrderedProductIds = [];
        foreach ($orderDetails as $detail) {
            $getOrderedProductIds[] = $detail['product_id'];
        }
        $bestSellProduct = ProductManager::getPriorityWiseBestSellingProductsQuery(query: $bestSellProduct->whereIn('id', $getOrderedProductIds), dataLimit: 10);

        $bestSellProduct?->map(function ($product) use ($current_date) {
            $flashDealStatus = 0;
            $flashDealEndDate = 0;
            if (isset($product->flashDealProducts) && count($product->flashDealProducts) > 0) {
                $flashDeal = $product->flashDealProducts[0]->flashDeal;
                if ($flashDeal) {
                    $startDate = date('Y-m-d H:i:s', strtotime($flashDeal->start_date));
                    $endDate = date('Y-m-d H:i:s', strtotime($flashDeal->end_date));
                    $flashDealStatus = $flashDeal->status == 1 && (($current_date >= $startDate) && ($current_date <= $endDate)) ? 1 : 0;
                    $flashDealEndDate = $flashDeal->end_date;
                }
            }
            $product['flash_deal_status'] = $flashDealStatus;
            $product['flash_deal_end_date'] = $flashDealEndDate;
            return $product;
        });

        $justForYouProducts = $this->cacheHomePageJustForYouProductList();
        if (auth('customer')->check()) {
            $orders = $this->order->where(['customer_id' => auth('customer')->id()])->with(['details'])->get();

            if ($orders) {
                $orders = $orders?->map(function ($order) {
                    $orderDetails = $order->details->map(function ($detail) {
                        $product = json_decode($detail?->product_details ?? '') ?? null;
                        $category = $product?->category_ids ? json_decode($product?->category_ids)[0]->id : null;
                        if ($category) {
                            $detail['category_id'] = $category;
                        }
                        return $detail;
                    });
                    try {
                        $order['id'] = $orderDetails[0]->id;
                        $order['category_id'] = $orderDetails[0]?->category_id ?? null;
                    } catch (\Throwable $th) {

                    }

                    return $order;
                });

                $orderCategories = [];
                foreach ($orders as $order) {
                    if ($order['category_id']) {
                        $orderCategories[] = $order['category_id'];
                    }
                }
                $ids = array_unique($orderCategories);

                $justForYouProducts = $this->product->active()->with(['reviews' => fn($q) => $q->active()])
                    ->where(function ($query) use ($ids) {
                        foreach ($ids as $id) {
                            $query->orWhere('category_ids', 'like', "%{$id}%");
                        }
                    })->inRandomOrder()->take(8)->get()->each(function ($product) {
                        $product->reviews_count = $product->reviews->count();
                        $product->average_rating = $product->reviews->avg('rating');
                        $product->total_rating   = $product->reviews->sum('rating');
                    });
            }
        }

        $topRatedProducts = $this->cacheTopRatedProductList();

        if ($bestSellProduct->count() == 0) {
            $bestSellProduct = $latestProductsList;
        }

        if ($topRatedProducts->count() == 0) {
            $topRatedProducts = $bestSellProduct;
        }
        $dealOfTheDay = $this->dealOfTheDay->with(['product' => function ($query) {
            return $query->active()->with(['clearanceSale' => function ($query) {
                return $query->active();
            }]);
        }])
            ->join('products', 'products.id', '=', 'deal_of_the_days.product_id')
            ->select('deal_of_the_days.*', 'products.unit_price')
            ->where('products.status', 1)
            ->where('deal_of_the_days.status', 1)
            ->first();
        $recommendedProduct = $this->cacheHomePageRandomSingleProductItem();

        $banners = $this->cacheBannerTable();
        $bannerTypeMainBanner = [];
        $bannerTypeFooterBanner = [];
        $bannerTypeSidebarBanner = [];
        $bannerTypeMainSectionBanner = [];
        $bannerTypeTopSideBanner = [];

        foreach ($banners as $banner) {
            $banner['photo_full_url'] = $banner->photo_full_url;
            if ($banner->banner_type == 'Main Banner') {
                $bannerTypeMainBanner[] = $banner;
            } elseif ($banner->banner_type == 'Footer Banner') {
                $bannerTypeFooterBanner[] = $banner->toArray();
            } elseif ($banner->banner_type == 'Sidebar Banner') {
                $bannerTypeSidebarBanner[] = $banner;
            } elseif ($banner->banner_type == 'Main Section Banner') {
                $bannerTypeMainSectionBanner[] = $banner;
            } elseif ($banner->banner_type == 'Top Side Banner') {
                $bannerTypeTopSideBanner[] = $banner;
            }
        }

        $bannerTypeSidebarBanner = $bannerTypeSidebarBanner ? $bannerTypeSidebarBanner[0] : [];
        $bannerTypeMainSectionBanner = $bannerTypeMainSectionBanner ? $bannerTypeMainSectionBanner[0] : [];
        $bannerTypeTopSideBanner = $bannerTypeTopSideBanner ? $bannerTypeTopSideBanner[0] : [];
        $bannerTypeFooterBanner = $bannerTypeFooterBanner ? array_slice($bannerTypeFooterBanner, 0, 2) : [];

        $decimal_point = getWebConfig(name: 'decimal_point_settings');
        $decimal_point_settings = !empty($decimal_point) ? $decimal_point : 0;
        $user = Helpers::getCustomerInformation();

        $order_again = $user != 'offline' ?
            $this->order->with('details.product')->where(['order_status' => 'delivered', 'customer_id' => $user->id])->latest()->take(8)->get()
            : [];

        $random_coupon = Coupon::with('seller')
            ->where(['status' => 1])
            ->whereDate('start_date', '<=', date('Y-m-d'))
            ->whereDate('expire_date', '>=', date('Y-m-d'))
            ->inRandomOrder()->take(3)->get();

        $topVendorsListSectionShowingStatus = false;
        foreach ($topVendorsList as $vendorList) {
            if ($vendorList->products_count > 0) {
                $topVendorsListSectionShowingStatus = true;
                break;
            }
        }

        $robotsMetaContentData = \App\Models\RobotsMetaContent::where('page_name', 'default')->first();
        $auctionProducts = getWebConfig(name: 'auction_feature_status') ? $this->getHomePageAuctionProductsList() : null;
        if ($auctionProducts && auth('customer')->check()) {
            $auctionProducts->load(['myBid', 'myParticipation']);
        }

        $recommendation = app(ProductRecommendationService::class);
        if ($recommendation->isActive()) {
            $flashDealProducts = collect($flashDeal['flashDealProducts'] ?? []);
            $recommendation->prime(array_merge(
                $featuredProductsList->pluck('id')->all(),
                $topRatedProducts->pluck('id')->all(),
                $bestSellProduct->pluck('id')->all(),
                $latestProductsList->pluck('id')->all(),
                $clearanceSaleProducts->pluck('id')->all(),
                $flashDealProducts->pluck('id')->all(),
                getFeaturedDealsProductList()->pluck('id')->all(),
                $homeCategories->flatMap(fn($category) => collect($category['products'] ?? [])->pluck('id'))->all(),
            ));
            $featuredProductsList = $recommendation->reorder($featuredProductsList);
            $topRatedProducts = $recommendation->reorder($topRatedProducts);
            $bestSellProduct = $recommendation->reorder($bestSellProduct);
            $latestProductsList = $recommendation->reorder($latestProductsList);
            $clearanceSaleProducts = $recommendation->reorder($clearanceSaleProducts);
            if ($flashDealProducts->isNotEmpty()) {
                $flashDeal['flashDealProducts'] = $recommendation->reorder($flashDealProducts);
            }
            $this->personalizeCategoryProducts($homeCategories, $recommendation);
        }

        return view(VIEW_FILE_NAMES['home'],
            compact(
                'flashDeal', 'topRatedProducts', 'bestSellProduct', 'latestProductsList', 'featuredProductsList', 'dealOfTheDay', 'topVendorsList',
                'homeCategories', 'bannerTypeMainBanner', 'bannerTypeFooterBanner', 'randomSingleProduct', 'decimal_point_settings', 'justForYouProducts', 'moreVendors',
                'final_category', 'category_slider', 'order_again', 'bannerTypeSidebarBanner', 'bannerTypeMainSectionBanner', 'random_coupon', 'bannerTypeTopSideBanner',
                'categories', 'topVendorsListSectionShowingStatus', 'clearanceSaleProducts', 'recommendedProduct', 'robotsMetaContentData', 'auctionProducts'
            )
        );
    }

    /**
     * Reorder each home category's products in place using the personalization layer.
     * Keeps both the array accessor ($category['products']) and the 'product' relation in sync.
     */
    private function personalizeCategoryProducts($homeCategories, ProductRecommendationService $recommendation): void
    {
        $homeCategories?->each(function ($category) use ($recommendation) {
            if (!empty($category['products'])) {
                $reordered = $recommendation->reorder(collect($category['products']));
                $category['products'] = $reordered;
                $category->setRelation('product', $reordered);
            }
        });
    }

    public function getHomePageAuctionProductsList()
    {
        if (!getCheckAddonPublishedStatus(moduleName: 'Auction')) {
            return null;
        }
        $cacheKey = CACHE_HOME_PAGE_AUCTION_PRODUCTS_LIST . '_' . (getDefaultLanguage() ?? 'en');
        $cacheKeys = Cache::get(CACHE_HOME_PAGE_AUCTION_PRODUCTS_KEYS, []);
        if (!in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            Cache::put(CACHE_HOME_PAGE_AUCTION_PRODUCTS_KEYS, $cacheKeys, CACHE_FOR_3_HOURS);
        }

        return Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () {
            return \Modules\Auction\app\Models\AuctionProduct::active()
                ->whereAuctionCurrentStatus([AuctionStatus::LIVE, AuctionStatus::UPCOMING])
                ->orderByDesc('id')
                ->take(8)
                ->get();
        });
    }

}
