<?php

use App\Events\AddFundToWalletEvent;
use App\Models\AdminWallet;
use App\Models\Order;
use App\Models\OrderEditHistory;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Utils\Convert;
use App\Utils\CustomerManager;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\DB;
use Modules\TaxModule\app\Models\SystemTaxSetup;
use Modules\TaxModule\app\Models\Tax;

if (!function_exists('digital_payment_success')) {
    function digital_payment_success($paymentData): void
    {
        if (isset($paymentData) && $paymentData['is_paid'] == 1) {
            $additionalData = json_decode($paymentData['additional_data'], true);

            $addCustomer = null;
            $newCustomerInfo = $additionalData['new_customer_info'] ?? null;

            if ($newCustomerInfo) {
                $checkCustomer = User::where(['email' => $newCustomerInfo['email']])->orWhere(['phone' => $newCustomerInfo['phone']])->first();
                if (!$checkCustomer) {
                    $addCustomer = User::create([
                        'name' => $newCustomerInfo['name'],
                        'f_name' => $newCustomerInfo['name'],
                        'l_name' => $newCustomerInfo['l_name'],
                        'email' => $newCustomerInfo['email'],
                        'phone' => $newCustomerInfo['phone'],
                        'is_active' => 1,
                        'password' => bcrypt($newCustomerInfo['password']),
                        'referral_code' => $newCustomerInfo['referral_code'],
                    ]);
                } else {
                    $addCustomer = $checkCustomer;
                }
                session()->put('newRegisterCustomerInfo', $addCustomer);

                if ($additionalData['is_guest']) {
                    $addressId = $additionalData['address_id'] ?? null;
                    $billingAddressId = $additionalData['billing_address_id'] ?? null;
                    ShippingAddress::where(['customer_id' => $additionalData['customer_id'], 'is_guest' => 1, 'id' => $addressId])
                        ->update(['customer_id' => $addCustomer['id'], 'is_guest' => 0]);
                    ShippingAddress::where(['customer_id' => $additionalData['customer_id'], 'is_guest' => 1, 'id' => $billingAddressId])
                        ->update(['customer_id' => $addCustomer['id'], 'is_guest' => 0]);
                }
            }

            session()->put('payment_mode', $additionalData['payment_mode'] ?? 'web');

            if (isset($additionalData['is_guest']) && $additionalData['is_guest'] == 0) {
                $user = User::where(['id' => $additionalData['customer_id']])->first();
                request()->merge(['user' => $user]);
            }

            $requestObj = [
                'customer_id' => $additionalData['customer_id'],
                'is_guest' => $additionalData['is_guest'] ?? 0,
                'guest_id' => ($additionalData['is_guest_in_order'] ?? 0) ? $additionalData['customer_id'] : null,
                'payment_request_from' => $additionalData['payment_mode'] ?? 'web',
            ];
            request()->merge($requestObj);

            $orderIds = OrderManager::generateOrder(data: [
                'is_guest' => $additionalData['is_guest_in_order'] ?? 0,
                'guest_id' => ($additionalData['is_guest_in_order'] ?? 0) ? $additionalData['customer_id'] : null,
                'customer_id' => $additionalData['customer_id'],
                'order_status' => 'confirmed',
                'payment_method' => $paymentData['payment_method'],
                'payment_status' => 'paid',
                'transaction_ref' => $paymentData['transaction_id'],
                'new_customer_id' => $addCustomer ? $addCustomer['id'] : ($additionalData['new_customer_id'] ?? null),
                'newCustomerRegister' => $addCustomer,

                'order_note' => $additionalData['order_note'],
                'coupon_code' => $additionalData['coupon_code'] ?? null,
                'address_id' => $additionalData['address_id'] ?? null,
                'billing_address_id' => $additionalData['billing_address_id'] ?? null,
                'requestObj' => $requestObj,
            ]);

            foreach ($orderIds as $orderId) {
                OrderManager::generateReferBonusForFirstOrder(orderId: $orderId);
            }
        }
    }
}

