<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

class ShownProductStore
{
    private array $byId = [];

    public function seed(array $products): void
    {
        foreach ($products as $product) {
            if (isset($product['id'])) {
                $this->byId[(int) $product['id']] = $product;
            }
        }
    }

    public function all(): array
    {
        return array_values($this->byId);
    }

    public function byIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->byId[(int) $id])) {
                $out[] = $this->byId[(int) $id];
            }
        }

        return $out;
    }

    public function match(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '') {
            return $this->all();
        }

        return array_values(array_filter(
            $this->byId,
            fn($product) => str_contains(strtolower((string) ($product['name'] ?? '')), $keyword),
        ));
    }

    public function isEmpty(): bool
    {
        return empty($this->byId);
    }

    public function reset(): void
    {
        $this->byId = [];
    }
}
