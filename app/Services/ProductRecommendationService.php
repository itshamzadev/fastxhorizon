<?php

namespace App\Services;

use App\Models\CustomerSearch;
use App\Models\ProductView;
use App\Models\Tag;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\AI\AIProviders\AIProviderManager;
use Modules\AI\AIProviders\ClaudeProvider;
use Modules\AI\AIProviders\OpenAIProvider;
use Throwable;

/**
 * Reorders a homepage section's already-fetched products (never adds/removes) so products the
 * logged-in customer showed interest in float up, ranked by a recency-decayed score. A purchase is
 * a deliberately weak, fast-fading signal so a just-bought product doesn't dominate every section.
 * AI stays off the request path: it runs only at search-capture time to bridge a keyword to tags.
 */
class ProductRecommendationService
{
    private const DEFAULT_WEIGHTS = [
        'search' => 10,
        'view' => 9,
        'cart' => 6,
        'review' => 5,
        'rating' => 4,
        'wishlist' => 3,
        'order' => 1,
    ];

    private const DEFAULT_HALF_LIFE_DAYS = 14;
    private const ORDER_HALF_LIFE_DAYS = 2;   // a purchase signal fades within about a week
    private const DEFAULT_TAG_AFFINITY_WEIGHT = 1.5;
    private const DEFAULT_TAG_AFFINITY_CAP = 6;
    private const KEYWORD_TAG_CACHE_TTL_DAYS = 30;
    private const AI_TAG_VOCAB_LIMIT = 200;
    private const INTERACTED_PRODUCTS_CAP = 300;
    private const INTEREST_PROFILE_CACHE_MINUTES = 15;  // set to 0 to always recompute live

    private ?bool $active = null;
    private ?array $config = null;

    /** @var array<int, float> product_id => score, memoized for the request */
    private array $scoreCache = [];

    public function isActive(): bool
    {
        if ($this->active === null) {
            $this->active = (bool)($this->config()['status'] ?? false) && Auth::guard('customer')->check();
        }
        return $this->active;
    }

    /** Bounded to one increment per product per day, so view_count is a "distinct days viewed" signal. */
    public function recordView(int $productId): void
    {
        if (!$this->isActive() || $productId <= 0) {
            return;
        }

        $customerId = (int)Auth::guard('customer')->id();
        $existing = ProductView::firstOrCreate(
            ['customer_id' => $customerId, 'product_id' => $productId],
            ['view_count' => 1, 'viewed_at' => now()]
        );

        if ($existing->wasRecentlyCreated) {
            return;
        }

        if (!$existing->viewed_at || $existing->viewed_at->lt(now()->startOfDay())) {
            $existing->increment('view_count');
            $existing->forceFill(['viewed_at' => now()])->save();
        }
    }

    /** Deduped to one update per keyword per day. */
    public function recordSearch(?string $keyword): void
    {
        if (!$this->isActive()) {
            return;
        }
        $keyword = $this->normalizeKeyword($keyword);
        if ($keyword === '') {
            return;
        }

        $customerId = (int)Auth::guard('customer')->id();
        $tagIds = $this->resolveKeywordTags($keyword);
        $existing = CustomerSearch::firstOrCreate(
            ['customer_id' => $customerId, 'keyword' => $keyword],
            ['tag_ids' => $tagIds, 'search_count' => 1, 'searched_at' => now()]
        );

        if ($existing->wasRecentlyCreated) {
            return;
        }

        if (!$existing->searched_at || $existing->searched_at->lt(now()->startOfDay())) {
            $existing->increment('search_count');
            $existing->forceFill(['searched_at' => now(), 'tag_ids' => $tagIds])->save();
        }
    }

    /** Interacted products first (score desc); the rest keep their order. Nothing added or removed. */
    public function reorder($products)
    {
        if (!$this->isActive() || empty($products)) {
            return $products;
        }

        if ($products instanceof LengthAwarePaginator) {
            $products->setCollection($this->sortByScore(collect($products->getCollection())));
            return $products;
        }

        if ($products instanceof Collection) {
            return $this->sortByScore($products);
        }

        return $products;
    }

    /** Already-scored ids are skipped, so priming the whole homepage keeps it to a single query. */
    public function prime(array $productIds): void
    {
        if (!$this->isActive()) {
            return;
        }

        $missing = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $missing = array_diff($missing, array_keys($this->scoreCache));
        if (empty($missing)) {
            return;
        }

        // Default to 0 so a missing id is never re-queried this request.
        foreach ($missing as $id) {
            $this->scoreCache[$id] = 0.0;
        }

        $weights = $this->config()['weights'];
        $halfLife = $this->config()['half_life_days'];
        $customerId = (int)Auth::guard('customer')->id();

        foreach ($this->fetchActivityRows($customerId, $missing) as $row) {
            $weight = (float)($weights[$row->activity] ?? 0);
            if ($weight <= 0) {
                continue;
            }
            $signalHalfLife = $row->activity === 'order' ? self::ORDER_HALF_LIFE_DAYS : $halfLife;
            $this->scoreCache[(int)$row->product_id] += $weight * (float)$row->qty * $this->decay($row->event_time, $signalHalfLife);
        }

        $this->addInterestScores($customerId, $missing);
    }

