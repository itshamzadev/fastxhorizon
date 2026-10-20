<?php

namespace Modules\Courier\Tests\Unit;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Modules\Courier\Tests\CourierTestCase;

#[RunTestsInSeparateProcesses]
class SellerApiRouteTest extends CourierTestCase
{
    public static function endpoints(): array
    {
        return [
            ['GET', 'api/v3/seller/courier/config'],
            ['PUT', 'api/v3/seller/courier/config'],
            ['GET', 'api/v3/seller/courier/providers'],
            ['GET', 'api/v3/seller/courier/locations/stores'],
            ['GET', 'api/v3/seller/courier/locations/cities'],
            ['GET', 'api/v3/seller/courier/locations/zones/{cityId}'],
            ['GET', 'api/v3/seller/courier/locations/areas/{zoneId}'],
            ['POST', 'api/v3/seller/courier/estimate'],
            ['POST', 'api/v3/seller/courier/dispatch'],
            ['POST', 'api/v3/seller/courier/revise'],
            ['GET', 'api/v3/seller/courier/track/{consignmentId}'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_every_documented_seller_endpoint_is_registered_behind_the_token_guard(string $method, string $uri): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === $uri && in_array($method, $candidate->methods(), true));

        $this->assertNotNull($route, "[{$method} {$uri}] is not registered.");
        $this->assertContains('seller_api_auth', $route->gatherMiddleware());
        $this->assertNull($route->getName(), "[{$method} {$uri}] must stay unnamed so the owner resolves from the bearer token.");
    }

    public function test_the_configuration_endpoints_carry_the_vendor_setup_gate(): void
    {
        foreach (['GET', 'PUT'] as $method) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($candidate) => $candidate->uri() === 'api/v3/seller/courier/config'
                    && in_array($method, $candidate->methods(), true));

            $this->assertContains('vendor_delivery_partner_setup', $route->gatherMiddleware());
        }
    }

    #[DataProvider('endpoints')]
    public function test_only_tracking_is_reachable_once_the_admin_switches_vendor_setup_off(string $method, string $uri): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === $uri && in_array($method, $candidate->methods(), true));

        $isTracking = str_contains($uri, '/track/');

        $isTracking
            ? $this->assertNotContains('vendor_delivery_partner_setup', $route->gatherMiddleware(),
                "[{$method} {$uri}] must stay reachable so a shipment booked before the switch went off is still visible.")
            : $this->assertContains('vendor_delivery_partner_setup', $route->gatherMiddleware(),
                "[{$method} {$uri}] books on a vendor's own carrier account, so it must sit behind the vendor setup gate.");
    }

    public function test_the_vendor_panel_gates_the_same_booking_endpoints_as_the_seller_app(): void
    {
        $panelRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'vendor/courier'));

        $this->assertNotEmpty($panelRoutes);

        foreach ($panelRoutes as $route) {
            $isUngated = str_contains($route->uri(), '/track/') || str_ends_with($route->uri(), '/config');

            $isUngated
                ? $this->assertNotContains('vendor_delivery_partner_setup', $route->gatherMiddleware(), $route->uri())
                : $this->assertContains('vendor_delivery_partner_setup', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_the_vendor_panel_configuration_answers_to_the_admins_vendor_setup_setting_alone(): void
    {
        $configRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'vendor/courier/config');

        $this->assertCount(2, $configRoutes);

        foreach ($configRoutes as $route) {
            $this->assertContains('vendor_delivery_partner_config', $route->gatherMiddleware(),
                "[{$route->uri()}] must stay reachable while the platform's delivery partner service is off.");
        }
    }
}
