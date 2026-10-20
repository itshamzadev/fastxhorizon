<?php

namespace App\Builder;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Modules\Builder\Contracts\ItemProvider as ItemProviderContract;
use Modules\Builder\Contracts\CategoryProvider as CategoryProviderContract;
use Modules\Builder\ValueObjects\Storefront\CategoryDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for the storefront category surface.
 *
 * Maps App\Models\Category (top-level + one child level) into the module's
 * CategoryDTO. Product-by-category listings are delegated to ItemProvider so
 * card mapping, pricing and wishlist context stay identical to every other
 * product list. 6Valley has no delivery modules/zones, so scope only narrows
 * the item counts to the active shop.
 */
class CategoryProvider implements CategoryProviderContract
{
    public function __construct(private readonly ItemProviderContract $itemProvider)
    {
    }

    public function forScope(?StorefrontScope $scope): array
    {
        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'priority', 'icon'])
            ->where('parent_id', 0)
            ->where('position', 0)
            ->with(['childes' => function ($query) {
                $query->select(['id', 'name', 'slug', 'parent_id', 'priority'])
                    ->orderByDesc('priority')
                    ->orderBy('name');
            }])
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        // Every direct child (any status) of the top-level categories, mapped
        // childId => parentId, so each child's own product badge is counted.
        $childParentMap = Category::query()
            ->whereIn('parent_id', $categories->pluck('id')->all())
            ->pluck('parent_id', 'id');

        $countableIds = $categories->pluck('id')
            ->merge($childParentMap->keys())
            ->unique()
            ->values()
            ->all();

        $directCounts = $this->categoryProductCounts($scope, $countableIds);

        return $categories->map(function (Category $category) use ($directCounts, $childParentMap) {
            // Only sub-categories that actually have products.
            $children = $category->childes
                ->map(fn (Category $child) => new CategoryDTO(
                    id: (int) $child->id,
                    name: (string) $child->name,
                    slug: $child->slug,
                    image: null,
                    itemCount: (int) ($directCounts[$child->id] ?? 0),
                ))
                ->filter(fn (CategoryDTO $child) => $child->itemCount > 0)
                ->values()
                ->all();

            return new CategoryDTO(
                id: (int) $category->id,
                name: (string) $category->name,
                slug: $category->slug,
                image: getStorageImages(path: $category->icon_full_url ?? null, type: 'category'),
                // A product's category_ids stores its full hierarchy, so the
                // parent's direct count already spans every descendant. This is
                // the exact query the product-by-category page runs, keeping the
                // homepage badge and the detail listing total in lockstep.
                itemCount: (int) ($directCounts[$category->id] ?? 0),
                children: $children,
            );
        })
            // Only top-level categories with products (own or via any child).
            ->filter(fn (CategoryDTO $dto) => $dto->itemCount > 0)
            ->map(fn (CategoryDTO $dto) => $dto->toArray())
            ->values()
            ->all();
    }

    public function normalizeActiveCategoryIds(array $categoryIds = [], mixed $categoryId = null): array
    {
        return collect($categoryIds)
            ->when($categoryId !== null && $categoryId !== '', fn ($collection) => $collection->push($categoryId))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function resolveActiveCategory(array $categories, array $activeCategoryIds): ?array
    {
        return collect($categories)
            ->map(fn (array $category) => [...$category, 'children' => $category['children'] ?? []])
            ->flatMap(fn (array $category) => collect([$category])->merge($category['children']))
            ->first(fn (array $category) => \in_array((int) $category['id'], $activeCategoryIds, true));
    }

    public function categoryProducts(
        ?StorefrontScope $scope,
        array $categoryIds = [],
        int $limit = 12,
        int $offset = 1,
        string $type = 'all',
    ): array {
        $limit = \max(1, \min($limit, 100));
        $offset = \max(1, $offset);

        return $this->itemProvider->listing(
            $scope,
            ['categoryIds' => $categoryIds, 'type' => $type, 'sort' => 'rating'],
            $limit,
            $offset,
        );
    }

    public function search(?StorefrontScope $scope, ?string $query, int $limit = 30): array
    {
        $query = \trim((string) $query);
        $limit = \max(1, \min($limit, 100));

        return Category::with('translations')
            ->where('parent_id', 0)
            ->when($query !== '', function (Builder $q) use ($query) {
                $q->where(function (Builder $inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhereHas('translations', fn (Builder $t) => $t->where('value', 'like', "%{$query}%"));
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug ?? null,
                'image' => getStorageImages(path: $category->icon_full_url ?? null, type: 'category'),
            ])
            ->values()
            ->all();
    }

    public function translatedNamesByIds(array $ids): array
    {
        $ids = collect($ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->all();
        if ($ids === []) {
            return [];
        }

        // Unfiltered by product count: the curated explore/home tabs must show
        // every configured category's translated name — including categories
        // with no live products, which forScope() intentionally drops. The name
        // is localized by Category's `translate` global scope (session locale).
        return Category::whereIn('id', $ids)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $category) => [(int) $category->id => (string) $category->name])
            ->all();
    }

    public function findIdBySlug(?StorefrontScope $scope, string $slug): ?int
    {
        $slug = \trim($slug);
        if ($slug === '') {
            return null;
        }

        $id = Category::query()
            ->where('slug', $slug)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Active-product counts keyed by category id, scoped to the active shop.
     * A product counts against a category when its primary category_id matches
     * or its category_ids hierarchy contains the id.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function categoryProductCounts(?StorefrontScope $scope, array $categoryIds)
    {
        if ($categoryIds === []) {
            return collect();
        }

        return collect($categoryIds)->mapWithKeys(function ($categoryId) use ($scope) {
            $count = Product::query()
                ->active()
                ->tap(fn (Builder $q) => $this->applyShopScope($q, $scope))
                ->where(function (Builder $q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereJsonContains('category_ids', ['id' => (string) $categoryId]);
                })
                ->count();

            return [(int) $categoryId => $count];
        });
    }

    /**
     * Restrict a product query to the storefront's shop by canonical seller
     * ownership (added_by + user_id), NOT products.shop_id — that column can be
     * shared across vendors and is unreliable for ownership. Mirrors
     * App\Builder\ItemProvider::applyShopScope() so category counts match the
     * product listings.
     */
    private function applyShopScope(Builder $query, ?StorefrontScope $scope): void
    {
        if ($scope === null) {
            return;
        }

        $sellerId = $scope->tenantId;

        $sellerId
            ? $query->where('added_by', 'seller')->where('user_id', $sellerId)
            : $query->where('added_by', 'admin');
    }
}
