<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Seller;
use App\Observers\Erp\RefundWebhookObserver;
use App\Observers\Erp\VendorWebhookObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Product::observe([]);
        Seller::observe(VendorWebhookObserver::class);
        RefundRequest::observe(RefundWebhookObserver::class);
    }
}
