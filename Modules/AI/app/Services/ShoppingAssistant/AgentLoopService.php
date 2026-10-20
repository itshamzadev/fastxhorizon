<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Modules\AI\AIProviders\AIProviderManager;
use Modules\AI\app\Tools\AddToCartTool;
use Modules\AI\app\Tools\CheckoutTool;
use Modules\AI\app\Tools\GetSimilarProductsTool;
use Modules\AI\app\Tools\ListBrandsTool;
use Modules\AI\app\Tools\ListVendorsTool;
use Modules\AI\app\Tools\RecallShownProductsTool;
use Modules\AI\app\Tools\SafeTool;
use Modules\AI\app\Tools\SearchOffersTool;
use Modules\AI\app\Tools\SearchProductsTool;
use Modules\AI\app\Tools\UpdateCartQuantityTool;
use Modules\AI\app\Services\ShoppingAssistant\ImageAnalysisService;

class AgentLoopService
{
    public function __construct(
        private readonly SearchProductsTool       $searchTool,
        private readonly GetSimilarProductsTool   $similarTool,
        private readonly AddToCartTool            $addToCartTool,
        private readonly UpdateCartQuantityTool   $updateCartQtyTool,
        private readonly CheckoutTool             $checkoutTool,
        private readonly RecallShownProductsTool  $recallTool,
        private readonly SearchOffersTool         $offersTool,
        private readonly ListVendorsTool          $vendorsTool,
        private readonly ListBrandsTool           $brandsTool,
        private readonly ProductCollector         $collector,
        private readonly CartActionCollector      $cartAction,
        private readonly ImageAnalysisService     $imageAnalysis,
        private readonly AIProviderManager        $providerManager,
    ) {}

    public function run(
        array   $history,
        string  $newMessage,
        string  $systemPrompt,
        ?string $imageUrl = null,
    ): array {
        $cacheKey = $this->resolveCacheKey($history, $newMessage, $imageUrl);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $this->collector->reset();
        $this->cartAction->reset();
        $this->configureProvider();

        $messages = $this->toMessageObjects($history);

        $agent = new AnonymousAgent($systemPrompt, $messages, [
            new SafeTool($this->searchTool),
            new SafeTool($this->similarTool),
            new SafeTool($this->addToCartTool),
            new SafeTool($this->updateCartQtyTool),
            new SafeTool($this->checkoutTool),
            new SafeTool($this->recallTool),
            new SafeTool($this->offersTool),
            new SafeTool($this->vendorsTool),
            new SafeTool($this->brandsTool),
        ]);

        $prompt = $imageUrl
            ? $this->buildImageAwarePrompt(newMessage: $newMessage, imageUrl: $imageUrl)
            : $newMessage;

        $response = $agent->prompt($prompt);

        $result = [
            'reply'      => $response->text,
            'products'   => $this->collector->all(),
            'tools_used' => $this->collector->toolsUsed(),
            'actions'    => $this->cartAction->all(),
        ];

        // Never cache cart/checkout or recall turns: their output is per-customer and would leak across users.
        $recalled = in_array('recall_shown_products', $result['tools_used'], strict: true);
        if (empty($result['actions']) && !$recalled) {
            Cache::put(key: $cacheKey, value: $result, ttl: now()->addMinutes(10));
        }

        return $result;
    }

    // History-scoped once history exists so one shopper's personal answer is never served to another; only the first turn shares a global key.
    private function resolveCacheKey(array $history, string $newMessage, ?string $imageUrl): string
    {
        $normalized = strtolower(trim($newMessage));

        // Currency scopes the key: replies embed formatted prices, so a EUR shopper must never get a USD-formatted cached answer.
        $currency = (string) session('currency_code', '');

        if (empty($history)) {
            return 'ai_response_global:' . md5(
                app()->getLocale() . '|' . $currency . '|' . $normalized . '|' . ($imageUrl ?? '')
            );
        }

        return 'ai_response:' . md5(
            json_encode(array_slice($history, -6))
            . '|' . $currency
            . '|' . $normalized
            . '|' . ($imageUrl ?? '')
        );
    }

    private function buildImageAwarePrompt(string $newMessage, string $imageUrl): string
    {
        $analysis = $this->imageAnalysis->extractProductKeywords($imageUrl);

        $hasDetails = !empty($analysis['keywords']) || !empty($analysis['category']) || !empty($analysis['attributes']);

        $parts = [$newMessage];

        if (!$hasDetails) {
            $parts[] = 'The uploaded image could not be analysed for product details. '
                . 'Ask the customer to briefly describe the product they are looking for.';

            return implode("\n", $parts);
        }

        if (!empty($analysis['keywords'])) {
            $parts[] = 'Visible in image: ' . implode(', ', $analysis['keywords']) . '.';
        }

        if (!empty($analysis['category'])) {
            $parts[] = 'Category: ' . $analysis['category'] . '.';
        }

        if (!empty($analysis['attributes'])) {
            $attrs = collect($analysis['attributes'])
                ->map(fn($v, $k) => "{$k}: {$v}")
                ->implode(', ');
            $parts[] = 'Attributes: ' . $attrs . '.';
        }

        $parts[] = 'Call search_products now using these image details (use the product type/category as the query) '
            . 'to find matching or similar products from the catalog.';

        return implode("\n", $parts);
    }

    private function configureProvider(): void
    {
        $setting = $this->providerManager->getActiveAIProvider();

        $providerName = match (strtolower($setting->ai_name)) {
            'claude', 'anthropic' => 'anthropic',
            default               => 'openai',
        };

        config(["ai.providers.{$providerName}.key" => $setting->api_key]);
        config(['ai.default' => $providerName]);

        if ($setting->organization_id) {
            config(["ai.providers.{$providerName}.organization" => $setting->organization_id]);
        }
    }

    private function toMessageObjects(array $history): array
    {
        return collect($history)
            ->filter(fn($m) => in_array($m['role'], ['user', 'assistant'], strict: true))
            ->map(fn($m) => match ($m['role']) {
                'user'      => new UserMessage($m['content']),
                'assistant' => new AssistantMessage($m['content']),
            })
            ->values()
            ->all();
    }
}
