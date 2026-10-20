<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use App\Models\Cart;
use App\Utils\CartManager;
use App\Utils\OrderManager;
use Illuminate\Http\Request as HttpRequest;

class MinimumOrderGuard
{
    public function __construct(
        private readonly CartSelectionContext $context,
    ) {}

    /** @return array<int, array{shop:string,current:string,required:string,shortfall:string}>|null */
    public function blockingShops(): ?array
    {
        [$ownerId, $isGuest] = $this->cartOwner();
        $request = $this->context->stampIdentity(new HttpRequest(($isGuest && $ownerId) ? ['guest_id' => $ownerId] : []));
        if ((int) (OrderManager::verifyCartListMinimumOrderAmount($request)['status'] ?? 1) === 1) {
            return null;
        }

        $shops = $this->shortfallByShop();

        return $shops ?: null;
    }

    /** @return array<int, array{shop:string,current:string,required:string,shortfall:string}> */
    private function shortfallByShop(): array
    {
        if (!getWebConfig(name: 'minimum_order_amount_status')) {
            return [];
        }

        $bySeller   = getWebConfig(name: 'minimum_order_amount_by_seller');
        $inhouseMin = getWebConfig(name: 'minimum_order_amount');

        [$ownerId, $isGuest] = $this->cartOwner();
        if ($ownerId === null) {
            return [];
        }

        $groups = Cart::with(['seller'])
            ->whereHas('product', fn($query) => $query->active())
            ->where(['customer_id' => $ownerId, 'is_guest' => $isGuest, 'is_checked' => 1])
            ->get()
            ->groupBy('cart_group_id');

        $shortfalls = [];

        foreach ($groups as $groupKey => $items) {
            $first   = $items->first();
            $isAdmin = $first->seller_is === 'admin';
            $minimum = $isAdmin ? $inhouseMin : ($bySeller ? ($first->seller->minimum_order_amount ?? 0) : 0);

            if (!$minimum) {
                continue;
            }

            $shippingCost = CartManager::get_shipping_cost(groupId: $groupKey, type: 'checked');
            $subtotal     = CartManager::cart_grand_total(cartGroupId: $groupKey, type: 'checked') - $shippingCost;

            if ($minimum > $subtotal) {
                $shortfalls[] = [
                    'shop'      => $first->shop_info ?: ($isAdmin ? getInHouseShopConfig(key: 'name') : ($first->seller->shop->name ?? '')),
                    'current'   => webCurrencyConverter(amount: $subtotal),
                    'required'  => webCurrencyConverter(amount: $minimum),
                    'shortfall' => webCurrencyConverter(amount: $minimum - $subtotal),
                ];
            }
        }

        return $shortfalls;
    }

    private function cartOwner(): array
    {
        return $this->context->cartOwner();
    }
}
