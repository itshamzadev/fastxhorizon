<?php

namespace Modules\Courier\Tests\Unit;

use App\Services\Courier\HostCourierOwnerResolver;
use Illuminate\Encryption\Encrypter;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Courier\CourierProviders\CourierProvider;
use Modules\Courier\app\Contracts\CourierOwnerResolver;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Http\Requests\SaveCourierProviderRequest;
use Modules\Courier\app\Models\CourierProviderSetting;
use Modules\Courier\app\Services\CourierConfigService;
use Modules\Courier\app\Services\ProviderRegistry;
use Modules\Courier\app\ValueObjects\CourierOwner;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Modules\Courier\Tests\CourierTestCase;

#[RunTestsInSeparateProcesses]
class CourierConfigPersistenceTest extends CourierTestCase
{
    private const VENDOR_ID = 77;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('courier_provider_settings');
        (require base_path('Modules/Courier/database/migrations/2026_07_19_100001_create_courier_provider_settings_table.php'))->up();
        (require base_path('Modules/Courier/database/migrations/2026_07_29_100006_add_owner_to_courier_provider_settings_table.php'))->up();
    }

    public function test_an_admin_panel_route_owns_the_platform_row_even_while_a_vendor_is_signed_in(): void
    {
        $this->signInSeller();

        $this->onRoute('admin.courier.config.update', function (): void {
            $this->assertTrue($this->hostResolver()->resolve()->isPlatform());
        });
    }

    public function test_a_vendor_panel_route_owns_that_vendors_row(): void
    {
        $this->signInSeller();

        $this->onRoute('vendor.courier.config.update', function (): void {
            $owner = $this->hostResolver()->resolve();

            $this->assertFalse($owner->isPlatform());
            $this->assertSame(self::VENDOR_ID, $owner->id);
        });
    }

    public function test_a_vendor_route_without_a_signed_in_vendor_refuses_rather_than_falling_back(): void
    {
        $this->onRoute('vendor.courier.config.update', function (): void {
            $this->expectException(CourierException::class);

            $this->hostResolver()->resolve();
        });
    }

    public function test_a_seller_api_request_owns_the_token_holders_row(): void
    {
        $seller = new \App\Models\Seller();
        $seller->id = self::VENDOR_ID;

        $this->app['request']['seller'] = $seller;

        $owner = $this->hostResolver()->resolve();

        $this->assertFalse($owner->isPlatform());
        $this->assertSame(self::VENDOR_ID, $owner->id);
    }

    public function test_every_courier_route_resolves_the_owner_its_panel_implies(): void
    {
        $this->signInSeller();

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (!str_contains($name, 'courier.')) {
                continue;
            }

            $this->onRoute($name, function () use ($name): void {
                $this->assertSame(
                    !str_starts_with($name, 'vendor.'),
                    $this->hostResolver()->resolve()->isPlatform(),
                    "Route [{$name}] resolves the wrong owner for its panel.",
                );
            });
        }
    }

    public function test_every_provider_round_trips_for_the_platform_owner(): void
    {
        $this->assertEveryProviderRoundTrips(CourierOwner::platform());
    }

    public function test_every_provider_round_trips_for_a_vendor_owner(): void
    {
        $this->assertEveryProviderRoundTrips(CourierOwner::vendor(self::VENDOR_ID));
    }

    public function test_a_vendor_sees_and_writes_only_their_own_configuration(): void
    {
        $platform = CourierOwner::platform();
        $vendor = CourierOwner::vendor(self::VENDOR_ID);
        $otherVendor = CourierOwner::vendor(self::VENDOR_ID + 1);

        $this->saveAs($platform, $this->payloadFor('redx', ['api_access_token' => 'platform-token']));
        $this->saveAs($vendor, $this->payloadFor('redx', ['api_access_token' => 'vendor-token']));

        $this->assertSame('platform-token', $this->credentialsOf($platform, 'redx')['api_access_token']);
        $this->assertSame('vendor-token', $this->credentialsOf($vendor, 'redx')['api_access_token']);

        $this->assertNull($this->rowOf($otherVendor, 'redx'));
        $this->assertSame([], $this->registryFor($otherVendor)->enabled());
    }

    public function test_blank_required_credentials_are_rejected_and_nothing_is_stored(): void
    {
        $owner = CourierOwner::platform();

        $errors = $this->errorsFrom($owner, [
            'provider'    => 'pathao',
            'is_enabled'  => '1',
            'credentials' => ['client_id' => null, 'client_secret' => null, 'username' => null, 'password' => null],
        ]);

        $this->assertArrayHasKey('credentials.client_id', $errors);
        $this->assertNull($this->rowOf($owner, 'pathao'));
    }

    public function test_a_select_value_from_another_country_is_rejected(): void
    {
        $owner = CourierOwner::platform();

        $bangladesh = $this->payloadFor('lalamove', ['language' => 'bn_BD', 'default_service_type' => 'TRUCK']);
        $this->saveAs($owner, $bangladesh);

        $errors = $this->errorsFrom($owner, [...$bangladesh, 'country' => 'HK']);

        $this->assertArrayHasKey('credentials.language', $errors);
        $this->assertArrayHasKey('credentials.default_service_type', $errors);
        $this->assertSame('BD', $this->settingsOf($owner, 'lalamove')['country']);
    }

    public function test_a_status_toggle_keeps_the_credentials_environment_and_country(): void
    {
        $owner = CourierOwner::platform();

        $this->saveAs($owner, $this->payloadFor('lalamove'));
        $before = $this->credentialsOf($owner, 'lalamove');

        $this->saveAs($owner, ['provider' => 'lalamove', 'is_enabled' => '0']);

        $this->assertFalse($this->rowOf($owner, 'lalamove')->is_active);
        $this->assertSame($before, $this->credentialsOf($owner, 'lalamove'));
        $this->assertSame(['environment' => 'sandbox', 'country' => 'BD'], $this->settingsOf($owner, 'lalamove'));
    }

    public function test_credentials_encrypted_under_another_app_key_leave_the_provider_unconfigured_instead_of_breaking_the_screen(): void
    {
        $owner = CourierOwner::platform();

        $this->saveAs($owner, $this->payloadFor('redx'));
        $this->saveAs($owner, $this->payloadFor('pathao'));

        $this->reEncryptUnderAForeignKey($owner, 'redx');

        $entry = $this->catalogEntry($owner, 'redx');

        $this->assertSame([], $entry['credentials']);
        $this->assertFalse($entry['is_configured']);
        $this->assertSame('redx', $this->registryFor($owner)->driver('redx')->getName());
        $this->assertTrue($this->catalogEntry($owner, 'pathao')['is_configured']);
    }

    private function reEncryptUnderAForeignKey(CourierOwner $owner, string $provider): void
    {
        $cipher = config('app.cipher');
        $foreign = new Encrypter(Encrypter::generateKey($cipher), $cipher);

        CourierProviderSetting::query()
            ->forOwner($owner)
            ->where('provider', $provider)
            ->toBase()
            ->update(['credentials' => $foreign->encryptString(json_encode(['api_token' => 'unreadable']))]);
    }

    private function assertEveryProviderRoundTrips(CourierOwner $owner): void
    {
        $otherOwner = $owner->isPlatform() ? CourierOwner::vendor(self::VENDOR_ID) : CourierOwner::platform();

        foreach (array_keys($this->providers()) as $id) {
            $payload = $this->payloadFor($id);

            $this->saveAs($owner, $payload);

            $stored = $this->credentialsOf($owner, $id);

            foreach ($payload['credentials'] as $key => $value) {
                $this->assertSame($value, $stored[$key] ?? null, "[{$id}] did not round-trip credential [{$key}].");
            }

            $settings = $this->settingsOf($owner, $id);
            $this->assertTrue($this->rowOf($owner, $id)->is_active, "[{$id}] was not enabled.");
            $this->assertSame($payload['environment'] ?? null, $settings['environment'] ?? null, "[{$id}] lost its environment.");
            $this->assertSame($payload['country'] ?? null, $settings['country'] ?? null, "[{$id}] lost its country.");
            $this->assertTrue($this->catalogEntry($owner, $id)['is_configured'], "[{$id}] is not reported as configured.");
            $this->assertNull($this->rowOf($otherOwner, $id), "[{$id}] wrote a row for the other owner.");
        }
    }

    private function payloadFor(string $id, array $credentialOverrides = []): array
    {
        $driver = $this->driver($id);
        $countries = $driver->supportedCountries();
        $driver->setCountry($countries[0] ?? null);

        $credentials = [];

        foreach ($driver->credentialFields() as $field) {
            $credentials[$field['key']] = ($field['type'] ?? 'text') === 'select'
                ? (string) ($field['options'][0]['id'] ?? '')
                : 'value-for-'.$field['key'];
        }

        $payload = ['provider' => $id, 'is_enabled' => '1', 'credentials' => [...$credentials, ...$credentialOverrides]];

        if (count($driver->environments()) > 1) {
            $payload['environment'] = CourierProvider::ENVIRONMENT_SANDBOX;
        }

        if (count($countries) > 1) {
            $payload['country'] = $countries[0];
        }

        return $payload;
    }

    private function saveAs(CourierOwner $owner, array $payload): void
    {
        $this->ownedBy($owner);

        $request = $this->requestFor($payload);
        $request->validateResolved();

        $this->app->make(CourierConfigService::class)->save($request);
    }

    private function errorsFrom(CourierOwner $owner, array $payload): array
    {
        $this->ownedBy($owner);

        try {
            $this->requestFor($payload)->validateResolved();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('The save was accepted but should have been rejected.');
    }

    private function requestFor(array $payload): SaveCourierProviderRequest
    {
        $request = SaveCourierProviderRequest::create('/courier/config', 'PUT', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }

    private function ownedBy(CourierOwner $owner): void
    {
        $this->app->bind(CourierOwnerResolver::class, fn () => new class($owner) implements CourierOwnerResolver {
            public function __construct(private readonly CourierOwner $owner) {}

            public function resolve(): CourierOwner
            {
                return $this->owner;
            }
        });

        $this->app->forgetInstance(ProviderRegistry::class);
    }

    private function onRoute(string $name, callable $assertions): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route [{$name}] is not registered.");

        $this->app['request']->setRouteResolver(fn () => $route);

        $assertions();
    }

    private function signInSeller(): void
    {
        $seller = new \App\Models\Seller();
        $seller->id = self::VENDOR_ID;

        $this->app['auth']->guard('seller')->setUser($seller);
    }

    private function hostResolver(): HostCourierOwnerResolver
    {
        return $this->app->make(HostCourierOwnerResolver::class);
    }

    private function registryFor(CourierOwner $owner): ProviderRegistry
    {
        $this->ownedBy($owner);

        return $this->app->make(ProviderRegistry::class);
    }

    private function catalogEntry(CourierOwner $owner, string $id): array
    {
        foreach ($this->registryFor($owner)->catalog() as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        $this->fail("[{$id}] is absent from the catalog.");
    }

    private function providers(): array
    {
        return (array) config('courier.providers');
    }

    private function driver(string $id): CourierProvider
    {
        return $this->app->make($this->providers()[$id]);
    }

    private function rowOf(CourierOwner $owner, string $provider): ?CourierProviderSetting
    {
        return CourierProviderSetting::query()->forOwner($owner)->where('provider', $provider)->first();
    }

    private function credentialsOf(CourierOwner $owner, string $provider): array
    {
        return (array) $this->rowOf($owner, $provider)?->credentials;
    }

    private function settingsOf(CourierOwner $owner, string $provider): array
    {
        return (array) $this->rowOf($owner, $provider)?->settings;
    }
}