if (!function_exists('digital_payment_fail')) {
    function digital_payment_fail($payment_data)
    {

    }
}
if (!function_exists('customer_order_edit_pay_due_amount_success')) {
    /**
     * @throws Throwable
     */
    function customer_order_edit_pay_due_amount_success($payment_data): void
    {
        if (!isset($payment_data) || ($payment_data['is_paid'] ?? 0) != 1) {
            return;
        }
        $additionalData = json_decode($payment_data['additional_data'] ?? '{}', true);
        if (empty($additionalData['order_id'])) {
            return;
        }
        $order = Order::where('id', $additionalData['order_id'])->first();
        DB::transaction(function () use ($additionalData, $payment_data, $order) {
            $order->update([
                'edit_due_amount' => 0,
                'order_amount' => $additionalData['order_amount'] ?? 0,
                'payment_status' => 'paid',
            ]);
            OrderEditHistory::where('order_id', $additionalData['order_id'])
                ->latest('id')
                ->limit(1)
                ->update([
                    'order_due_payment_status' => 'paid',
                    'order_due_payment_method' => $payment_data['payment_method'] ?? null,
                    'order_due_transaction_ref' => $payment_data['transaction_id'] ?? '',
                    'order_due_payment_note' => $payment_data['order_due_payment_note'] ?? '',
                ]);
        });
        OrderManager::sendPushNotificationAfterDuePayment(order: $order);
        AdminWallet::where(['admin_id' => 1])->increment('pending_amount', $additionalData['order_amount']);
    }
}
if (!function_exists('customer_order_edit_pay_due_amount_failed')) {
    function customer_order_edit_pay_due_amount_failed($payment_data): void
    {
        if (!isset($payment_data)) {
            return;
        }
        $additionalData = json_decode($payment_data['additional_data'] ?? '', true);
        if (empty($additionalData['order_id'])) {
            return;
        }
        OrderEditHistory::where('order_id', $additionalData['order_id'])
            ->latest('id')
            ->limit(1)
            ->update([
                'order_due_payment_status' => 'unpaid',
                'order_due_transaction_ref' => $payment_data['transaction_id'] ?? '',
            ]);
    }
}

// Add Fund To Wallet - Success
if (!function_exists('add_fund_to_wallet_success')) {
    function add_fund_to_wallet_success($payment_data): void
    {
        if (isset($payment_data) && $payment_data['is_paid'] == 1) {
            $additional_data = json_decode($payment_data['additional_data'], true);
            session()->put('payment_mode', ($additional_data['payment_mode'] ?? 'web'));

            $paymentAmount = Convert::usdPaymentModule(floatval($payment_data['payment_amount']), $payment_data['currency_code']);
            $paymentAmount = usdToDefaultCurrency(amount: $paymentAmount);
            $wallet_transaction = CustomerManager::create_wallet_transaction($payment_data['payer_id'], $paymentAmount, 'add_fund', 'add_funds_to_wallet', $payment_data);

            if ($wallet_transaction) {
                try {
                    $data = [
                        'walletTransaction' => $wallet_transaction,
                        'userName' => $wallet_transaction->user['f_name'],
                        'userType' => 'customer',
                        'templateName' => 'add-fund-to-wallet',
                        'subject' => translate('add_fund_to_wallet'),
                        'title' => translate('add_fund_to_wallet'),
                    ];
                    event(new AddFundToWalletEvent(email: $wallet_transaction->user['email'], data: $data));
                } catch (Exception $ex) {
                    info($ex);
                }
            }
        }
    }
}

// Add Fund To Wallet - Fail
if (!function_exists('add_fund_to_wallet_fail')) {
    function add_fund_to_wallet_fail($payment_data)
    {

    }
}

