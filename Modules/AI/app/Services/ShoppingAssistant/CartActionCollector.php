<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

class CartActionCollector
{
    // Kept in sync with CART_MUTATING_ACTIONS in public/assets/front-end/ai/ai-shopping-assistant.js.
    private const CART_MUTATING_TYPES = ['added', 'updated', 'checkout', 'minimum_not_met'];

    /** @var array<int, array> */
    private array $actions = [];

    public function add(string $type, array $payload = []): void
    {
        $action = array_merge(['type' => $type], $payload);

        if (isset($action['product_id'])) {
            $this->actions = array_values(array_filter(
                $this->actions,
                fn($existing) => $this->survivesAlongside(existing: $existing, action: $action),
            ));
        }

        $this->actions[] = $action;
    }

    /**
     * One action per product, so a newer action replaces the older one. The exception: a later
     * non-mutating action (the agent re-calling add_to_cart and landing on select_variation) must
     * not erase an earlier 'added'/'updated' — the storefront refreshes its nav cart on those types
     * only, so dropping one leaves the header stale while the item sits in the cart.
     */
    private function survivesAlongside(array $existing, array $action): bool
    {
        if (($existing['product_id'] ?? null) !== $action['product_id']) {
            return true;
        }

        return $this->isCartMutating((string) ($existing['type'] ?? ''))
            && !$this->isCartMutating((string) $action['type']);
    }

    private function isCartMutating(string $type): bool
    {
        return in_array($type, self::CART_MUTATING_TYPES, strict: true);
    }

    /** @return array<int, array> */
    public function all(): array
    {
        return $this->actions;
    }

    public function reset(): void
    {
        $this->actions = [];
    }
}
