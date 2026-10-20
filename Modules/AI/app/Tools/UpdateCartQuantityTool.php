<?php

namespace Modules\AI\app\Tools;

use App\Models\Cart;
use App\Utils\CartManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Contracts\ProductSuggestionInterface;
use Modules\AI\app\Services\ShoppingAssistant\CartActionCollector;
use Modules\AI\app\Services\ShoppingAssistant\CartSelectionContext;
use Modules\AI\app\Tools\Concerns\MatchesDigitalVariant;
use Modules\AI\app\Tools\Concerns\MatchesVariantOption;
use Modules\AI\app\Tools\Concerns\ResolvesProductStock;

class UpdateCartQuantityTool implements Tool
{
    use MatchesDigitalVariant;
    use MatchesVariantOption;
    use ResolvesProductStock;

    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
        private readonly CartActionCollector        $cartAction,
        private readonly CartSelectionContext       $context,
    ) {}

    public function name(): string
    {
        return 'update_cart_quantity';
    }

    public function description(): string
    {
        return 'Change the quantity of a product that is ALREADY in the customer\'s cart. '
            . 'Call this whenever the customer wants to set, increase, decrease, or change how many of a '
            . 'product they have ("make it 5", "update the quantity to 3", "I want 2 of those instead"). '
            . 'Pass the product_id and the new total quantity. Do NOT call add_to_cart for a quantity change — '
            . 'that re-opens the variant card and does not update the cart. '
            . 'If the product has variants and more than one is in the cart, also pass color/options to say which line. '
            . 'For a digital product, pass the chosen edition/format name (e.g. "video-5") via options to identify the line. '
            . 'If the customer asks to ADD N more, use the line\'s current quantity (from a prior needs_selection "variants" '
            . 'list) plus N as the new total.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->description('ID of the product whose cart quantity to change.')->required(),
            'quantity'   => $schema->integer()->description('The new TOTAL quantity for that cart line.')->required(),
            'color'      => $schema->string()->description('Color NAME, only if needed to identify which variant line to update.'),
            'options'    => $schema->object()->description('Other variant options as {OptionName: value}, only to identify the line.'),
        ];
    }

    public function handle(Request $request): string
    {
        $productId = (int) $request->integer('product_id');
        $quantity  = (int) $request->integer('quantity');

        $product = $this->productSuggestion->formatById($productId);
        if (!$product) {
            return json_encode(['status' => 'not_found', 'message' => 'Product not found.']);
        }

        if ($quantity < 1) {
            return json_encode([
                'status'  => 'invalid_quantity',
                'product' => $product['name'],
                'message' => 'Ask the customer how many they want — the quantity must be at least 1. '
                    . 'To remove the item, they can use the cart instead.',
            ]);
        }

        // update_cart_qty does NOT enforce MOQ (only the add path does), so raise a sub-minimum request here and report the true quantity.
        $minimumOrderQty = (int) ($product['minimum_order_qty'] ?? 1);
        $requestedQty    = $quantity;
        $raisedToMinimum = $quantity < $minimumOrderQty;
        if ($raisedToMinimum) {
            $quantity = $minimumOrderQty;
        }

        [$ownerId, $isGuest] = $this->cartOwner();
        if ($ownerId === null) {
            return json_encode([
                'status'  => 'not_in_cart',
                'product' => $product['name'],
                'message' => 'There is no cart yet. Tell the customer the item is not in their cart and offer to add it.',
            ]);
        }

        $rows = Cart::where(['product_id' => $productId, 'customer_id' => $ownerId, 'is_guest' => $isGuest])->get();
        if ($rows->isEmpty()) {
            return json_encode([
                'status'  => 'not_in_cart',
                'product' => $product['name'],
                'message' => 'This product is NOT in the cart, so there is nothing to update. Do NOT claim the quantity '
                    . 'changed. Offer to add it to the cart instead.',
            ]);
        }

        $target = $this->resolveTargetRow($rows, $product, $request);
        if ($target === null) {
            return json_encode([
                'status'   => 'needs_selection',
                'product'  => $product['name'],
                'variants' => $rows->map(fn($row) => [
                    'variant'  => $row->variant,
                    'quantity' => (int) $row->quantity,
                ])->values()->all(),
                'message'  => 'There are multiple variants of this product in the cart. Ask which one '
                    . '(name the variant names from "variants") before changing the quantity. Each entry shows that '
                    . 'line\'s current "quantity" — if the customer asked to add N more, the new total is that quantity + N.',
            ]);
        }

        // Stock pre-check against ACTUAL inventory: update_cart_qty echoes the current cart quantity (not real stock) on a cap, so quote resolved stock.
        $available = $this->availableStockFor($product, (string) $target->variant);
        if ($available !== null && $quantity > $available) {
            return json_encode([
                'status'    => 'out_of_stock',
                'product'   => $product['name'],
                'available' => $available,
                'requested' => $quantity,
                'message'   => $available > 0
                    ? 'The quantity was NOT changed: only "available" unit(s) are in stock, fewer than the "requested" '
                        . 'total. Tell the customer how many are available and offer to set the quantity to that many.'
                    : 'The quantity was NOT changed: this product is currently out of stock. Tell the customer it is '
                        . 'unavailable right now.',
            ]);
        }

        $result = CartManager::update_cart_qty($this->context->stampIdentity(new HttpRequest([
            'key'      => $target->id,
            'quantity' => $quantity,
            'buy_now'  => 0,
            'guest_id' => $isGuest ? $ownerId : null,
        ])));

        if ((int) ($result['status'] ?? 0) !== 1) {
            return json_encode([
                'status'    => 'failed',
                'product'   => $product['name'],
                'available' => $available,
                'message'   => $available !== null
                    ? 'The quantity was NOT changed — only ' . $available . ' unit(s) are available. Tell the customer it '
                        . 'was not changed and how many are available.'
                    : ($result['message'] ?? 'Could not update the quantity. Tell the customer it was not changed.'),
            ]);
        }

        $this->cartAction->add('updated', [
            'product_id' => $productId,
            'name'       => $product['name'],
            'quantity'   => $quantity,
        ]);

        $response = [
            'status'   => 'updated',
            'product'  => $product['name'],
            'quantity' => $quantity,
            'message'  => 'The cart quantity is now ' . $quantity . '. Confirm this to the customer in one short sentence.',
        ];

        if ($raisedToMinimum) {
            $response['requested_quantity'] = $requestedQty;
            $response['minimum_order_qty']  = $minimumOrderQty;
            $response['message'] = 'The customer asked for ' . $requestedQty . ', but this product has a minimum order '
                . 'quantity of ' . $minimumOrderQty . ', so the cart line is now ' . $quantity . '. Tell the customer the '
                . 'minimum order quantity is ' . $minimumOrderQty . ', so that is the quantity set.';
        }

        return json_encode($response);
    }

    private function cartOwner(): array
    {
        return $this->context->cartOwner();
    }

    /** @param \Illuminate\Support\Collection<int, Cart> $rows */
    private function resolveTargetRow($rows, array $product, Request $request): ?Cart
    {
        if ($rows->count() === 1) {
            return $rows->first();
        }

        $wanted = $this->buildVariantString($product, $request);
        if ($wanted === '') {
            return null;
        }

        return $rows->first(fn($row) => strcasecmp((string) $row->variant, $wanted) === 0);
    }

    private function buildVariantString(array $product, Request $request): string
    {
        // Digital line: the cart `variant` IS the variant_key, not the color/choice composite physical products use.
        if (!empty($product['digital_variations'])) {
            return (string) $this->resolveDigitalVariantKey(
                $product['digital_variations'],
                $request['color'] ?? null,
                $request['options'] ?? [],
            );
        }

        $parts = [];

        $rawColor = trim((string) ($request['color'] ?? ''));
        if ($rawColor !== '' && !empty($product['colors'])) {
            $match = collect($product['colors'])->first(fn($c) =>
                strcasecmp($c['name'] ?? '', $rawColor) === 0 || strcasecmp($c['code'] ?? '', $rawColor) === 0);
            if ($match) {
                $parts[] = $match['name'];
            }
        }

        $rawOptions = is_array($request['options'] ?? null) ? $request['options'] : [];
        foreach ($product['choice_options'] ?? [] as $option) {
            $value = trim((string) ($rawOptions[$option['name']] ?? $rawOptions[$option['title'] ?? ''] ?? ''));
            if ($value === '') {
                continue;
            }
            $canonical = $this->matchOptionValue($option['options'] ?? [], $value);
            if ($canonical !== null) {
                $parts[] = str_replace(' ', '', $canonical);
            }
        }

        return implode('-', $parts);
    }
}
