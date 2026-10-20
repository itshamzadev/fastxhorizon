<?php

namespace Modules\AI\app\Contracts;

interface ProductSuggestionInterface
{
    /** @return array{total: int, products: array} */
    public function suggest(
        string $query,
        ?string $category = null,
        array $attributes = [],
        int $limit = 5,
        ?float $maxPriceUsd = null,
        ?bool $hasVariation = null,
        ?string $sort = null,
        ?string $brand = null,
        ?string $store = null,
    ): array;

    /** @return array<int, array{name: string, product_count: int}> */
    public function vendors(int $limit = 10, ?string $query = null): array;

    /** @return array<int, array{name: string, product_count: int}> */
    public function brands(int $limit = 15, ?string $query = null): array;

    /** @return array{total: int, products: array} */
    public function offers(int $limit = 5, ?string $query = null, ?float $maxPriceUsd = null): array;

    /** @return array{total: int, products: array} */
    public function flashDeals(int $limit = 5, ?string $query = null, ?float $maxPriceUsd = null): array;

    /** @return array{total: int, products: array} */
    public function featuredDeals(int $limit = 5, ?string $query = null, ?float $maxPriceUsd = null): array;

    public function formatById(int $productId): ?array;

    /** @return array{total: int, products: array} */
    public function similar(int $productId, int $limit = 5): array;
}
