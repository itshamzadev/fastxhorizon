<?php

namespace Modules\AI\app\Tools;

use App\Utils\CartManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Contracts\ProductSuggestionInterface;
use Modules\AI\app\Services\ShoppingAssistant\CartActionCollector;
use Modules\AI\app\Services\ShoppingAssistant\CartSelectionContext;
use Modules\AI\app\Services\ShoppingAssistant\MinimumOrderGuard;
use Modules\AI\app\Services\ShoppingAssistant\ProductCollector;
use Modules\AI\app\Tools\Concerns\MatchesDigitalVariant;
use Modules\AI\app\Tools\Concerns\MatchesVariantOption;
use Modules\AI\app\Tools\Concerns\ResolvesProductStock;

class AddToCartTool implements Tool
{
    use MatchesDigitalVariant;
    use MatchesVariantOption;
    use ResolvesProductStock;

    public function __construct(
        private readonly ProductSuggestionInterface $productSuggestion,
        private readonly ProductCollector           $products,
        private readonly CartActionCollector        $cartAction,
        private readonly CartSelectionContext       $context,
        private readonly MinimumOrderGuard          $minimumOrderGuard,
    ) {}

    public function name(): string
    {
        return 'add_to_cart';
    }

    public function description(): string
    {
        return 'Add a specific product to the customer\'s cart, or start a Buy Now checkout. '
            . 'Call this whenever the customer clearly wants to buy, purchase, order, or add a product to their cart '
            . '("add to cart", "buy it now", "place the order", "I\'ll take it"). '
            . 'Pass the product_id of the product under discussion. '
            . 'When the customer names a color, size/option, or quantity in their message, pass them via color, '
            . 'options and quantity so the item is added directly without another step. '
            . 'When the customer wants SEVERAL DIFFERENT variants of the SAME product in one go '
            . '(e.g. "2 of the 256GB and 3 of the 1TB", or "one black and two white"), pass each as an entry in '
            . 'variations — each with its own color/options/quantity — and every variant is added as its own cart line. '
            . 'For digital products with multiple editions/formats, pass the chosen one via options '
            . '(e.g. {"Format":"PDF-Standard"}). '
            . 'Set buy_now=true for buy-now/checkout phrasing, false for add-to-cart.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->description('ID of the product to add to cart or buy.')->required(),
            'quantity'   => $schema->integer()->description('Quantity the customer asked for (default 1). Ignored when variations is given.'),
            'color'      => $schema->string()->description('Color NAME the customer named, e.g. "Black". Only when they stated a color. Ignored when variations is given.'),
            'options'    => $schema->object()->description('Other chosen variant options as {OptionName: value}, e.g. {"Size":"M","Material":"Cotton"}. Only include options the customer explicitly named. Ignored when variations is given.'),
            'variations' => $schema->array()->items(
                $schema->object([
                    'quantity' => $schema->integer()->description('Quantity for THIS variant (default 1).'),
                    'color'    => $schema->string()->description('Color NAME for this variant, e.g. "Black". Only when a color applies.'),
                    'options'  => $schema->object()->description('This variant\'s options as {OptionName: value}, e.g. {"Storage":"1TB"}. For a digital edition use {"Format":"PDF-Standard"}.'),
                ])
            )->description('Use ONLY when the customer wants several DIFFERENT variants of this same product at once. One entry per variant, each with its own color/options/quantity. Leave empty for a single variant.'),
            'buy_now'    => $schema->boolean()->description('true for Buy Now / immediate checkout, false for Add to Cart.'),
        ];
    }

    public function handle(Request $request): string
    {
        $productId = (int) $request->integer('product_id');
        $buyNow    = (bool) ($request['buy_now'] ?? false);

        $product = $this->productSuggestion->formatById($productId);
        if (!$product) {
            return json_encode(['status' => 'not_found', 'message' => 'Product not found.']);
        }

        $this->products->add(products: [$product], toolName: 'add_to_cart');

        $variations = $this->normalizeVariations($request);
        if (count($variations) > 1) {
            return $this->addMultipleVariations($product, $variations, $buyNow);
        }

        $single = $variations[0] ?? [
            'color'    => isset($request['color']) ? (string) $request['color'] : null,
            'options'  => is_array($request['options'] ?? null) ? $request['options'] : [],
            'quantity' => (int) $request->integer('quantity', 0),
        ];

        return $this->addSingleSelection($product, $single, $buyNow);
    }

    private function addSingleSelection(array $product, array $raw, bool $buyNow): string
    {
        $productId = (int) $product['id'];

        $isDigitalVariant = !empty($product['digital_variations']);
        $variantRequired  = !empty($product['colors']) || !empty($product['choice_options']) || $isDigitalVariant;
        $cardSelection    = $this->matchedSelection($product);
        $argSelection     = $this->resolveArgSelectionValues(
            $product,
            $raw['color'] ?? null,
            $raw['options'] ?? [],
            ((int) ($raw['quantity'] ?? 0)) ?: null,
        );

        if ($isDigitalVariant) {
            $selection = $this->resolveDigitalFlow($product, $raw, $buyNow, $cardSelection, $argSelection);

            if (is_string($selection)) {
                return $selection;
            }
        } elseif ($variantRequired) {
            $merged = $this->mergeSelection($cardSelection, $argSelection);

            if (!empty($argSelection['invalid'])) {
                $this->setSelectVariation($product, $buyNow, $merged);

                return json_encode([
                    'status'    => 'invalid_option',
                    'product'   => $product['name'],
                    'invalid'   => $argSelection['invalid'],
                    'available' => $this->availableOptions($product),
                    'message'   => 'Some options the customer named are not available. Tell them what IS available '
                        . '(from "available") for those options and ask them to choose.',
                ]);
            }

            $missing = $this->missingAttributes($product, $merged);
            if ($missing) {
                if (!$this->hasAnyChoice($merged)) {
                    $this->cartAction->add('select_variation', ['product_id' => $productId, 'buy_now' => $buyNow]);

                    return json_encode([
                        'status'    => 'needs_selection',
                        'product'   => $product['name'],
                        'available' => $this->availableOptions($product),
                        'message'   => 'This product has options. A selection card is shown. If the customer already told you '
                            . 'a color/size/quantity, call add_to_cart again with color/options/quantity; otherwise ask them to '
                            . 'pick the options in "available". Do not pick a variant yourself.',
                    ]);
                }

                $this->setSelectVariation($product, $buyNow, $merged);

                return json_encode([
                    'status'   => 'needs_selection',
                    'product'  => $product['name'],
                    'selected' => $this->describeSelection($product, $merged),
                    'missing'  => $missing,
                    'message'  => 'The card is pre-filled with what the customer chose. Ask ONE short question for the option(s) '
                        . 'in "missing", naming their available choices. Do NOT re-ask anything already in "selected".',
                ]);
            }

            $selection = $merged;
        } else {
            if (!empty($argSelection['invalid'])) {
                return json_encode([
                    'status'  => 'no_variations',
                    'product' => $product['name'],
                    'invalid' => $argSelection['invalid'],
                    'message' => 'This product has no variations or options (no color, size, or format choices) — it is '
                        . 'sold as a single option. Tell the customer it does not come in the "invalid" choice(s) they named '
                        . 'and cannot be customized. Do NOT claim any variant was selected or changed. Offer to add it as-is.',
                ]);
            }

            $selection = $argSelection['qty'] ? ['qty' => $argSelection['qty']] : null;
        }

        $quantity = $this->resolveQuantity($product, $argSelection, $selection);

        // Cart silently raises a sub-minimum request to the MOQ; report the ACTUAL quantity added, not the customer's number.
        $requestedQty    = $argSelection['qty'] ?? ($selection['qty'] ?? null);
        $minimumOrderQty = (int) ($product['minimum_order_qty'] ?? 1);
        $raisedToMinimum = $minimumOrderQty > 1 && ($requestedQty ?? 1) < $minimumOrderQty;

        $available = $this->availableStock($product, $selection);
        if ($available !== null && $quantity > $available) {
            return json_encode([
                'status'    => 'out_of_stock',
                'product'   => $product['name'],
                'available' => $available,
                'requested' => $quantity,
                'message'   => $available > 0
                    ? 'Only "available" unit(s) are in stock — fewer than the "requested" needed'
                        . ($raisedToMinimum ? ' (the minimum order quantity is "minimum_order_qty")' : '')
                        . '. Tell the customer how many are available and ask if they want that many instead.'
                    : 'This product (or the chosen variant) is out of stock. Tell the customer it is currently '
                        . 'unavailable and offer to find a similar product.',
                'minimum_order_qty' => $raisedToMinimum ? $minimumOrderQty : null,
            ]);
        }

        $result = CartManager::add_to_cart($this->buildCartRequest($productId, $quantity, $buyNow, $product, $selection));

        // CartManager status: 0 = rejected, 1 = added, 2 = added but order-wise shipping needs a method at checkout.
        $status = (int) ($result['status'] ?? 0);
        if ($status === 0) {
            return json_encode([
                'status'  => 'failed',
                'message' => $result['message'] ?? 'Could not add this product to the cart.',
            ]);
        }
        $needsShippingSelection = $status === 2;

        if ($buyNow) {
            if ($blocking = $this->minimumOrderGuard->blockingShops()) {
                $this->cartAction->add('minimum_not_met', ['product_id' => $productId, 'shops' => $blocking]);

                return json_encode([
                    'status'  => 'needs_minimum',
                    'product' => $product['name'],
                    'shops'   => $blocking,
                    'message' => 'The product was added, but checkout is NOT available yet: one or more shops are below their '
                        . 'minimum order amount. Each entry in "shops" has the shop name, current subtotal for it (current), its '
                        . 'required minimum (required), and how much more is needed (shortfall). The minimum is per shop, so an '
                        . 'item from a different shop does not help. Do NOT show or mention a checkout button. In one or two '
                        . 'short, friendly sentences, name the short shop(s) and tell the customer to add "shortfall" more from '
                        . 'that shop to continue.',
                ]);
            }

            $this->cartAction->add('checkout', [
                'product_id'               => $productId,
                'url'                      => route('checkout-details'),
                'name'                     => $product['name'],
                'quantity'                 => $quantity,
                'needs_shipping_selection' => $needsShippingSelection,
            ]);

            return json_encode($this->withQuantityNote([
                'status'  => 'buy_now',
                'product' => $product['name'],
                'message' => $needsShippingSelection
                    ? 'The product is in the cart. This shop uses order-wise shipping, so a shipping method must be '
                        . 'chosen on the checkout page. In ONE short sentence, ask the customer to tap "Proceed to checkout" '
                        . 'and pick a shipping method there. Do NOT say the order is fully ready or already placed.'
                    : 'The product is ready for checkout. In ONE short, natural sentence, ask the customer to '
                        . 'confirm by tapping the "Proceed to checkout" button. Be confident — do NOT hedge with phrases '
                        . 'like "should be visible" or describe the UI uncertainly, and do not say they were already redirected.',
            ], $quantity, $requestedQty, $minimumOrderQty, $raisedToMinimum));
        }

        $this->cartAction->add('added', [
            'product_id' => $productId,
            'name'       => $product['name'],
            'quantity'   => $quantity,
            'preselect'  => $this->addedPreselect($selection, $quantity),
        ]);

        return json_encode($this->withQuantityNote([
            'status'  => 'added',
            'product' => $product['name'],
            'message' => 'Added to cart. Confirm to the customer and mention they can keep shopping or check out.',
        ], $quantity, $requestedQty, $minimumOrderQty, $raisedToMinimum));
    }

    private function normalizeVariations(Request $request): array
    {
        $raw = $request['variations'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $out[] = [
                'color'    => isset($entry['color']) ? (string) $entry['color'] : null,
                'options'  => is_array($entry['options'] ?? null) ? $entry['options'] : [],
                'quantity' => (int) ($entry['quantity'] ?? 0),
            ];
        }

        return $out;
    }

    private function addMultipleVariations(array $product, array $variations, bool $buyNow): string
    {
        $productId       = (int) $product['id'];
        $isDigitalVariant = !empty($product['digital_variations']);
        $variantRequired  = !empty($product['colors']) || !empty($product['choice_options']) || $isDigitalVariant;

        if (!$variantRequired) {
            $totalQty = 0;
            foreach ($variations as $entry) {
                $totalQty += max((int) ($entry['quantity'] ?? 0), 0);
            }

            return $this->addSingleSelection(
                $product,
                ['color' => null, 'options' => [], 'quantity' => $totalQty],
                $buyNow,
            );
        }

        $added  = [];
        $issues = [];

        foreach ($variations as $entry) {
            $line = $this->resolveVariationLine($product, $entry, $isDigitalVariant);
            if (!empty($line['issue'])) {
                $issues[] = $line['issue'];
                continue;
            }

            $selection    = $line['selection'];
            $requestedQty = $line['requested_qty'];
            $quantity     = $this->resolveQuantity($product, ['qty' => $requestedQty], $selection);

            $available = $this->availableStock($product, $selection);
            if ($available !== null && $quantity > $available) {
                $issues[] = [
                    'type'      => 'out_of_stock',
                    'variant'   => $this->describeSelection($product, $selection),
                    'available' => $available,
                    'requested' => $quantity,
                ];
                continue;
            }

            $result = CartManager::add_to_cart($this->buildCartRequest($productId, $quantity, $buyNow, $product, $selection));
            if ((int) ($result['status'] ?? 0) === 0) {
                $issues[] = [
                    'type'    => 'failed',
                    'variant' => $this->describeSelection($product, $selection),
                    'reason'  => $result['message'] ?? null,
                ];
                continue;
            }

            $this->cartAction->add('added', [
                'product_id' => $productId,
                'name'       => $product['name'],
                'quantity'   => $quantity,
                'preselect'  => $this->addedPreselect($selection, $quantity),
            ]);

            $added[] = [
                'variant'  => $this->describeSelection($product, $selection),
                'quantity' => $quantity,
            ];
        }

        return $this->multipleVariationsResponse($product, $added, $issues, $buyNow);
    }

    /** @return array{selection?:array, requested_qty?:int|null, issue?:array} */
    private function resolveVariationLine(array $product, array $entry, bool $isDigitalVariant): array
    {
        $color   = $entry['color'] ?? null;
        $options = is_array($entry['options'] ?? null) ? $entry['options'] : [];
        $qty     = ((int) ($entry['quantity'] ?? 0)) ?: null;

        if ($isDigitalVariant) {
            $argVariant = $this->resolveDigitalArgSelectionValues($product, $color, $options);
            if (!empty($argVariant['invalid'])) {
                return ['issue' => [
                    'type'      => 'invalid_option',
                    'invalid'   => $argVariant['invalid'],
                    'available' => ['Format' => $this->digitalLabels($product)],
                ]];
            }
            if (empty($argVariant['variant_key'])) {
                return ['issue' => [
                    'type'      => 'needs_selection',
                    'available' => ['Format' => $this->digitalLabels($product)],
                ]];
            }

            return ['selection' => ['variant_key' => $argVariant['variant_key'], 'qty' => $qty], 'requested_qty' => $qty];
        }

        $arg = $this->resolveArgSelectionValues($product, $color, $options, $qty);
        if (!empty($arg['invalid'])) {
            return ['issue' => [
                'type'      => 'invalid_option',
                'invalid'   => $arg['invalid'],
                'available' => $this->availableOptions($product),
            ]];
        }

        $merged  = ['color' => $arg['color'], 'choices' => $arg['choices'], 'qty' => $arg['qty']];
        $missing = $this->missingAttributes($product, $merged);
        if ($missing) {
            return ['issue' => [
                'type'     => 'needs_selection',
                'selected' => $this->describeSelection($product, $merged),
                'missing'  => $missing,
            ]];
        }

        return ['selection' => $merged, 'requested_qty' => $arg['qty']];
    }

    private function multipleVariationsResponse(array $product, array $added, array $issues, bool $buyNow): string
    {
        $productId = (int) $product['id'];

        if (!$added) {
            return json_encode([
                'status'  => 'failed',
                'product' => $product['name'],
                'issues'  => $issues,
                'message' => 'None of the requested variants could be added. Explain each entry in "issues" to the customer '
                    . '(invalid_option → say what IS available; needs_selection → ask for the missing option; out_of_stock → '
                    . 'give the "available" count) and ask how they want to proceed. Do NOT claim anything was added.',
            ]);
        }

        if ($buyNow) {
            if ($blocking = $this->minimumOrderGuard->blockingShops()) {
                $this->cartAction->add('minimum_not_met', ['product_id' => $productId, 'shops' => $blocking]);

                return json_encode([
                    'status'  => 'needs_minimum',
                    'product' => $product['name'],
                    'added'   => $added,
                    'issues'  => $issues,
                    'shops'   => $blocking,
                    'message' => 'The variant lines in "added" are in the cart, but checkout is NOT available yet: a shop is '
                        . 'below its minimum order amount (see "shops": name, current subtotal, required minimum, shortfall). '
                        . 'Do NOT mention a checkout button. Tell the customer to add "shortfall" more from that shop to continue, '
                        . 'and mention any entries in "issues" that could not be added.',
                ]);
            }

            $this->cartAction->add('checkout', [
                'product_id' => $productId,
                'url'        => route('checkout-details'),
                'name'       => $product['name'],
            ]);
        }

        return json_encode([
            'status'  => $issues ? 'partial' : ($buyNow ? 'buy_now' : 'added'),
            'product' => $product['name'],
            'added'   => $added,
            'issues'  => $issues,
            'message' => 'Each entry in "added" is now its own cart line with its own quantity — confirm to the customer '
                . 'EXACTLY which variant and how many went in (name the variant attributes and quantity for each). '
                . ($issues
                    ? 'Some entries in "issues" could NOT be added — explain each (invalid_option / needs_selection / '
                        . 'out_of_stock) and never imply those were added. '
                    : '')
                . ($buyNow
                    ? 'They are ready for checkout — ask the customer to tap "Proceed to checkout".'
                    : 'Mention they can keep shopping or check out.'),
        ]);
    }

    private function availableStock(array $product, ?array $selection): ?int
    {
        $variantType = $selection ? $this->buildVariationCode($product, $selection) : '';

        return $this->availableStockFor($product, $variantType);
    }

    private function withQuantityNote(array $payload, int $quantity, ?int $requestedQty, int $minimumOrderQty, bool $raisedToMinimum): array
    {
        $payload['quantity'] = $quantity;

        if ($raisedToMinimum) {
            $payload['minimum_order_qty'] = $minimumOrderQty;

            $askedNote = '';
            if ($requestedQty !== null) {
                $payload['requested_quantity'] = (int) $requestedQty;
                $askedNote = ' — NOT the "requested_quantity" the customer asked for';
            }

            $payload['message'] = 'This product has a minimum order quantity of "minimum_order_qty", so "quantity" units '
                . 'were added' . $askedNote . '. Tell the customer clearly that the minimum order quantity is '
                . '"minimum_order_qty", so that many were added. ' . $payload['message'];
        }

        return $payload;
    }

    private function addedPreselect(?array $selection, int $quantity): array
    {
        return [
            'color'       => $selection['color'] ?? null,
            'choices'     => (object) ($selection['choices'] ?? []),
            'variant_key' => $selection['variant_key'] ?? null,
            'qty'         => $quantity,
        ];
    }

    private function resolveDigitalFlow(array $product, array $raw, bool $buyNow, ?array $cardSelection, array $argSelection): array|string
    {
        $cardVariant = (is_array($cardSelection) && !empty($cardSelection['variant_key'])) ? $cardSelection : null;
        $argVariant  = $this->resolveDigitalArgSelectionValues($product, $raw['color'] ?? null, $raw['options'] ?? []);

        $variantKey = $argVariant['variant_key'] ?? ($cardVariant['variant_key'] ?? null);
        $qty        = $argSelection['qty'] ?? ($cardVariant['qty'] ?? null);
        $merged     = ['variant_key' => $variantKey, 'qty' => $qty];

        if (!empty($argVariant['invalid'])) {
            $this->setSelectVariationDigital($product, $buyNow, $merged);

            return json_encode([
                'status'    => 'invalid_option',
                'product'   => $product['name'],
                'invalid'   => $argVariant['invalid'],
                'available' => ['Format' => $this->digitalLabels($product)],
                'message'   => 'The edition/format the customer named is not available. Tell them what IS available '
                    . '(from "available") and ask them to choose.',
            ]);
        }

        if (!$variantKey) {
            $this->cartAction->add('select_variation', ['product_id' => (int) $product['id'], 'buy_now' => $buyNow]);

            return json_encode([
                'status'    => 'needs_selection',
                'product'   => $product['name'],
                'available' => ['Format' => $this->digitalLabels($product)],
                'message'   => 'This product has editions/formats. A selection card is shown. If the customer already named '
                    . 'one, call add_to_cart again with it in options (e.g. {"Format":"PDF-Standard"}); otherwise ask them to '
                    . 'pick from "available". Do not pick one yourself.',
            ]);
        }

        return ['variant_key' => $variantKey, 'qty' => $qty];
    }

    private function resolveDigitalArgSelectionValues(array $product, ?string $color, mixed $options): array
    {
        $variants   = $product['digital_variations'] ?? [];
        $candidates = $this->digitalVariantCandidates($color, $options);

        $variantKey = null;
        foreach ($candidates as $candidate) {
            if ($match = $this->matchDigitalVariant($variants, $candidate)) {
                $variantKey = $match;
                break;
            }
        }

        $invalid = (!$variantKey && !empty($candidates))
            ? array_map(fn($c) => ['option' => 'Format', 'value' => $c], $candidates)
            : [];

        return ['variant_key' => $variantKey, 'invalid' => $invalid];
    }

    private function digitalLabels(array $product): array
    {
        return array_values(array_map(
            fn($v) => $v['label'] ?? $v['variant_key'] ?? '',
            $product['digital_variations'] ?? [],
        ));
    }

    private function setSelectVariationDigital(array $product, bool $buyNow, array $merged): void
    {
        $this->cartAction->add('select_variation', [
            'product_id' => (int) $product['id'],
            'buy_now'    => $buyNow,
            'preselect'  => [
                'variant_key' => $merged['variant_key'] ?? null,
                'qty'         => $merged['qty'] ?? null,
            ],
        ]);
    }

    private function hasAnyChoice(array $selection): bool
    {
        return !empty($selection['color']) || !empty($selection['choices']) || !empty($selection['qty']);
    }

    private function resolveArgSelectionValues(array $product, ?string $colorInput, mixed $optionsInput, ?int $qtyInput): array
    {
        $color   = null;
        $choices = [];
        $invalid = [];

        $rawColor = trim((string) ($colorInput ?? ''));
        if ($rawColor !== '') {
            $match = collect($product['colors'] ?? [])->first(fn($c) =>
                strcasecmp($c['name'] ?? '', $rawColor) === 0 || strcasecmp($c['code'] ?? '', $rawColor) === 0);
            if ($match) {
                $color = $match['code'];
            } elseif ($loose = $this->looseMatchOption($product, $rawColor)) {
                $choices[$loose[0]] = $loose[1];
            } else {
                $invalid[] = ['option' => 'Color', 'value' => $rawColor];
            }
        }

        $rawOptions = $optionsInput ?? [];
        if (is_array($rawOptions)) {
            foreach ($rawOptions as $key => $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $option = collect($product['choice_options'] ?? [])->first(fn($o) =>
                    strcasecmp($o['name'] ?? '', (string) $key) === 0 || strcasecmp($o['title'] ?? '', (string) $key) === 0);
                if (!$option) {
                    if ($loose = $this->looseMatchOption($product, $value)) {
                        $choices[$loose[0]] = $loose[1];
                    } else {
                        $invalid[] = ['option' => (string) $key, 'value' => $value];
                    }
                    continue;
                }
                $canonical = $this->matchOptionValue($option['options'] ?? [], $value);
                $canonical !== null
                    ? $choices[$option['name']] = $canonical
                    : $invalid[] = ['option' => $option['title'] ?? $option['name'], 'value' => $value];
            }
        }

        $qty = (int) ($qtyInput ?? 0);

        return [
            'color'   => $color,
            'choices' => $choices,
            'qty'     => $qty > 0 ? $qty : null,
            'invalid' => $invalid,
        ];
    }

    /** @return array{0:string,1:string}|null */
    private function looseMatchOption(array $product, string $value): ?array
    {
        $hits = [];
        foreach ($product['choice_options'] ?? [] as $option) {
            $canonical = $this->matchOptionValue($option['options'] ?? [], $value);
            if ($canonical !== null) {
                $hits[] = [$option['name'], $canonical];
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    private function mergeSelection(?array $card, array $arg): array
    {
        $card = is_array($card) ? $card : [];

        return [
            'color'   => $arg['color'] ?? ($card['color'] ?? null),
            'choices' => array_merge(is_array($card['choices'] ?? null) ? $card['choices'] : [], $arg['choices'] ?? []),
            'qty'     => $arg['qty'] ?? ($card['qty'] ?? null),
        ];
    }

    private function missingAttributes(array $product, array $selection): array
    {
        $missing   = [];
        $inStock   = $this->inStockSegments($product);

        if (!empty($product['colors']) && empty($selection['color'])) {
            $colors = $this->keepInStockValues(collect($product['colors'])->pluck('name')->all(), $inStock);
            if (!empty($colors)) {
                $missing[] = ['label' => 'Color', 'available' => $colors];
            }
        }
        foreach ($product['choice_options'] ?? [] as $option) {
            if (empty($selection['choices'][$option['name']])) {
                $values = $this->keepInStockValues($option['options'] ?? [], $inStock);
                if (!empty($values)) {
                    $missing[] = ['label' => $option['title'] ?? $option['name'], 'available' => $values];
                }
            }
        }

        return $missing;
    }

    /** @return array<string, true>|null */
    private function inStockSegments(array $product): ?array
    {
        $variations = $product['variation'] ?? [];
        if (empty($variations)) {
            return null;
        }

        $segments = [];
        foreach ($variations as $variation) {
            if ((int) ($variation['qty'] ?? 0) <= 0) {
                continue;
            }
            foreach (explode('-', (string) ($variation['type'] ?? '')) as $segment) {
                $segments[$this->normalizeSegment($segment)] = true;
            }
        }

        return $segments;
    }

    private function keepInStockValues(array $values, ?array $inStock): array
    {
        if ($inStock === null) {
            return array_values($values);
        }

        return array_values(array_filter(
            $values,
            fn($value) => isset($inStock[$this->normalizeSegment((string) $value)]),
        ));
    }

    private function normalizeSegment(string $value): string
    {
        return strtolower(str_replace(' ', '', $value));
    }

    private function setSelectVariation(array $product, bool $buyNow, array $merged): void
    {
        $this->cartAction->add('select_variation', [
            'product_id' => (int) $product['id'],
            'buy_now'    => $buyNow,
            'preselect'  => [
                'color'   => $merged['color'] ?? null,
                'choices' => (object) ($merged['choices'] ?? []),
                'qty'     => $merged['qty'] ?? null,
            ],
        ]);
    }

    private function describeSelection(array $product, array $selection): array
    {
        $out = [];

        if (!empty($selection['color'])) {
            $out['Color'] = collect($product['colors'] ?? [])->firstWhere('code', $selection['color'])['name'] ?? $selection['color'];
        }
        foreach ($selection['choices'] ?? [] as $name => $value) {
            $title = collect($product['choice_options'] ?? [])->firstWhere('name', $name)['title'] ?? $name;
            $out[$title] = $value;
        }
        if (!empty($selection['variant_key'])) {
            $variant = collect($product['digital_variations'] ?? [])->firstWhere('variant_key', $selection['variant_key']);
            $out['Format'] = $variant['label'] ?? $selection['variant_key'];
        }
        if (!empty($selection['qty'])) {
            $out['Quantity'] = (int) $selection['qty'];
        }

        return $out;
    }

    private function availableOptions(array $product): array
    {
        $out     = [];
        $inStock = $this->inStockSegments($product);

        if (!empty($product['colors'])) {
            $colors = $this->keepInStockValues(collect($product['colors'])->pluck('name')->all(), $inStock);
            if (!empty($colors)) {
                $out['Color'] = $colors;
            }
        }
        foreach ($product['choice_options'] ?? [] as $option) {
            $values = $this->keepInStockValues($option['options'] ?? [], $inStock);
            if (!empty($values)) {
                $out[$option['title'] ?? $option['name']] = $values;
            }
        }

        return $out;
    }

    private function resolveQuantity(array $product, array $arg, ?array $selection): int
    {
        $minimum = (int) ($product['minimum_order_qty'] ?? 1);
        $wanted  = $arg['qty'] ?? ($selection['qty'] ?? null);

        return max((int) ($wanted ?: 1), $minimum, 1);
    }

    private function buildCartRequest(int $productId, int $quantity, bool $buyNow, array $product, ?array $selection): HttpRequest
    {
        $params = [
            'id'       => $productId,
            'quantity' => $quantity,
            'buy_now'  => $buyNow ? 1 : 0,
            'guest_id' => $this->context->guestId(),
        ];

        if ($selection) {
            if (!empty($selection['variant_key'])) {
                // Digital products carry a single variant_key — the only field the digital cart path reads (no color/choices/variation code).
                $params['variant_key'] = $selection['variant_key'];
            } else {
                if (!empty($selection['color'])) {
                    $params['color'] = $selection['color'];
                }
                foreach (($selection['choices'] ?? []) as $name => $value) {
                    $params[$name] = $value;
                }
                $params['product_variation_code'] = $this->buildVariationCode($product, $selection);
            }
        }

        // Stamp the authenticated customer so CartManager keys the cart on them; a hand-built request has no user resolver (else it falls to a guest cart).
        return $this->context->stampIdentity(new HttpRequest($params));
    }

    private function matchedSelection(array $product): ?array
    {
        $selection = $this->context->forProduct((int) $product['id']);
        if (!is_array($selection)) {
            return null;
        }
        $hasColor   = !empty($selection['color']);
        $hasChoices = !empty($selection['choices']) && is_array($selection['choices']);
        $hasVariant = !empty($selection['variant_key']);
        $hasQty     = (int) ($selection['qty'] ?? 0) > 0;

        return ($hasColor || $hasChoices || $hasVariant || $hasQty) ? $selection : null;
    }

    private function buildVariationCode(array $product, array $selection): string
    {
        $parts = [];

        if (!empty($selection['color'])) {
            $name = collect($product['colors'] ?? [])->firstWhere('code', $selection['color'])['name'] ?? null;
            if ($name) {
                $parts[] = $name;
            }
        }

        foreach (($product['choice_options'] ?? []) as $option) {
            $value = $selection['choices'][$option['name']] ?? null;
            if ($value !== null && $value !== '') {
                $parts[] = str_replace(' ', '', (string) $value);
            }
        }

        return implode('-', $parts);
    }
}