// Auction Entry Fee - Success
if (!function_exists('auction_entry_fee_success')) {
    function auction_entry_fee_success($payment_data): void
    {
        if (!getCheckAddonPublishedStatus(moduleName: 'Auction')) {
            return;
        }
        if (!isset($payment_data) || $payment_data['is_paid'] != 1) {
            return;
        }

        $additionalData = json_decode($payment_data['additional_data'], true);
        $auctionProductId = $additionalData['auction_product_id'] ?? null;
        $userId = $payment_data['payer_id'];

        if (!$auctionProductId || !$userId) {
            return;
        }

        $alreadyPaid = \Modules\Auction\app\Models\AuctionParticipant::where([
            'auction_product_id' => $auctionProductId,
            'user_id' => $userId,
            'entry_fee_paid_status' => \Modules\Auction\app\Enums\PaymentStatus::PAID,
        ])->exists();

        if ($alreadyPaid) {
            return;
        }

        $wasNew = false;

        DB::transaction(function () use ($userId, $auctionProductId, $payment_data, $additionalData, &$wasNew) {
            $alreadyExists = \Modules\Auction\app\Models\AuctionParticipant::where([
                'auction_product_id' => $auctionProductId,
                'user_id' => $userId,
            ])->exists();

            \Modules\Auction\app\Models\AuctionParticipant::updateOrCreate(
                ['auction_product_id' => $auctionProductId, 'user_id' => $userId],
                [
                    'entry_fee_paid_status' => \Modules\Auction\app\Enums\PaymentStatus::PAID,
                    'entry_fee_paid_amount' => $additionalData['base_amount'] ?? $payment_data['payment_amount'],
                    'entry_fee_payment_method' => $payment_data['payment_method'],
                    'entry_fee_payment_status' => \Modules\Auction\app\Enums\PaymentStatus::VERIFIED,
                    'joined_at' => now(),
                ]
            );

            $wasNew = !$alreadyExists;

            if ($wasNew) {
                \Modules\Auction\app\Models\AuctionProduct::where('id', $auctionProductId)->increment('total_participants');
            }

            \Modules\Auction\app\Models\AuctionTransaction::updateOrCreate(
                ['auction_product_id' => $auctionProductId, 'user_id' => $userId, 'type' => 'entry_fee'],
                [
                    'amount' => $additionalData['base_amount'] ?? 0,
                    'payment_status' => \Modules\Auction\app\Enums\PaymentStatus::PAID,
                    'payment_method' => $payment_data['payment_method'] ?? null,
                    'transaction_ref' => $payment_data['transaction_id'],
                ]
            );
        });

        if ($wasNew) {
            $auctionProduct = \Modules\Auction\app\Models\AuctionProduct::find($auctionProductId);
            if ($auctionProduct) {
                \Modules\Auction\app\Events\AuctionNewParticipationEvent::dispatch(
                    $auctionProduct,
                    (int)$userId,
                    true,
                );
            }
        }
    }
}

// Auction Entry Fee - Fail
if (!function_exists('auction_entry_fee_fail')) {
    function auction_entry_fee_fail($payment_data): void
    {
    }
}

// Auction Claim Payment - Success
if (!function_exists('auction_claim_payment_success')) {
    function auction_claim_payment_success($payment_data): void
    {
        if (!getCheckAddonPublishedStatus(moduleName: 'Auction')) {
            return;
        }
        if (!isset($payment_data) || $payment_data['is_paid'] != 1) {
            return;
        }

        $additionalData = json_decode($payment_data['additional_data'], true);
        $auctionProductId = $additionalData['auction_product_id'] ?? null;
        $userId = $payment_data['payer_id'];
        $winningBidId = $additionalData['winning_bid_id'] ?? null;

        if (!$auctionProductId || !$userId || !$winningBidId) {
            return;
        }

        $alreadyProcessed = \Modules\Auction\app\Models\AuctionTransaction::where([
            'auction_product_id' => $auctionProductId,
            'user_id' => $userId,
            'type' => \Modules\Auction\app\Enums\TransactionType::AUCTION_PAYMENT,
            'payment_status' => \Modules\Auction\app\Enums\PaymentStatus::PAID,
        ])->exists();

        if ($alreadyProcessed) {
            return;
        }

        $auctionProduct = \Modules\Auction\app\Models\AuctionProduct::find($auctionProductId);
        if (!$auctionProduct) {
            return;
        }

        $eligibleBid = \Modules\Auction\app\Models\AuctionBid::find($winningBidId);
        if (!$eligibleBid) {
            return;
        }

        $commission = \Modules\Auction\app\Utils\AuctionCheckoutManager::calculateAuctionAdminCommission(
            product: $auctionProduct,
            eligibleBid: $eligibleBid,
        );

        $shippingAddressId = $additionalData['shipping_address_id'] ?? null;
        $shippingAddressInfo = $additionalData['shipping_address_info'] ?? null;
        $billingAddressId = $additionalData['billing_address_id'] ?? null;
        $billingAddressInfo = $additionalData['billing_address_info'] ?? null;

        DB::transaction(function () use (
            $userId, $auctionProduct, $eligibleBid, $payment_data, $additionalData, $commission,
            $shippingAddressId, $shippingAddressInfo, $billingAddressId, $billingAddressInfo,
        ) {
            $claimTransaction = \Modules\Auction\app\Models\AuctionTransaction::updateOrCreate([
                'auction_product_id' => $auctionProduct->id,
                'user_id' => $userId,
                'type' => \Modules\Auction\app\Enums\TransactionType::AUCTION_PAYMENT,
            ], [
                'bid_id' => $eligibleBid->id,
                'amount' => $additionalData['base_amount'] ?? 0,
                'payment_method' => $payment_data['payment_method'] ?? null,
                'payment_status' => \Modules\Auction\app\Enums\PaymentStatus::PAID,
                'transaction_ref' => $payment_data['transaction_id'],
            ]);

            $auctionProduct->update([
                'winner_user_id' => $userId,
                'winning_bid_id' => $eligibleBid->id,
                'admin_commission' => $commission,
                'delivery_status' => null,
                'shipping_address_id' => $shippingAddressId,
                'shipping_address_info' => $shippingAddressInfo,
                'billing_address_id' => $billingAddressId,
                'billing_address_info' => $billingAddressInfo,
            ]);

            \Modules\Auction\app\Utils\AuctionCheckoutManager::persistAuctionClaimTaxDetails(
                auctionProduct: $auctionProduct,
                claimTransaction: $claimTransaction,
                generatedData: \Modules\Auction\app\Utils\AuctionCheckoutManager::processAuctionClaimGenerateData(
                    auction: $auctionProduct,
                    bid: $eligibleBid,
                ),
            );
        });

        \Modules\Auction\app\Events\AuctionItemClaimedEvent::dispatch($auctionProduct);
        cacheRemoveByType(type: 'auction_products');

    }
}

