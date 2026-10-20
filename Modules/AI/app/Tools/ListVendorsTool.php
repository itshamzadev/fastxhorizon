<?php

namespace Modules\AI\app\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Contracts\ProductSuggestionInterface;

class ListVendorsTool implements Tool
{
    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
    ) {}

    public function name(): string
    {
        return 'list_vendors';
    }

    public function description(): string
    {
        return 'List the stores / vendors / sellers available on this marketplace. '
            . 'Call this whenever the customer asks which stores, vendors, sellers, or shops exist, '
            . 'or wants to see the available sellers. Optionally narrow by a name fragment.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional store name fragment to filter vendors (optional).'),
            'limit' => $schema->integer()->description('Maximum vendors to return (1–20, default 10).'),
        ];
    }

    public function handle(Request $request): string
    {
        $query = strtolower(trim((string) $request->string('query')));
        $limit = min(max((int) $request->integer('limit', 10), 1), 20);

        $cacheKey = 'ai_vendors:' . md5($query . '|' . $limit);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $limit) {
            $vendors = $this->productSuggestion->vendors(
                limit: $limit,
                query: $query !== '' ? $query : null,
            );

            return json_encode(['total' => count($vendors), 'vendors' => $vendors]);
        });
    }
}
