<?php

namespace Modules\AI\app\Tools;

use App\Models\Currency;
use App\Utils\Helpers;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Contracts\ProductSuggestionInterface;
use Modules\AI\app\Services\ShoppingAssistant\ProductCollector;

class SearchProductsTool implements Tool
{
    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
        private readonly ProductCollector           $collector,
    ) {}

    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Search for products in the store by keywords, category, or attributes. '
            . 'Call this whenever the customer wants to find, browse, or compare products — even if the request is vague. '
            . 'Extract keywords from the ENTIRE conversation, not just the latest message. '
            . 'If the customer explicitly asks for variation/configurable products (with selectable options like '
            . 'color/size/type) or, conversely, for non-variation/simple products, set has_variation accordingly.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'         => $schema->string()->description('Search keywords: product name, type, brand, or feature.')->required(),
            'category'      => $schema->string()->description('Product category name to narrow results (optional).'),
            'attributes'    => $schema->object()->description('Extra filters e.g. {"color":"blue","brand":"Nike"} (optional).'),
            'limit'         => $schema->integer()->description('Maximum results to return (1–10, default 5).'),
            'max_price'     => $schema->number()->description('Maximum budget amount in the currency specified by currency_code (optional).'),
            'currency_code' => $schema->string()->description('ISO 4217 currency code for max_price, e.g. "USD", "BDT", "EUR" (optional, defaults to store base currency).'),
            'has_variation' => $schema->boolean()->description('Set ONLY when the customer explicitly asks by configurability: true for variation/configurable products (selectable color/size/type options), false for non-variation/simple products. Omit entirely otherwise to return both.'),
            'sort'          => $schema->string()->description('Result ranking. Use "popularity" when the customer asks for the best/top/good/recommended/popular/highest-rated products, so results are ranked by ratings and orders. Use "price_high" when they ask for the most expensive / highest-priced / priciest products, or "price_low" for the cheapest / lowest-priced / most affordable products (both order by price and need NO budget). Use "relevance" (or omit) for a plain keyword search.'),
            'brand'         => $schema->string()->description('Brand NAME to filter by, e.g. "Samsung", when the customer asks for a specific brand (optional).'),
            'store'         => $schema->string()->description('Store/shop/vendor/seller NAME to filter by, e.g. "Gizmox", when the customer wants products from a specific store (optional).'),
        ];
    }

    public function handle(Request $request): string
    {
        $query        = strtolower(trim((string) $request->string('query')));
        $category     = strtolower(trim((string) $request->string('category')));
        $attributes   = (array) ($request['attributes'] ?? []);
        $limit        = min((int) $request->integer('limit', 5), 10);
        $maxPrice     = $request->has('max_price') ? (float) $request->float('max_price') : null;
        $currencyCode = strtoupper(trim((string) $request->string('currency_code')));
        $hasVariation = $request->has('has_variation') ? (bool) $request->boolean('has_variation') : null;
        $sortInput    = strtolower(trim((string) $request->string('sort')));
        $sort         = in_array($sortInput, ['popularity', 'price_high', 'price_low'], true) ? $sortInput : null;
        $brand        = trim((string) $request->string('brand'));
        $store        = trim((string) $request->string('store'));

        $maxPriceUsd = ($maxPrice !== null && $maxPrice > 0)
            ? $this->convertToBaseUsd(amount: $maxPrice, currencyCode: $currencyCode)
            : null;

        $currency = (string) session('currency_code', '');
        $locale   = app()->getLocale();
        $cacheKey = 'ai_search:' . md5(
            $locale . '|' . $currency . '|' . $query . '|' . $category . '|' . json_encode($attributes) . '|' . $limit . '|' . ($maxPriceUsd ?? '') . '|' . ($hasVariation === null ? '' : ($hasVariation ? '1' : '0')) . '|' . ($sort ?? '') . '|' . $brand . '|' . $store
        );

        $json = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $category, $attributes, $limit, $maxPriceUsd, $hasVariation, $sort, $brand, $store) {
            return json_encode($this->productSuggestion->suggest(
                query:        $query,
                category:     $category,
                attributes:   $attributes,
                limit:        $limit,
                maxPriceUsd:  $maxPriceUsd,
                hasVariation: $hasVariation,
                sort:         $sort,
                brand:        $brand !== '' ? $brand : null,
                store:        $store !== '' ? $store : null,
            ));
        });

        $this->collector->add(
            products: json_decode($json, associative: true)['products'] ?? [],
            toolName: 'search_products',
        );

        return $json;
    }

    private function convertToBaseUsd(float $amount, string $currencyCode): float
    {
        if ($currencyCode === '') {
            return (float) Helpers::convert_currency_to_usd($amount);
        }

        if ($currencyCode === 'USD') {
            return $amount;
        }

        $currency = Currency::where('code', $currencyCode)->first();
        if (!$currency || (float) $currency->exchange_rate === 0.0) {
            \Illuminate\Support\Facades\Log::warning('AI search: unrecognized or zero-rate currency for budget', [
                'currency_code' => $currencyCode,
            ]);

            return (float) Helpers::convert_currency_to_usd($amount);
        }

        $priceInBase = $amount / (float) $currency->exchange_rate;

        $usdCurrency = Currency::where('code', 'USD')->first();
        if ($usdCurrency && (float) $usdCurrency->exchange_rate !== 0.0) {
            $priceInBase = (float) $usdCurrency->exchange_rate < 1
                ? $priceInBase * (float) $usdCurrency->exchange_rate
                : $priceInBase / (float) $usdCurrency->exchange_rate;
        }

        return $priceInBase;
    }
}