// Auction Claim Payment - Fail
if (!function_exists('auction_claim_payment_fail')) {
    function auction_claim_payment_fail($payment_data): void
    {
    }
}

// Auction COD Commission Payment - Success (digital gateway callback)
if (!function_exists('auction_cod_commission_success')) {
    function auction_cod_commission_success($payment_data): void
    {
        if (!getCheckAddonPublishedStatus(moduleName: 'Auction')) {
            return;
        }
        if (!isset($payment_data) || $payment_data['is_paid'] != 1) {
            return;
        }

        $additionalData = json_decode($payment_data['additional_data'] ?? '', true);
        $auctionProductId = $additionalData['auction_product_id'] ?? null;
        $userId = $payment_data['payer_id'];

        if (!$auctionProductId || !$userId) {
            return;
        }

        $auctionProduct = \Modules\Auction\app\Models\AuctionProduct::find($auctionProductId);
        if (!$auctionProduct) {
            return;
        }

        DB::transaction(function () use ($userId, $auctionProductId, $auctionProduct, $payment_data) {
            OrderManager::getCheckOrCreateAdminWallet();
            $adminWallet = AdminWallet::where('admin_id', 1)->first();
            $adminWallet->commission_earned += (float)$auctionProduct['admin_commission'];
            $adminWallet->save();

            \Modules\Auction\app\Models\AuctionTransaction::updateOrCreate(
                [
                    'auction_product_id' => $auctionProductId,
                    'user_id' => $userId,
                    'type' => \Modules\Auction\app\Enums\TransactionType::COMMISSION_PAYMENT,
                ],
                [
                    'amount' => $auctionProduct['admin_commission'],
                    'payment_method' => $payment_data['payment_method'] ?? null,
                    'payment_status' => \Modules\Auction\app\Enums\PaymentStatus::PAID,
                    'transaction_ref' => $payment_data['transaction_id'] ?? null,
                ]
            );

            \Modules\Auction\app\Models\AuctionCommissionPayment::updateOrCreate(
                [
                    'auction_product_id' => $auctionProductId,
                    'owner_type' => $auctionProduct->owner_type,
                    'owner_id' => $auctionProduct->owner_id,
                ],
                [
                    'amount' => $auctionProduct['admin_commission'],
                    'payment_method' => $payment_data['payment_method'] ?? null,
                    'payment_status' => \Modules\Auction\app\Enums\CommissionPaymentStatus::PAID,
                    'transaction_ref' => $payment_data['transaction_id'] ?? null,
                ]
            );

            \Modules\Auction\app\Models\AuctionProduct::where('id', $auctionProductId)
                ->update(['admin_commission_given' => true]);
        });

        \Modules\Auction\app\Events\AuctionCommissionPaymentVerifiedEvent::dispatch($auctionProduct);
    }
}

