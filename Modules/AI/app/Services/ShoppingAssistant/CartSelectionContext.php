<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request as HttpRequest;

class CartSelectionContext
{
    private ?Authenticatable $customer = null;

    private ?string $guestId = null;

    /** @var array<int, array> each entry is one card's selection */
    private array $selections = [];

    public function set(?Authenticatable $customer, ?string $guestId, ?array $selections): void
    {
        $this->customer   = $customer;
        $this->guestId    = $guestId;
        $this->selections = $this->normalize($selections);
    }

    public function customer(): ?Authenticatable
    {
        return $this->customer;
    }

    public function guestId(): ?string
    {
        return $this->guestId;
    }

    // Cart-keying rule: authenticated customer wins, else session('guest_id') over the AI-passed id.
    public function cartOwner(): array
    {
        if ($this->customer) {
            return [$this->customer->getAuthIdentifier(), 0];
        }

        $guestId = session('guest_id') ?? $this->guestId;

        return $guestId ? [$guestId, 1] : [null, 1];
    }

    // Without setUserResolver a hand-built request has no user, so an API customer would fall through to a guest cart.
    public function stampIdentity(HttpRequest $request): HttpRequest
    {
        if ($this->customer) {
            $request->setUserResolver(fn() => $this->customer);
        }

        return $request;
    }

    public function forProduct(int $productId): ?array
    {
        foreach ($this->selections as $selection) {
            if ((int) ($selection['product_id'] ?? 0) === $productId) {
                return $selection;
            }
        }

        return null;
    }

    private function normalize(?array $selections): array
    {
        if (empty($selections)) {
            return [];
        }

        if (array_key_exists('product_id', $selections)) {
            return [$selections];
        }

        return array_values(array_filter($selections, 'is_array'));
    }
}
