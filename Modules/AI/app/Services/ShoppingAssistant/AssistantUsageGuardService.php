<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use Illuminate\Support\Facades\Cache;
use Modules\AI\app\Exceptions\UsageLimitException;
use Modules\AI\app\Models\AISetting;
use Modules\AI\app\Services\AIUsageManagerService;
use Modules\AI\app\Utils\CurrentAuthUser;

class AssistantUsageGuardService
{
    private const DEMO_LIMIT = 10;

    public function __construct(private readonly AIUsageManagerService $usage) {}

    public function check(?string $imageUrl = null): ?array
    {
        if (strtolower((string) env('APP_MODE')) === 'demo') {
            return $this->checkDemoLimit();
        }

        if (CurrentAuthUser::isAdmin() || !CurrentAuthUser::isCustomer()) {
            return null;
        }

        return $this->checkCustomerLimit($imageUrl);
    }

    private function checkDemoLimit(): ?array
    {
        $ip       = request()->header('x-forwarded-for') ?: request()->ip();
        $cacheKey = 'demo_ip_usage_' . $ip;
        $count    = (int) Cache::get($cacheKey, 0);

        if ($count >= self::DEMO_LIMIT) {
            return [
                'reply'  => translate('Demo_limit_reached_You_can_only_use_the_assistant_a_limited_number_of_times'),
                'intent' => 'blocked',
            ];
        }

        Cache::forever($cacheKey, $count + 1);

        return null;
    }

    private function checkCustomerLimit(?string $imageUrl): ?array
    {
        $provider = AISetting::where('status', 1)->first();
        if (!$provider) {
            return null;
        }

        $log = $this->usage->getOrCreateLog($provider);

        try {
            $this->usage->checkUsageLimits(log: $log, provider: $provider, imageUrl: $imageUrl);
        } catch (UsageLimitException) {
            return [
                'reply'  => translate('You_have_reached_your_usage_limit_for_the_shopping_assistant'),
                'intent' => 'blocked',
            ];
        }

        $this->usage->incrementUsage(log: $log, imageUrl: $imageUrl);

        return null;
    }
}
