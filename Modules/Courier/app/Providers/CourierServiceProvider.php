<?php

namespace Modules\Courier\app\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\Contracts\CourierShipmentAccessResolver;
use Modules\Courier\app\Services\BookingOwnerAccessResolver;
use Modules\Courier\app\Services\CourierService;
use Modules\Courier\app\Services\PlatformOwnerResolver;
use Modules\Courier\app\Services\ProviderRegistry;
use Modules\Courier\app\Support\CountryList;

class CourierServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Courier';

    protected string $moduleNameLower = 'courier';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        if (!addon_published_status($this->moduleName)) {
            return;
        }

        foreach (['send-to-courier', 'delivery-partner-selection'] as $viewName) {
            View::composer("courier::admin.{$viewName}", fn ($view) => $this->composeDeliveryPartner($view, 'admin.courier.', 5));
            View::composer("courier::vendor.{$viewName}", fn ($view) => $this->composeDeliveryPartner($view, 'vendor.courier.', 4));
        }
    }

    private function composeDeliveryPartner($view, string $routePrefix, int $bootstrapVersion): void
    {
        $registry = $this->app->make(ProviderRegistry::class);
        $enabledOptions = $registry->enabledOptions();

        $view->with('courierProviders', $enabledOptions);
        $view->with('courierCountries', CountryList::options());
        $view->with('courierBs', $bootstrapVersion);

        $view->with('courierRoutes', [
            'dispatch' => $routePrefix.'dispatch',
            'revise'   => $routePrefix.'revise',
            'estimate' => $routePrefix.'estimate',
            'stores'   => $routePrefix.'locations.stores',
            'cities'   => $routePrefix.'locations.cities',
            'zones'    => $routePrefix.'locations.zones',
            'areas'    => $routePrefix.'locations.areas',
            'track'    => $routePrefix.'track',
        ]);

        $order = $view->getData()['courierOrder'] ?? null;

        $shipment = is_array($order) && isset($order['id'])
            ? $this->app->make(CourierService::class)->shipmentDetailsFor((string) $order['id'])
            : null;

        $activeProvider = $shipment['provider'] ?? null;
        $panelMayDispatch = (bool) (is_array($order) ? ($order['can_dispatch'] ?? true) : true);
        $ownedByCaller = $panelMayDispatch && (bool) ($shipment['can_manage'] ?? false);

        $selectableProviders = array_values(array_filter(
            $enabledOptions,
            static fn (array $provider): bool => $provider['id'] !== $activeProvider,
        ));

        $view->with('courierShipment', $shipment);
        $view->with('courierActiveProvider', $activeProvider);
        $view->with('courierDispatchDetails', $shipment['dispatch_details'] ?? []);
        $canSwitch = $ownedByCaller && $selectableProviders !== [];

        $view->with('courierCanSwitch', $canSwitch);
        $view->with('courierCanRevise', $ownedByCaller);
        $view->with('courierSelectableProviders', $shipment === null ? $enabledOptions : $selectableProviders);
        $view->with('courierDispatchAvailable', $shipment === null ? $panelMayDispatch : ($canSwitch || $ownedByCaller));
    }

    public function register(): void
    {
        // Addon gate: until the admin activates the addon (Addon/info.php →
        // is_published = 1), no routes register and no services bind, so
        // courierService() resolves null and every host touchpoint hides.
        // boot() still loads views/translations/migrations so activation can
        // migrate in-process. Mirror gates: AppServiceProvider::register()
        // and EventServiceProvider::boot().
        if (!addon_published_status($this->moduleName)) {
            return;
        }

        $this->app->register(RouteServiceProvider::class);
        $this->app->register(CourierEventServiceProvider::class);

        $this->app->bindIf(CourierOwnerResolver::class, PlatformOwnerResolver::class);
        $this->app->bindIf(CourierShipmentAccessResolver::class, BookingOwnerAccessResolver::class);

        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(CourierService::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower.'.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'lang'));
        }
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower.'-module-views']);
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);

        $componentNamespace = str_replace('/', '\\', config('modules.namespace').'\\'.$this->moduleName.'\\'.config('modules.paths.generator.component-class.path'));
        Blade::componentNamespace($componentNamespace, $this->moduleNameLower);
    }

    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
