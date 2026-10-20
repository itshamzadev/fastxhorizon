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

class SearchOffersTool implements Tool
{
    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
        private readonly ProductCollector           $collector,
    ) {}

    public function name(): string
    {
        return 'search_offers';
    }

    public function description(): string
    {
        return 'Find products currently ON PROMOTION — discounted items, sale/clearance products, '
            . 'special offers and deals. Call this whenever the customer asks about deals, discounts, '
            . 'offers, sales, promotions, flash deals, featured deals, or "what is on sale". '
            . 'Set deal_type to match the SPECIFIC campaign the customer names so results match that '
            . 'exact page on the website: "flash_deal" for the flash-deals page, "featured_deal" for the '
            . 'featured-deal-products page. Use "general" (or omit) for any other deal/discount/sale ask. '
            . 'Optionally narrow by a keyword (e.g. offers on "phones").';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'         => $schema->string()->description('Optional keyword to narrow the offers (product name/type), e.g. "phone".'),
            'deal_type'     => $schema->string()->enum(['flash_deal', 'featured_deal', 'general'])->description('Which promotion the customer asked for: "flash_deal" = flash-deals campaign/page, "featured_deal" = featured-deal-products campaign/page, "general" (default) = any discounted/sale/clearance product.'),
            'limit'         => $schema->integer()->description('Maximum results to return (1–10, default 5).'),
            'max_price'     => $schema->number()->description('Maximum budget amount in the currency specified by currency_code (optional).'),
            'currency_code' => $schema->string()->description('ISO 4217 currency code for max_price, e.g. "USD", "BDT" (optional, defaults to store base currency).'),
        ];
    }

    public function handle(Request $request): string
    {
        $query        = strtolower(trim((string) $request->string('query')));
        $limit        = min((int) $request->integer('limit', 5), 10);
        $dealType     = strtolower(trim((string) $request->string('deal_type')));
        $maxPrice     = $request->has('max_price') ? (float) $request->float('max_price') : null;
        $currencyCode = strtoupper(trim((string) $request->string('currency_code')));

        $maxPriceUsd = ($maxPrice !== null && $maxPrice > 0)
            ? $this->convertToBaseUsd(amount: $maxPrice, currencyCode: $currencyCode)
            : null;

        $currency = (string) session('currency_code', '');
        $locale   = app()->getLocale();
        $cacheKey = 'ai_offers:' . md5($locale . '|' . $currency . '|' . $dealType . '|' . $query . '|' . $limit . '|' . ($maxPriceUsd ?? ''));

        $json = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($dealType, $query, $limit, $maxPriceUsd) {
            $keyword = $query !== '' ? $query : null;

            $result = match ($dealType) {
                'flash_deal'    => $this->productSuggestion->flashDeals(limit: $limit, query: $keyword, maxPriceUsd: $maxPriceUsd),
                'featured_deal' => $this->productSuggestion->featuredDeals(limit: $limit, query: $keyword, maxPriceUsd: $maxPriceUsd),
                default         => $this->productSuggestion->offers(limit: $limit, query: $keyword, maxPriceUsd: $maxPriceUsd),
            };

            return json_encode($result);
        });

        $this->collector->add(
            products: json_decode($json, associative: true)['products'] ?? [],
            toolName: 'search_offers',
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
