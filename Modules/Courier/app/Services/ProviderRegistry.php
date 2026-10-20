<?php

namespace Modules\Courier\app\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Models\CourierProviderSetting;
use Modules\Courier\app\Support\CountryList;
use Modules\Courier\app\ValueObjects\CourierOwner;
use Modules\Courier\CourierProviders\CourierProvider;

class ProviderRegistry
{
    protected array $registry;

    private ?CourierOwner $owner = null;

    public function __construct(
        protected Container $container,
        private readonly CourierOwnerResolver $ownerResolver,
    ) {
        $this->registry = (array) config('courier.providers', []);
    }

    public function owner(): CourierOwner
    {
        if ($this->owner === null) {
            $this->owner = $this->ownerResolver->resolve();
        }

        return $this->owner;
    }

    public function forOwner(CourierOwner $owner): static
    {
        $scoped = clone $this;
        $scoped->owner = $owner;

        return $scoped;
    }

    public function available(): array
    {
        return array_keys($this->registry);
    }

    public function has(string $name): bool
    {
        return isset($this->registry[$name]);
    }

    public function resolveByRouteKey(string $key): ?string
    {
        if ($this->has($key)) {
            return $key;
        }

        foreach ($this->registry as $id => $class) {
            if ($this->container->make($class)->webhookSlug() === $key) {
                return $id;
            }
        }

        return null;
    }

    public function labelFor(string $name): string
    {
        if (!$this->has($name)) {
            return ucfirst($name);
        }

        return $this->container->make($this->registry[$name])->getLabel();
    }

    public function isProviderEnabled(string $name): bool
    {
        return in_array($name, $this->enabled(), true);
    }

    public function enabled(): array
    {
        if (!$this->settingsAvailable()) {
            $configured = (string) config('courier.active_provider');

            if ($this->has($configured)) {
                return [$configured];
            }

            return [];
        }

        $activeIds = $this->settings()->where('is_active', true)->keys()->all();

        $enabled = [];

        foreach ($this->available() as $id) {
            if (in_array($id, $activeIds, true)) {
                $enabled[] = $id;
            }
        }

        return $enabled;
    }

    public function defaultProvider(): ?string
    {
        return $this->enabled()[0] ?? null;
    }

    public function driver(?string $name = null): CourierProvider
    {
        if ($name === null) {
            $name = $this->defaultProvider();
        }

        if ($name === null) {
            throw new CourierException('No courier provider is enabled.');
        }

        if (!$this->has($name)) {
            throw new CourierException("Courier provider [{$name}] is not registered.");
        }

        $driver = $this->container->make($this->registry[$name]);

        $setting = $this->settings()[$name] ?? null;
        $credentials = $this->credentialsFor($setting);
        $storedSettings = (array) ($setting?->settings ?? []);

        $driver->setCredentials($credentials);
        $driver->setEnvironment($storedSettings['environment'] ?? null);
        $driver->setOwner($this->owner());

        $country = $storedSettings['country'] ?? null;

        if ($country === null && $driver->legacyCountryCredential() !== null) {
            $country = $credentials[$driver->legacyCountryCredential()] ?? null;
        }

        if (filled($country)) {
            $driver->setCountry(strtoupper(trim((string) $country)));
        }

        return $driver;
    }

    public function enabledOptions(): array
    {
        $options = [];

        foreach ($this->enabled() as $id) {
            $driver = $this->driver($id);

            $deliveryTypes = [];

            foreach ($driver->deliveryTypes() as $typeId => $label) {
                $deliveryTypes[] = ['id' => (string) $typeId, 'label' => $label];
            }

            $options[] = [
                'id'              => $id,
                'label'           => $driver->getLabel(),
                'levels'          => $driver->locationLevels(),
                'level_labels'    => $driver->locationLabels(),
                'address_fields'  => $driver->addressFields(),
                'address_mode'    => $driver->addressMode(),
                'required_fields' => $driver->requiredFields(),
                'optional_fields' => $driver->optionalFields(),
                'delivery_types'  => $deliveryTypes,
            ];
        }

        return $options;
    }

    public function catalog(): array
    {
        $settings = $this->settings();
        $enabled = $this->enabled();

        $catalog = [];

        foreach ($this->available() as $id) {
            $driver = $this->driver($id);

            $setting = $settings[$id] ?? null;
            $credentials = $this->credentialsFor($setting);
            $storedCountry = (string) ($setting?->settings['country'] ?? '');

            if (!in_array($storedCountry, $driver->supportedCountries(), true)) {
                $storedCountry = '';
            }

            $countries = [];

            foreach ($driver->supportedCountries() as $code) {
                $countries[] = ['id' => $code, 'label' => (string) CountryList::nameFor($code)];
            }

            $catalog[] = [
                'id'            => $id,
                'label'         => $driver->getLabel(),
                'fields'        => $driver->credentialFields(),
                'is_enabled'    => in_array($id, $enabled, true),
                'is_configured' => $this->isConfigured($driver, $credentials),
                'credentials'   => $credentials,
                'environment'   => $driver->environment(),
                'environments'  => $driver->environments(),
                'base_urls'     => $driver->baseUrls(),
                'country'       => $storedCountry,
                'countries'     => $countries,
                'webhook_url'   => $this->webhookUrl($driver),
            ];
        }

        return $catalog;
    }

    private function webhookUrl(CourierProvider $driver): string
    {
        $segments = [trim((string) config('courier.webhook.route_prefix', 'courier/webhook'), '/'), $driver->webhookSlug()];

        if (!$this->owner()->isPlatform()) {
            $segments[] = $this->owner()->routeKey();
        }

        return url(implode('/', $segments));
    }

    private function isConfigured(CourierProvider $driver, array $credentials): bool
    {
        if (!$driver->credentialsComplete($credentials)) {
            return false;
        }

        $requiredKeys = [];

        foreach ($driver->credentialFields() as $field) {
            if (!empty($field['required'])) {
                $requiredKeys[] = $field['key'];
            }
        }

        if ($requiredKeys === []) {
            return $credentials !== [];
        }

        foreach ($requiredKeys as $key) {
            if (blank($credentials[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function credentialsFor(?CourierProviderSetting $setting): array
    {
        if ($setting === null) {
            return [];
        }

        try {
            return (array) $setting->credentials;
        } catch (DecryptException) {
            // A rotated APP_KEY or a database moved between installs leaves the row unreadable;
            // degrading to an unconfigured provider keeps the config screen usable to re-save it.
            return [];
        }
    }

    private function settings(): Collection
    {
        if (!$this->settingsAvailable()) {
            return collect();
        }

        return CourierProviderSetting::query()->forOwner($this->owner())->get()->keyBy('provider');
    }

    private function settingsAvailable(): bool
    {
        return Schema::hasTable('courier_provider_settings');
    }
}
