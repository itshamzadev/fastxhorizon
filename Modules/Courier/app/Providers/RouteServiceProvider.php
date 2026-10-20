<?php

namespace Modules\Courier\app\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\Courier\app\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapWebhookRoutes();
        $this->mapSellerApiRoutes();
        $this->mapAdminRoutes();
        $this->mapVendorRoutes();
    }

    protected function mapSellerApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Courier', '/routes/seller_api.php'));
    }

    protected function mapWebhookRoutes(): void
    {
        Route::middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Courier', '/routes/webhook.php'));
    }

    protected function mapAdminRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Courier', '/routes/admin.php'));
    }

    protected function mapVendorRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Courier', '/routes/vendor.php'));
    }
}
