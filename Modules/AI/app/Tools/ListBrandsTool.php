<?php

namespace Modules\AI\app\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Contracts\ProductSuggestionInterface;

class ListBrandsTool implements Tool
{
    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
    ) {}

    public function name(): string
    {
        return 'list_brands';
    }

    public function description(): string
    {
        return 'List the product BRANDS (manufacturers / labels like Samsung, Nike) available on this marketplace. '
            . 'Brands are DISTINCT from stores/vendors/sellers — call this whenever the customer asks which brands '
            . 'exist, wants the brand list, or asks what brands you carry. Use list_vendors for stores/sellers instead. '
            . 'Optionally narrow by a name fragment.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional brand name fragment to filter brands (optional).'),
            'limit' => $schema->integer()->description('Maximum brands to return (1–30, default 15).'),
        ];
    }

    public function handle(Request $request): string
    {
        $query = strtolower(trim((string) $request->string('query')));
        $limit = min(max((int) $request->integer('limit', 15), 1), 30);

        $cacheKey = 'ai_brands:' . md5($query . '|' . $limit);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $limit) {
            $brands = $this->productSuggestion->brands(
                limit: $limit,
                query: $query !== '' ? $query : null,
            );

            return json_encode(['total' => count($brands), 'brands' => $brands]);
        });
    }
}
