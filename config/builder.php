<?php

/*
|--------------------------------------------------------------------------
| Builder addon configuration (host-owned)
|--------------------------------------------------------------------------
|
| This file is owned by the HOST project, not the Builder module. The module
| stays identical across every project and only READS these keys via
| config('builder.*') (each read carries an inline default, so a missing key
| never fatals). Define / override the addon's behaviour for THIS project here.
|
| Laravel auto-loads every file in this directory, so no service-provider
| merge is needed — these values are authoritative.
|
*/

return [
    'cms_dashboard_route' => env('BUILDER_CMS_DASHBOARD_ROUTE', 'vendor.dashboard.index'),
    'default_platform_name'  => '6valley',

    /*
     * Master switch for storefront wallet-family features: wallet payment,
     * partial payment, loyalty points, referral, and wallet cashback. When
     * false, the storefront hides all of those UI affordances and the
     * matching endpoints return 404. Host wallet logic is untouched —
     * balances stay in the database and re-appear if the flag is flipped
     * back on. Admin / vendor / mobile API V1 are unaffected.
     */
    'wallet_features_enabled' => false,

    /*
     * 6Valley computes the storefront delivery fee from its admin/seller
     * "Shipping Method" setting (order-wise / category-wise / product-wise).
     * Order-wise needs a ShippingMethod chosen per cart group; the legacy site
     * has a picker, the storefront does not — so when true the CheckoutProvider
     * auto-selects the cheapest applicable order-wise method so the fee is
     * charged instead of reading 0. 6amMart uses zone/distance-based delivery,
     * not this flow, so it sets this false.
     */
    'order_wise_shipping_auto_select' => true,

    /*
     * Storefront social login (Google / Facebook / Apple). Disabled for now:
     * the providers gate by registered origin / redirect-URI, which doesn't
     * work across arbitrary vendor sub-domains / custom domains without a
     * central auth broker. When false, the storefront hides all social buttons
     * and the `storefront.auth.social` endpoint 404s. Email/phone + OTP login
     * are unaffected. Flip back to true once the broker flow exists.
     */
    'social_login_enabled' => false,

    /*
     * Master switch for ALL outbound email triggered by a storefront request
     * (customer registration, email-verification OTP, password reset, order
     * placement / verification, wallet & refund notifications, …). When false,
     * the `SuppressStorefrontMail` middleware cancels every mail sent during a
     * storefront request (all 6amMart mailables are synchronous, so nothing
     * escapes to a queue worker) — so no storefront email goes out.
     *
     * Scope is the storefront ONLY: admin, vendor panel, and mobile API mail
     * are untouched (their routes don't carry this middleware). Flip to false
     * to run storefronts silently (e.g. white-label sites that handle their own
     * transactional email, or staging domains that shouldn't email real users).
     */
    'storefront_mail_enabled' => true,

    /*
     * Host capability manifest — the single place a host declares WHICH features
     * the storefront + builder should render and enforce, so the same addon
     * adapts per project. Read via Modules\Builder\Contracts\CapabilityProvider
     * (host adapter may also DERIVE data-driven flags). Every value here is the
     * 6amMart baseline = its current implicit behavior; other hosts override.
     *
     * Read in PHP:  app(CapabilityProvider)->capabilities($scope)->enabled('location.map')
     * Gate a route: ->middleware(RequireCapability::class.':features.wallet')
     * Read in JS:   useCapability('payment.cod')
     */
    'capabilities' => [
        'schemaVersion' => 1,

        // Storefront profile-edit field editability. Phone is the account's
        // login identity → locked; email is editable. Read on the client
        // (EditProfileModal disables the field) and enforced server-side in
        // CustomerAuthProvider::updateProfile.
        'profile' => [
            'phoneEditable' => false,
            'emailEditable' => false,
        ],

        // Business-model / item presentation.
        // itemPresentation: 'auto' (food→modal, else page) | 'modal' | 'page'.
        'modules' => ['mode' => 'multi', 'switcher' => true, 'itemPresentation' => 'auto'],

        // Currency. Only 'single' is implemented today; 'multi' is reserved.
        'currency' => ['mode' => 'single', 'switcher' => false],

        // Location / map / address book.
        'location' => [
            'enabled' => false, 'map' => true, 'currentLocation' => false,
            'zoneBased' => false, 'savedAddresses' => true,
            // Show the map in the address form but don't force a pin — the
            // typed delivery address is authoritative.
            'mapPickRequired' => false,
            // Extra address inputs rendered under name/phone in the add/edit
            // address form, in order. Each maps to a shipping_addresses column
            // the host persists (LocationProvider::fill). `half` pairs two
            // fields on one row. 6Valley uses city/state/zip/country (it has no
            // road/house/floor columns); 6amMart overrides with those.
            // Show the email input on every address (not just guest checkout).
            // False for 6Valley: guests still get email via the guest-checkout
            // path, but saved addresses don't collect it.
            'addressEmail' => false,
            // Address inputs under name/phone. `enabled: false` keeps a field
            // declared-but-off (documented + easy to re-enable) without
            // rendering it. `half` pairs two fields on one row.
            'addressFields' => [
                ['key' => 'country', 'label' => 'address_form_country', 'half' => false, 'enabled' => true],
                ['key' => 'city',    'label' => 'address_form_city',    'half' => true,  'enabled' => true],
                ['key' => 'zip',     'label' => 'address_form_zip',     'half' => true,  'enabled' => true],
                ['key' => 'road',    'label' => 'address_form_street',  'half' => false, 'enabled' => false],
                ['key' => 'house',   'label' => 'address_form_house',   'half' => true,  'enabled' => false],
                ['key' => 'floor',   'label' => 'address_form_floor',   'half' => true,  'enabled' => false],
            ],
        ],

        // Checkout surface.
        'checkout' => [
            'deliveryTypes' => ['home', 'takeaway', 'schedule'],
            'tips' => false, 'tipPresets' => [10, 15, 20, 40],
            'extraPackaging' => true, 'coupon' => true,
            'unavailableNote' => false, 'deliveryInstruction' => false,
            'orderNote' => false, 'savedAddress' => true,
            // Whether a guest (not logged-in) may apply a coupon. False for
            // 6valley: guests get no coupon suggestions and coupon apply is
            // rejected server-side (CouponProvider). Logged-in customers are
            // unaffected. Coupon feature itself is still gated by `coupon` above.
            'guestCoupon' => false,
        ],

        // Payment rails + flow. timing: 'after' (place→pay) is the only mode
        // implemented today; 'before' is reserved for a future pre-auth flow.
        'payment' => [
            'cod' => true, 'digital' => true, 'offline' => true,
            'wallet' => true, 'partial' => true,
            'timing' => 'after', 'retryReminder' => false,
        ],

        // Cross-cutting commerce features.
        'features' => [
            'wallet' => true, 'loyaltyPoint' => true, 'referral' => true,
            'reviews' => true, 'inbox' => true, 'pushNotif' => true,
            'guestCheckout' => true, 'reorder' => true, 'wishlist' => true, 'blog' => false,
            // Buy Now (instant checkout) button on the item modal / details page /
            // quick-view. Off for StackFood + 6amMart per product decision — the
            // storefront uses the add-to-cart flow only.
            'buyNow' => true,
            // Delivery-partner contact affordances on the order-details card.
            // `chat` = message via storefront inbox, `call` = tap-to-call tel: link.
            // Both hidden for 6valley; the host adapter (OrderProvider) enforces
            // them by withholding the deliveryMan id / phone from the payload.
            'deliveryManChat' => false, 'deliveryManCall' => false,
        ],

        // Auth methods (folds the existing social/login switches under one axis).
        'auth' => [
            'manual' => true, 'otp' => true, 'otpChannel' => 'sms',
            'social' => ['google' => true, 'facebook' => true, 'apple' => true],
            // Storefront forgot-password: `status` enables/disables the whole
            // feature; `modes` lists the allowed reset channels — any of
            // ['phone', 'email'], ['phone'], or ['email'].
            'forgotPassword' => ['status' => true, 'modes' => ['phone']],
        ],
    ],
];
