<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

class ProductCollector
{
    private array $products  = [];
    private array $seenIds   = [];
    private array $toolsUsed = [];

    public function add(array $products, string $toolName): void
    {
        $this->toolsUsed[] = $toolName;

        foreach ($products as $product) {
            if (!isset($this->seenIds[$product['id']])) {
                $this->seenIds[$product['id']] = true;
                $this->products[]              = $product;
            }
        }
    }

    public function all(): array
    {
        return $this->products;
    }

    public function toolsUsed(): array
    {
        return array_values(array_unique($this->toolsUsed));
    }

    public function reset(): void
    {
        $this->products  = [];
        $this->seenIds   = [];
        $this->toolsUsed = [];
    }
}