// Auction COD Commission Payment - Fail (digital gateway callback)
if (!function_exists('auction_cod_commission_fail')) {
    function auction_cod_commission_fail($payment_data): void
    {
        if (!getCheckAddonPublishedStatus(moduleName: 'Auction')) {
            return;
        }
        if (!isset($payment_data)) {
            return;
        }

        $additionalData = json_decode($payment_data['additional_data'] ?? '', true);
        $auctionProductId = $additionalData['auction_product_id'] ?? null;
        $userId = $payment_data['payer_id'] ?? null;

        if (!$auctionProductId || !$userId) {
            return;
        }

        \Modules\Auction\app\Models\AuctionTransaction::updateOrCreate(
            [
                'auction_product_id' => $auctionProductId,
                'user_id' => $userId,
                'type' => \Modules\Auction\app\Enums\TransactionType::COMMISSION_PAYMENT,
            ],
            [
                'amount' => $additionalData['base_amount'] ?? 0,
                'payment_method' => $payment_data['payment_method'] ?? null,
                'payment_status' => \Modules\Auction\app\Enums\PaymentStatus::DENIED,
                'transaction_ref' => $payment_data['transaction_id'] ?? null,
            ]
        );
    }
}

// Auction Seller Commission Payment - Success (digital gateway callback)
if (!function_exists('auction_commission_payment_success')) {
    function auction_commission_payment_success($payment_data): void
    {
        if (!getCheckAddonPublishedStatus(moduleName: 'Auction')) {
            return;
        }
        if (!isset($payment_data) || $payment_data['is_paid'] != 1) {
            return;
        }

        $additionalData = json_decode($payment_data['additional_data'] ?? '', true);
        $auctionProductId = $additionalData['auction_product_id'] ?? null;
        $sellerId = $payment_data['payer_id'] ?? null;

        if (!$auctionProductId || !$sellerId) {
            return;
        }

        DB::transaction(function () use ($sellerId, $auctionProductId, $payment_data, $additionalData) {
            \Modules\Auction\app\Models\AuctionCommissionPayment::updateOrCreate(
                [
                    'auction_product_id' => $auctionProductId,
                    'owner_type' => \Modules\Auction\app\Enums\OwnerType::SELLER,
                    'owner_id' => $sellerId,
                ],
                [
                    'amount' => $additionalData['base_amount'] ?? 0,
                    'payment_method' => $payment_data['payment_method'] ?? null,
                    'payment_status' => \Modules\Auction\app\Enums\CommissionPaymentStatus::PAID,
                    'transaction_ref' => $payment_data['transaction_id'] ?? null,
                ]
            );

            \Modules\Auction\app\Models\AuctionProduct::where('id', $auctionProductId)
                ->update(['admin_commission_given' => true]);
        });
    }
}

// Auction Seller Commission Payment - Fail (digital gateway callback)
if (!function_exists('auction_commission_payment_fail')) {
    function auction_commission_payment_fail($payment_data): void
    {
    }
}

