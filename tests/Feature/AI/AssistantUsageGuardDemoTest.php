<?php

namespace Tests\Feature\AI;

use Illuminate\Support\Facades\Cache;
use Modules\AI\app\Services\ShoppingAssistant\AssistantUsageGuardService;
use Tests\TestCase;

/**
 * Demo-mode usage policy (C2): the assistant must be capped per IP at 10 turns,
 * mirroring the existing AIProviderManager demo behaviour.
 *
 * Requires the configured test database (app boots DB-driven config).
 */
class AssistantUsageGuardDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('APP_MODE=demo');
        $_ENV['APP_MODE'] = $_SERVER['APP_MODE'] = 'demo';
        Cache::flush();
    }

    protected function tearDown(): void
    {
        putenv('APP_MODE');
        unset($_ENV['APP_MODE'], $_SERVER['APP_MODE']);
        parent::tearDown();
    }

    public function test_demo_mode_caps_assistant_usage_at_ten_per_ip(): void
    {
        $guard = app(AssistantUsageGuardService::class);

        // First 10 turns are allowed.
        for ($i = 1; $i <= 10; $i++) {
            $this->assertNull($guard->check(), "Turn {$i} should be allowed");
        }

        // 11th turn is blocked.
        $blocked = $guard->check();
        $this->assertIsArray($blocked);
        $this->assertSame('blocked', $blocked['intent']);
    }
}
