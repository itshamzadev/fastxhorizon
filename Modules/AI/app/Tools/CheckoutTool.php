<?php

namespace Modules\AI\app\Tools;

use App\Models\Cart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Services\ShoppingAssistant\CartActionCollector;
use Modules\AI\app\Services\ShoppingAssistant\CartSelectionContext;
use Modules\AI\app\Services\ShoppingAssistant\MinimumOrderGuard;

class CheckoutTool implements Tool
{
    public function __construct(
        private readonly CartActionCollector  $cartAction,
        private readonly MinimumOrderGuard    $minimumOrderGuard,
        private readonly CartSelectionContext $context,
    ) {}

    public function name(): string
    {
        return 'go_to_checkout';
    }

    public function description(): string
    {
        return 'Take the customer to checkout for the items ALREADY in their cart. '
            . 'Call this when they want to check out, pay, place or complete their order, or finish shopping — '
            . '"buy these", "buy them", "checkout", "place my order", "go to checkout", "I\'m done", "proceed". '
            . 'Do NOT call add_to_cart for items that were already added; this only opens checkout for the existing cart.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        // MinimumOrderGuard passes on an empty cart, so guard empty here or checkout renders a button for it.
        if ($this->checkedCartCount() === 0) {
            return json_encode([
                'status'  => 'empty_cart',
                'message' => 'The cart has no items selected for checkout. Tell the customer their cart is empty and '
                    . 'offer to help them find products to add. Do NOT show or mention a checkout button.',
            ]);
        }

        if ($blocking = $this->minimumOrderGuard->blockingShops()) {
            $this->cartAction->add('minimum_not_met', ['shops' => $blocking]);

            return json_encode([
                'status'  => 'needs_minimum',
                'shops'   => $blocking,
                'message' => 'Checkout is NOT available yet: one or more shops in the cart are below their minimum order '
                    . 'amount. Each entry in "shops" gives that shop\'s name, the cart\'s current subtotal for it (current), '
                    . 'its required minimum (required), and how much more is needed (shortfall). Do NOT show or mention a '
                    . 'checkout button. In one or two short, friendly sentences, name the shop(s) that are short and tell the '
                    . 'customer their current total, the minimum, and to add "shortfall" more from that shop to continue. '
                    . 'The minimum is per shop, so do not imply the whole cart total counts toward one shop\'s minimum.',
            ]);
        }

        $this->cartAction->add('checkout', [
            'url'  => route('checkout-details'),
            'name' => null,
        ]);

        return json_encode([
            'status'  => 'checkout',
            'message' => 'A "Proceed to checkout" button is shown to the customer. In ONE short sentence, invite them to '
                . 'tap it to complete their order. Do not list cart contents or re-open any product options.',
        ]);
    }

    private function checkedCartCount(): int
    {
        [$ownerId, $isGuest] = $this->context->cartOwner();

        if (!$ownerId) {
            return 0;
        }

        return Cart::whereHas('product', fn($query) => $query->active())
            ->where(['customer_id' => $ownerId, 'is_guest' => $isGuest, 'is_checked' => 1])
            ->count();
    }
}