if (!function_exists('config_settings')) {
    function config_settings($key, $settings_type)
    {
        try {
            $config = DB::table('addon_settings')->where('key_name', $key)
                ->where('settings_type', $settings_type)->first();
        } catch (Exception $exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('getCheckAddonPublishedStatus')) {
    function getCheckAddonPublishedStatus(string $moduleName): int
    {
        try {
            if(in_array($moduleName, ['AI', 'Blog', 'Courier', 'TaxModule'])) {
               return 1;
            }
            $statusFile = base_path('modules_statuses.json');
            if (file_exists($statusFile)) {
                $statuses = json_decode((string)file_get_contents($statusFile), true);
                if (is_array($statuses) && array_key_exists($moduleName, $statuses) && $statuses[$moduleName] !== true) {
                    return 0;
                }
            }

            if (file_exists(base_path("Modules/{$moduleName}/Addon/info.php"))) {
                $full_data = include(base_path("Modules/{$moduleName}/Addon/info.php"));
                return $full_data['is_published'] == 1 ? 1 : 0;
            }
        } catch (Exception $exception) {
        }
        return 0;
    }
}

if (!function_exists('addon_published_status')) {
    function addon_published_status(string $moduleName): bool
    {
        return getCheckAddonPublishedStatus(moduleName: $moduleName) === 1;
    }
}

if (!function_exists('getTaxModuleSystemTypesConfig')) {
    function getTaxModuleSystemTypesConfig($getTaxVatList = true, $tax_payer = 'vendor'): array
    {
        $cacheKey = "tax_system_type_{$tax_payer}_" . ($getTaxVatList ? 'with_vat' : 'no_vat');

        $cacheKeys = Cache::get('cache_tax_system_types_and_config', []);
        if (!in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            Cache::put('cache_tax_system_types_and_config', $cacheKeys, 60 * 60 * 24 * 7);
        }

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($getTaxVatList, $tax_payer) {
            if (getCheckAddonPublishedStatus('TaxModule')) {
                $systemTaxVat = SystemTaxSetup::where('is_active', 1)
                    ->with(['additionalData' => function ($query) {
                        return $query->where('is_active', 1);
                    }])
                    ->where('tax_payer', $tax_payer)
                    ->where('is_default', 1)
                    ->first();

                if (!$systemTaxVat) {
                    $systemTaxVat = SystemTaxSetup::create([
                        'tax_type' => 'order_wise',
                        'country_code' => null,
                        'tax_payer' => 'vendor',
                        'is_default' => true,
                        'is_active' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return [
                        'SystemTaxVat' => $systemTaxVat ?? null,
                        'SystemTaxVatType' => $systemTaxVat?->tax_type ?? 'order_wise',
                        'is_included' => $systemTaxVat?->is_included ?? 0,
                        'productWiseTax' => false,
                        'categoryWiseTax' => false,
                        'taxVats' => []
                    ];
                }

                if ($getTaxVatList) {
                    $taxVats = Tax::where('is_active', 1)->where('is_default', 1)->get();
                }

                if ($systemTaxVat?->tax_type == 'product_wise') {
                    $productWiseTax = true;
                } elseif ($systemTaxVat?->tax_type == 'category_wise') {
                    $categoryWiseTax = true;
                }
            }

            return [
                'SystemTaxVat' => $systemTaxVat ?? null,
                'SystemTaxVatType' => $systemTaxVat?->tax_type ?? 'order_wise',
                'is_included' => $systemTaxVat?->is_included ?? 0,
                'productWiseTax' => $productWiseTax ?? false,
                'categoryWiseTax' => $categoryWiseTax ?? false,
                'taxVats' => $taxVats ?? []
            ];
        });
    }
}

if (!function_exists('courierService')) {
    function courierService(): ?object
    {
        return addon_published_status('Courier')
            && class_exists(\Modules\Courier\app\Services\CourierService::class)
            && app()->bound(\Modules\Courier\app\Services\CourierService::class)
                ? app(\Modules\Courier\app\Services\CourierService::class)
                : null;
    }
}

if (!function_exists('deliveryPartnerServiceAvailable')) {
    function deliveryPartnerServiceAvailable(): bool
    {
        return (bool)getWebConfig('third_party_delivery_service') && courierService() !== null;
    }
}

if (!function_exists('deliveryPartnerConfigAvailable')) {
    
    function deliveryPartnerConfigAvailable(): bool
    {
        return courierService() !== null;
    }
}

if (!function_exists('deliveryPartnerAvailable')) {
    function deliveryPartnerAvailable(object|array|null $order, string $panel): bool
    {
        if (empty($order)) {
            return false;
        }

        $isVendorResponsible = ($order['shipping_responsibility'] ?? null) === 'sellerwise_shipping'
            && ($order['seller_is'] ?? null) === 'seller';

        if ($isVendorResponsible && !vendorDeliveryPartnerSetupAvailable()) {
            return false;
        }

        return deliveryPartnerServiceAvailable()
            && $panel === ($isVendorResponsible ? 'vendor' : 'admin')
            && (bool)courierService()?->isEnabled();
    }
}

if (!function_exists('vendorDeliveryPartnerConfigAvailable')) {
    function vendorDeliveryPartnerConfigAvailable(): bool
    {
        return deliveryPartnerConfigAvailable()
            && (getWebConfig('vendor_delivery_partner_setup') ?? 1);
    }
}

if (!function_exists('vendorDeliveryPartnerSetupAvailable')) {
    function vendorDeliveryPartnerSetupAvailable(): bool
    {
        return deliveryPartnerServiceAvailable()
            && vendorDeliveryPartnerConfigAvailable();
    }
}

if (!function_exists('deliveryPartnerShipment')) {
    function deliveryPartnerShipment(string|int $orderId): ?array
    {
        return courierService()?->summaryFor((string)$orderId);
    }
}

if (!function_exists('deliveryPartnerAssigned')) {
    function deliveryPartnerAssigned(object|array|null $order): bool
    {
        if (empty($order)) {
            return false;
        }

        if (!empty($order['id']) && deliveryPartnerShipment($order['id']) !== null) {
            return true;
        }

        return ($order['delivery_type'] ?? null) === 'third_party_delivery'
            && (filled($order['delivery_service_name'] ?? null) || filled($order['third_party_delivery_tracking_id'] ?? null));
    }
}

if (!function_exists('deliveryPartnerTrackingInfo')) {
    function deliveryPartnerTrackingInfo(object|null $order): ?array
    {
        if (empty($order)) {
            return null;
        }

        $instruction = translate('This_order_is_being_delivered_by_a_3rd_party_delivery_partner_Use_the_tracking_details_above_on_the_delivery_partner_website_or_app_to_follow_your_parcel') . '. '
            . translate('For_delivery_time_rescheduling_or_any_delivery_issue_contact_the_delivery_partner_directly_with_these_tracking_details') . '.';

        $shipment = deliveryPartnerShipment($order->id);

        if ($shipment !== null) {
            return [
                'delivery_partner' => $shipment['provider_label'],
                'tracking_number' => $shipment['tracking_number'],
                'shipment_status' => $shipment['status'],
                'shipment_status_label' => $shipment['status_label'],
                'tracking_url' => $shipment['tracking_url'],
                'instruction' => $instruction,
            ];
        }

        if (blank($order->delivery_service_name) && blank($order->third_party_delivery_tracking_id)) {
            return null;
        }

        return [
            'delivery_partner' => $order->delivery_service_name,
            'tracking_number' => $order->third_party_delivery_tracking_id,
            'shipment_status' => null,
            'shipment_status_label' => null,
            'tracking_url' => null,
            'instruction' => $instruction,
        ];
    }
}

if (!function_exists('deliveryPartnerShipmentDetails')) {
    function deliveryPartnerShipmentDetails(string|int $orderId): ?array
    {
        return courierService()?->shipmentDetailsFor((string)$orderId);
    }
}

if (!function_exists('getModuleDynamicAsset')) {
    function getModuleDynamicAsset(string $path): string
    {
        if (getModuleAssetsProcessingDirectory() == 'public') {
            $position = strpos($path, 'public/');
            $result = $path;
            if ($position === 0) {
                $result = preg_replace('/public/', '', $path, 1);
            }
        } else {
            $result = $path;
        }
        return asset($result);
    }
}

if (!function_exists('getModuleDynamicStorage')) {
    function getModuleDynamicStorage(string $path): string|null
    {
        if (getModuleAssetsProcessingDirectory() == 'public') {
            $result = str_replace('storage/app/public', 'storage', $path);
        } else {
            $result = $path;
        }
        return is_file($result) ? asset($result) : '';
    }
}

if (!function_exists('getModuleAssetsProcessingDirectory')) {
    function getModuleAssetsProcessingDirectory(): string
    {
        $cacheKey = 'SYSTEM_DOMAIN_POINTED_DIRECTORY_' . md5($_SERVER['SCRIPT_FILENAME']);
        return Cache::rememberForever($cacheKey, function () {
            $scriptPath = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
            $basePath = realpath(base_path());
            $publicPath = realpath(public_path());

            if ($scriptPath === $publicPath) {
                return 'public';
            } elseif ($scriptPath === $basePath) {
                return 'root';
            }
            return 'public';
        });
    }
}
