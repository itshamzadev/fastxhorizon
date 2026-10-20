<?php

namespace Modules\Courier\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Modules\Courier\Tests\CourierTestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class DeliveryPartnerServiceGateTest extends CourierTestCase
{
    public function test_every_admin_endpoint_except_configuration_and_tracking_sits_behind_the_delivery_partner_service_gate(): void
    {
        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/courier'));

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $isUngated = str_contains($route->uri(), '/track/') || str_ends_with($route->uri(), '/config');

            $isUngated
                ? $this->assertNotContains('delivery_partner_service', $route->gatherMiddleware(),
                    "[{$route->uri()}] must stay reachable so a partner can be set up, and an already booked shipment read, while the switch is off.")
                : $this->assertContains('delivery_partner_service', $route->gatherMiddleware(),
                    "[{$route->uri()}] books a shipment, so it must sit behind the delivery partner service gate.");
        }
    }

    public function test_the_admin_configuration_endpoints_stay_behind_the_addon_gate(): void
    {
        $configRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'admin/courier/config');

        $this->assertCount(2, $configRoutes);

        foreach ($configRoutes as $route) {
            $this->assertContains('delivery_partner_config', $route->gatherMiddleware(),
                "[{$route->uri()}] must stay unreachable while the courier addon itself is inactive.");
        }
    }
}