    public function getScore(int $productId): float
    {
        return $this->scoreCache[$productId] ?? 0.0;
    }

    private function sortByScore(Collection $products): Collection
    {
        $list = $products->values();
        $this->prime($list->pluck('id')->all());

        $indexed = [];
        foreach ($list as $index => $product) {
            $indexed[] = ['product' => $product, 'index' => $index, 'score' => $this->getScore((int)$product->id)];
        }

        usort($indexed, fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['index'] <=> $b['index']));

        return collect(array_column($indexed, 'product'));
    }

    private function addInterestScores(int $customerId, array $productIds): void
    {
        $config = $this->config();
        $searchWeight = (float)($config['weights']['search'] ?? 0);
        $tagWeight = (float)$config['tag_affinity_weight'];
        $tagCap = (float)$config['tag_affinity_cap'];
        $halfLife = $config['half_life_days'];

        if ($searchWeight <= 0 && $tagWeight <= 0) {
            return;
        }

        $profile = $this->customerInterestProfile($customerId);
        $affinityTags = $profile['affinity_tags'];
        $searches = $profile['searches'];

        $productTags = $this->fetchProductTagMap($productIds);

        foreach ($productIds as $pid) {
            $productTagIds = $productTags[$pid] ?? [];
            if (empty($productTagIds)) {
                continue;
            }

            if ($tagWeight > 0) {
                $overlap = count(array_intersect($productTagIds, $affinityTags));
                if ($overlap > 0) {
                    $this->scoreCache[$pid] += min($tagCap, $tagWeight * $overlap);
                }
            }

            if ($searchWeight > 0) {
                $bestSearchTime = null;
                foreach ($searches as $search) {
                    if (array_intersect($productTagIds, $search['tag_ids'])) {
                        $bestSearchTime = $bestSearchTime === null ? $search['ts'] : max($bestSearchTime, $search['ts']);
                    }
                }
                if ($bestSearchTime !== null) {
                    $this->scoreCache[$pid] += $searchWeight * $this->decay($bestSearchTime, $halfLife);
                }
            }
        }
    }

    /**
     * Page-independent interest profile, cached briefly. Direct per-product activity is NOT cached,
     * so a new view/cart/order moves a product immediately; only tag affinity can be stale.
     *
     * @return array{affinity_tags: int[], searches: array<int, array{tag_ids:int[], ts:int|null}>}
     */
    private function customerInterestProfile(int $customerId): array
    {
        $halfLife = $this->config()['half_life_days'];
        $builder = function () use ($customerId, $halfLife) {
            $interactedIds = $this->fetchInteractedProductIds($customerId);
            $tagsByProduct = $this->fetchProductTagMap($interactedIds);

            $affinity = [];
            foreach ($interactedIds as $pid) {
                foreach ($tagsByProduct[$pid] ?? [] as $tagId) {
                    $affinity[$tagId] = true;
                }
            }

            $searches = CustomerSearch::where('customer_id', $customerId)
                ->where('searched_at', '>=', now()->subDays(2 * $halfLife))
                ->get(['tag_ids', 'searched_at'])
                ->map(fn($search) => [
                    'tag_ids' => array_map('intval', (array)$search->tag_ids),
                    'ts' => $search->searched_at?->timestamp,
                ])->all();

            foreach ($searches as $search) {
                foreach ($search['tag_ids'] as $tagId) {
                    $affinity[$tagId] = true;
                }
            }

            return ['affinity_tags' => array_keys($affinity), 'searches' => $searches];
        };

        if (self::INTEREST_PROFILE_CACHE_MINUTES <= 0) {
            return $builder();
        }
        return Cache::remember("rec_interest_profile_{$customerId}", now()->addMinutes(self::INTEREST_PROFILE_CACHE_MINUTES), $builder);
    }

    private function fetchActivityRows(int $customerId, array $productIds)
    {
        $views = DB::table('product_views')
            ->whereIn('product_id', $productIds)->where('customer_id', $customerId)
            ->selectRaw("product_id, view_count as qty, viewed_at as event_time, 'view' as activity");

        $wishlist = DB::table('wishlists')
            ->whereIn('product_id', $productIds)->where('customer_id', $customerId)
            ->selectRaw("product_id, 1 as qty, created_at as event_time, 'wishlist' as activity");

        $cart = DB::table('carts')
            ->whereIn('product_id', $productIds)->where('customer_id', $customerId)->where('is_guest', 0)
            ->selectRaw("product_id, 1 as qty, created_at as event_time, 'cart' as activity");

        $rating = DB::table('reviews')
            ->whereIn('product_id', $productIds)->where('customer_id', $customerId)->where('rating', '>', 0)
            ->selectRaw("product_id, 1 as qty, created_at as event_time, 'rating' as activity");

        $review = DB::table('reviews')
            ->whereIn('product_id', $productIds)->where('customer_id', $customerId)
            ->whereNotNull('comment')->where('comment', '!=', '')
            ->selectRaw("product_id, 1 as qty, created_at as event_time, 'review' as activity");

        $orders = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->whereIn('order_details.product_id', $productIds)->where('orders.customer_id', $customerId)
            ->selectRaw("order_details.product_id as product_id, 1 as qty, orders.created_at as event_time, 'order' as activity");

        return $views->unionAll($wishlist)->unionAll($cart)->unionAll($rating)->unionAll($review)->unionAll($orders)->get();
    }

    private function fetchInteractedProductIds(int $customerId): array
    {
        $cap = self::INTERACTED_PRODUCTS_CAP;
        $views = DB::table('product_views')->where('customer_id', $customerId)->orderByDesc('id')->limit($cap)->select('product_id');
        $wishlist = DB::table('wishlists')->where('customer_id', $customerId)->orderByDesc('id')->limit($cap)->select('product_id');
        $cart = DB::table('carts')->where('customer_id', $customerId)->where('is_guest', 0)->orderByDesc('id')->limit($cap)->select('product_id');
        $reviews = DB::table('reviews')->where('customer_id', $customerId)->orderByDesc('id')->limit($cap)->select('product_id');
        $orders = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.customer_id', $customerId)->orderByDesc('order_details.id')->limit($cap)
            ->select('order_details.product_id as product_id');

        return $views->unionAll($wishlist)->unionAll($cart)->unionAll($reviews)->unionAll($orders)
            ->pluck('product_id')->map(fn($id) => (int)$id)->unique()->values()->all();
    }

    /** @return array<int, int[]> product_id => list of tag ids */
    private function fetchProductTagMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        $map = [];
        DB::table('product_tag')->whereIn('product_id', $productIds)->get(['product_id', 'tag_id'])
            ->each(function ($row) use (&$map) {
                $map[(int)$row->product_id][] = (int)$row->tag_id;
            });
        return $map;
    }

    /** Literal tag match first; only when that finds nothing does it fall back to the AI bridge. */
    private function resolveKeywordTags(string $keyword): array
    {
        return Cache::remember(
            'rec_keyword_tags_' . md5($keyword),
            now()->addDays(self::KEYWORD_TAG_CACHE_TTL_DAYS),
            function () use ($keyword) {
                $literal = Tag::where('tag', 'like', "%{$keyword}%")
                    ->orWhereIn('tag', explode(' ', $keyword))
                    ->pluck('id')->map(fn($id) => (int)$id)->all();

                if (!empty($literal)) {
                    return array_values(array_unique($literal));
                }
                return $this->aiBridgeKeywordToTags($keyword);
            }
        );
    }

    /**
     * Maps a fuzzy keyword onto existing tags so "gaming mouse" can match products tagged "mouse".
     * Heavily guarded and never throws into the request.
     *
     * @return int[]
     */
    private function aiBridgeKeywordToTags(string $keyword): array
    {
        if (!getActiveAIProviderConfigCache()) {
            return [];
        }

        try {
            $vocabulary = Tag::orderByDesc('visit_count')->limit(self::AI_TAG_VOCAB_LIMIT)->pluck('tag', 'id');
            if ($vocabulary->isEmpty()) {
                return [];
            }

            $prompt = "From the tag list below, return ONLY the tags (comma-separated, exact spelling) that best match the shopping search \"{$keyword}\". "
                . "If none are relevant, return an empty line. Tags: " . $vocabulary->values()->implode(', ');

            // Call the provider directly to bypass vendor usage-limit logging, which needs a seller_id
            // we don't have in this context.
            $response = (new AIProviderManager([new OpenAIProvider(), new ClaudeProvider()]))
                ->getAvailableProviderObject()
                ->generate(prompt: $prompt, imageUrl: null, options: ['max_tokens' => 120]);

            $matched = collect(explode(',', strtolower($response)))->map(fn($t) => trim($t))->filter()->all();
            if (empty($matched)) {
                return [];
            }

            return $vocabulary
                ->filter(fn($tag) => in_array(strtolower($tag), $matched, true))
                ->keys()->map(fn($id) => (int)$id)->all();
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function decay($eventTime, int $halfLife): float
    {
        $timestamp = is_numeric($eventTime) ? (int)$eventTime : ($eventTime ? strtotime($eventTime) : time());
        $ageDays = max(0, (time() - $timestamp) / 86400);
        return pow(0.5, $ageDays / $halfLife);
    }

    private function normalizeKeyword(?string $keyword): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/', ' ', (string)$keyword)));
    }

    private function config(): array
    {
        if ($this->config === null) {
            $stored = getWebConfig(name: 'product_recommendation_setup');
            $stored = is_array($stored) ? $stored : [];
            $this->config = [
                'status' => (int)($stored['status'] ?? 0),
                'weights' => self::DEFAULT_WEIGHTS,
                'half_life_days' => self::DEFAULT_HALF_LIFE_DAYS,
                'tag_affinity_weight' => self::DEFAULT_TAG_AFFINITY_WEIGHT,
                'tag_affinity_cap' => self::DEFAULT_TAG_AFFINITY_CAP,
            ];
        }
        return $this->config;
    }
}
