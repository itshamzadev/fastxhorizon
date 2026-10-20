<?php

namespace Modules\Courier\CourierProviders;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Courier\app\DataTransferObjects\Responses\Location;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Support\CountryList;
use Modules\Courier\app\ValueObjects\CourierOwner;
use Modules\Courier\CourierProviders\Contracts\ProvidesStores;

abstract class CourierProvider
{
    public const ENVIRONMENT_SANDBOX = 'sandbox';
    public const ENVIRONMENT_LIVE = 'live';

    private const COVERAGE_MULTINATIONAL = 'Multinational';

    protected array $credentials = [];

    protected string $environment = self::ENVIRONMENT_SANDBOX;

    protected ?string $country = null;

    protected ?CourierOwner $owner = null;

    abstract public function getName(): string;

    public function getLabel(): string
    {
        $coverage = $this->coverage();

        return $coverage === '' ? $this->displayName() : $this->displayName().' ('.$coverage.')';
    }

    protected function displayName(): string
    {
        return ucfirst($this->getName());
    }

    public function coverage(): string
    {
        $countries = $this->supportedCountries();

        if ($countries === []) {
            return '';
        }

        return count($countries) === 1 || $this->hasSelectedCountry()
            ? (string) CountryList::nameFor($this->country())
            : self::COVERAGE_MULTINATIONAL;
    }

    public function supportedCountries(): array
    {
        return [];
    }

    public function setCountry(?string $country): void
    {
        if (in_array($country, $this->supportedCountries(), true)) {
            $this->country = $country;
        }
    }

    public function country(): string
    {
        return $this->country ?? (string) ($this->supportedCountries()[0] ?? '');
    }

    protected function hasSelectedCountry(): bool
    {
        return $this->country !== null;
    }

    public function legacyCountryCredential(): ?string
    {
        return null;
    }

    public function webhookSlug(): string
    {
        return $this->getName();
    }

    public function credentialFields(): array
    {
        return [];
    }

    public function credentialsComplete(array $credentials): bool
    {
        return true;
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials = $credentials;
    }

    public function environments(): array
    {
        return [self::ENVIRONMENT_SANDBOX, self::ENVIRONMENT_LIVE];
    }

    public function setEnvironment(?string $environment): void
    {
        if (in_array($environment, $this->environments(), true)) {
            $this->environment = $environment;
        }
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function setOwner(CourierOwner $owner): void
    {
        $this->owner = $owner;
    }

    public function owner(): CourierOwner
    {
        return $this->owner ??= CourierOwner::platform();
    }

    public function baseUrls(): array
    {
        return [];
    }

    public function locationLevels(): array
    {
        return ['city', 'zone', 'area'];
    }

    public function locationLabels(): array
    {
        return [];
    }

    public function deliveryTypes(): array
    {
        return [];
    }

    public function addressFields(): array
    {
        return [];
    }

    public function addressMode(): string
    {
        return 'catalog';
    }

    public function requiredFields(): array
    {
        $destination = match ($this->addressMode()) {
            'postal' => ['country_code', 'postal_code', 'city_name'],
            'geo'    => ['latitude', 'longitude'],
            'branch' => ['source_branch', 'destination_branch'],
            default  => array_map(
                static fn (string $level): string => $level.'_id',
                $this->locationLevels(),
            ),
        };

        return array_merge(['recipient_name', 'recipient_phone', 'recipient_address', 'weight', 'cod_amount'], $destination);
    }

    public function optionalFields(): array
    {
        $pickup = $this instanceof ProvidesStores ? ['store_id'] : [];

        $destination = match ($this->addressMode()) {
            'postal' => ['state_province'],
            'branch' => ['source_branch_id', 'destination_branch_id'],
            default  => [],
        };

        $service = $this->deliveryTypes() === [] ? [] : ['delivery_type'];

        return array_merge($pickup, $destination, $service, ['quantity', 'item_description', 'note', 'order_value']);
    }

    public function webhookAcknowledgement(): array
    {
        return ['status' => 200, 'headers' => [], 'body' => ''];
    }

    protected function storeLocations(array $rows, string $idKey, string $nameKey, string $addressKey): array
    {
        $stores = [];

        foreach ($rows as $row) {
            $name = Location::normalizeName((string) ($row[$nameKey] ?? ''));

            if ($this->isProbeStoreName($name)) {
                continue;
            }

            $stores[] = new Location(
                id: (string) $row[$idKey],
                name: $this->storeLabel($name, $row[$addressKey] ?? null),
                level: 'store',
                raw: $row,
            );
        }

        return $stores;
    }

    // A carrier's store list is merchant-authored, and shared sandbox accounts accumulate
    // injection-probe records left behind by other integrators — {{7*7}}, {{config}},
    // test$(id), test`id`. The carrier cannot tell them from real pickup points, so they are
    // dropped on the shape of the name: no merchant names a store with template or shell syntax.
    private function isProbeStoreName(string $name): bool
    {
        foreach (['{{', '}}', '${', '$(', '`', '|', '<', '>'] as $marker) {
            if (str_contains($name, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function storeLabel(string $name, ?string $address): string
    {
        $parts = array_filter(
            [$name, Location::normalizeName((string) $address)],
            static fn (string $part): bool => $part !== '',
        );

        return $parts === [] ? 'Store' : implode(' — ', $parts);
    }

    protected function baseUrl(): string
    {
        $urls = $this->baseUrls();

        if (isset($urls[$this->environment])) {
            return $urls[$this->environment];
        }

        return $urls[self::ENVIRONMENT_SANDBOX] ?? (string) (reset($urls) ?: '');
    }

    protected function defaultHeaders(): array
    {
        return [];
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout((int) config('courier.http.timeout', 30))
            ->retry((int) config('courier.http.retries', 0), 200)
            ->withHeaders($this->defaultHeaders())
            ->acceptJson();
    }

    protected function failFromResponse(Response $response): never
    {
        $reasons = [];
        $details = $response->json('validation_errors') ?? $response->json('errors');

        if (!empty($details)) {
            array_walk_recursive($details, static function ($value) use (&$reasons) {
                if (is_string($value) && $value !== '') {
                    $reasons[] = $value;
                }
            });
        }

        $message = $reasons !== []
            ? implode('; ', array_unique($reasons))
            : (string) $response->json('message', $response->body());

        throw new CourierException($message !== '' ? $message : 'Courier request failed.');
    }
}
