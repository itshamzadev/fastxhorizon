<?php

namespace Modules\AI\app\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Contracts\ProductSuggestionInterface;
use Modules\AI\app\Services\ShoppingAssistant\ProductCollector;

class GetSimilarProductsTool implements Tool
{
    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
        private readonly ProductCollector           $collector,
    ) {}

    public function name(): string
    {
        return 'get_similar_products';
    }

    public function description(): string
    {
        return 'Find products similar to a specific product by its ID. '
            . 'Use when the customer wants alternatives or more options like an item they are already viewing.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->description('The ID of the product to find alternatives for.')->required(),
            'limit'      => $schema->integer()->description('Maximum results to return (1–10, default 5).'),
        ];
    }

    public function handle(Request $request): string
    {
        $productId = (int) $request->integer('product_id');
        $limit     = min((int) $request->integer('limit', 5), 10);

        $currency = (string) session('currency_code', '');
        $cacheKey = 'ai_similar:' . md5($currency . '|' . $productId . '|' . $limit);

        $json = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($productId, $limit) {
            return json_encode($this->productSuggestion->similar(
                productId: $productId,
                limit:     $limit,
            ));
        });

        $this->collector->add(
            products: json_decode($json, associative: true)['products'] ?? [],
            toolName: 'get_similar_products',
        );

        return $json;
    }
}
