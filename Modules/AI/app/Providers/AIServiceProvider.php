<?php

namespace Modules\AI\app\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\AI\app\Console\PruneChatSessions;
use Modules\AI\AIProviders\AIProviderManager;
use Modules\AI\AIProviders\ClaudeProvider;
use Modules\AI\AIProviders\OpenAIProvider;
use Modules\AI\app\Contracts\AuctionAIContract;
use Modules\AI\app\Contracts\ProductSuggestionInterface;
use Modules\AI\app\Contracts\Repositories\ChatMessageRepositoryInterface;
use Modules\AI\app\Contracts\Repositories\ChatSessionRepositoryInterface;
use Modules\AI\app\Contracts\ShoppingAssistantInterface;
use Modules\AI\app\Repositories\ChatMessageRepository;
use Modules\AI\app\Repositories\ChatSessionRepository;
use Modules\AI\app\Services\Auction\AuctionAIContentService;
use Modules\AI\app\Services\ShoppingAssistant\CartActionCollector;
use Modules\AI\app\Services\ShoppingAssistant\CartSelectionContext;
use Modules\AI\app\Services\ShoppingAssistant\ProductCollector;
use Modules\AI\app\Services\ShoppingAssistant\ProductSuggestionService;
use Modules\AI\app\Services\ShoppingAssistant\ShownProductStore;
use Modules\AI\app\Services\ShoppingAssistant\ShoppingAssistantService;

class AIServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'AI';

    protected string $moduleNameLower = 'ai';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Auction AI
        $this->app->bind(AuctionAIContract::class, AuctionAIContentService::class);

        // Repositories
        $this->app->bind(ChatSessionRepositoryInterface::class, ChatSessionRepository::class);
        $this->app->bind(ChatMessageRepositoryInterface::class, ChatMessageRepository::class);

        // Product suggestion (used by tools + kept for other consumers)
        $this->app->bind(ProductSuggestionInterface::class, ProductSuggestionService::class);

        // AIProviderManager — singleton so provider list is instantiated once per request
        $this->app->singleton(AIProviderManager::class, fn() => new AIProviderManager([
            new OpenAIProvider(),
            new ClaudeProvider(),
        ]));

        // Scoped (per-request) so tools and AgentLoopService share one instance
        // within a turn, while never leaking state across requests under Octane.
        $this->app->scoped(ProductCollector::class);
        $this->app->scoped(CartActionCollector::class);
        $this->app->scoped(ShownProductStore::class);
        $this->app->scoped(CartSelectionContext::class);

        $this->app->bind(ShoppingAssistantInterface::class, ShoppingAssistantService::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([PruneChatSessions::class]);
    }

    protected function registerCommandSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('ai:prune-chat-sessions')->daily();
        });
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

    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower.'.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);
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
