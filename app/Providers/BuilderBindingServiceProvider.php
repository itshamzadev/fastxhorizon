<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class BuilderBindingServiceProvider extends ServiceProvider
{
    private const ADAPTER_PATH = 'Builder';
    private const CONTRACT_NS   = 'Modules\\Builder\\Contracts\\';
    private const ADAPTER_NS    = 'App\\Builder\\';

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->bindBuilderAdapters();
    }

    /**
     * Auto-discover host adapters in app/Builder/ and bind each one to
     * whichever Modules\Builder\Contracts\* interface it implements.
     *
     * Gated on the Builder addon-activation status, not just disk presence:
     * files may be extracted to Modules/Builder/ while the add-on is still
     * unpublished, in which case the adapters must stay dormant and the
     * module's own Null/Default providers keep serving.
     */
    private function bindBuilderAdapters(): void
    {
        if (!addon_published_status('Builder')) {
            return;
        }

        $adapterPath = app_path(self::ADAPTER_PATH);
        if (!File::isDirectory($adapterPath)) {
            return;
        }

        foreach (File::files($adapterPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = self::ADAPTER_NS . $file->getFilenameWithoutExtension();
            if (!class_exists($class)) {
                continue;
            }

            foreach (class_implements($class) ?: [] as $interface) {
                if (str_starts_with($interface, self::CONTRACT_NS)) {
                    $this->app->bind($interface, $class);
                }
            }
        }
    }
}
