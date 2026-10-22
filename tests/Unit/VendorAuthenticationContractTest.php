<?php

namespace Tests\Unit;

use App\Http\Requests\Vendor\LoginRequest;
use App\Services\VendorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class VendorAuthenticationContractTest extends TestCase
{
    public function test_vendor_login_and_logout_routes_are_distinct_and_correct(): void
    {
        self::assertSame('vendor/auth/login', app('router')->getRoutes()->getByName('vendor.auth.login')->uri());
        self::assertSame('vendor/auth/logout', app('router')->getRoutes()->getByName('vendor.auth.logout')->uri());
    }

    public function test_seller_login_request_accepts_email_or_phone_identity(): void
    {
        $validator = Validator::make([
            'email' => '03001234567',
            'password' => 'Valid-password-1!',
        ], (new LoginRequest())->rules());

        self::assertFalse($validator->fails());
    }

    public function test_vendor_service_uses_phone_for_non_email_identity(): void
    {
        $guard = \Mockery::mock(\Illuminate\Contracts\Auth\StatefulGuard::class);
        $guard->shouldReceive('attempt')
            ->once()
            ->with([
                'phone' => '03001234567',
                'password' => 'Valid-password-1!',
            ], false)
            ->andReturnTrue();

        Auth::shouldReceive('guard')->once()->with('seller')->andReturn($guard);

        self::assertTrue((new VendorService())->isLoginSuccessful('03001234567', 'Valid-password-1!', false));
    }
}
