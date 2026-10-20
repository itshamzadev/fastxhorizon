<?php

namespace App\Providers;

use Kreait\Firebase\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            $configured = config('firebase.credentials');

            // Keep the legacy database setting as a fallback, but only read
            // it when Firebase is actually resolved by a Firebase feature.
            if (blank($configured)) {
                $configured = getWebConfig('push_notification_key');
            }

            if (is_array($configured)) {
                return (new Factory)->withServiceAccount($configured);
            }

            $configured = is_string($configured) ? trim($configured) : '';
            $placeholders = array_map('strtolower', config('firebase.placeholder_values', []));

            if ($configured === '' || in_array(strtolower($configured), $placeholders, true)) {
                throw new \RuntimeException('Firebase credentials are not configured.');
            }

            if (is_file($configured)) {
                return (new Factory)->withServiceAccount($configured);
            }

            $credentials = json_decode($configured, true);
            if (is_array($credentials) && isset($credentials['project_id'], $credentials['client_email'], $credentials['private_key'])) {
                return (new Factory)->withServiceAccount($credentials);
            }

            throw new \RuntimeException('Firebase credentials must be a valid service-account file or JSON document.');
        });

        $this->app->singleton(Auth::class, function ($app) {
            return $app->make(Factory::class)->createAuth();
        });

        $this->app->singleton(Messaging::class, function ($app) {
            return $app->make(Factory::class)->createMessaging();
        });

        // Optionally, you can bind it to a simpler alias
        $this->app->alias(Messaging::class, 'firebase.messaging');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
