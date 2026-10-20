<?php

namespace Modules\AI\app\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Services\ShoppingAssistant\ProductCollector;
use Modules\AI\app\Services\ShoppingAssistant\ShownProductStore;

class RecallShownProductsTool implements Tool
{
    public function __construct(
        private readonly ShownProductStore $shown,
        private readonly ProductCollector  $collector,
    ) {}

    public function name(): string
    {
        return 'recall_shown_products';
    }

    public function description(): string
    {
        return 'Re-display products that were already shown earlier in THIS conversation. '
            . 'Call this when the customer wants to see, review, compare, or pick from products presented before — '
            . '"show the others", "show the rest", "the other iPhones", "what were the options again", '
            . '"I want to buy more of those". These products are in the catalog and still available — never claim '
            . 'they are unavailable and do NOT call search_products for them. Filter by product_ids or a name '
            . 'keyword, or omit both to re-show everything shown so far.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_ids' => $schema->array()->items($schema->integer())
                ->description('Specific product ids to re-show (optional).'),
            'keyword'     => $schema->string()
                ->description('Filter previously shown products by name keyword, e.g. "iphone" (optional).'),
        ];
    }

    public function handle(Request $request): string
    {
        $ids     = array_filter(array_map('intval', (array) ($request['product_ids'] ?? [])));
        $keyword = trim((string) $request->string('keyword'));

        $products = !empty($ids)
            ? $this->shown->byIds($ids)
            : $this->shown->match($keyword);

        $this->collector->add(products: $products, toolName: 'recall_shown_products');

        if (empty($products)) {
            return json_encode([
                'status'  => 'none',
                'message' => 'No previously shown products match. Use search_products to find products instead.',
            ]);
        }

        return json_encode([
            'status'   => 'shown',
            'count'    => count($products),
            'products' => array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $products),
            'message'  => 'These products are now shown to the customer as cards. Reference them by real name and '
                . 'price in natural language; do not list ids or internal data.',
        ]);
    }
}
