<?php

return [
    'frontend_mode' => env('FRONTEND_MODE', 'blade'),
    // Observability-only metadata. These values must never alter business data
    // or user-visible behaviour when application nodes are load-balanced.
    'region' => env('APP_REGION'),
    'instance_id' => env('APP_INSTANCE_ID'),

    'readiness' => [
        'redis_required' => filter_var(env('FASTXHORIZON_READINESS_REDIS_REQUIRED', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production-safe presentation sync allowlist
    |--------------------------------------------------------------------------
    | This file intentionally contains presentation and company identity only.
    | It must never become a generic database export.
    */
    'settings' => [
        'company_name' => 'FastXHorizon',
        'brand_name' => 'FastXHorizon',
        'legal_company_name' => 'FASTXHORIZON (PRIVATE) LIMITED',
        'legal_name_short' => 'FASTXHORIZON (Pvt.) Ltd.',
        'company_status' => 'Reserved',
        'company_email' => 'info@fastxhorizon.com',
        'company_phone' => '+923265680129',
        'business_mode' => 'multi',
        'currency_model' => 'multi_currency',
        'currency_symbol_position' => 'left',
        'decimal_point_settings' => '2',
        'homepage_deals_setup' => [
            'enabled' => 1,
            'product_limit' => 10,
            'countdown_enabled' => 1,
        ],
        'homepage_featured_setup' => [
            'enabled' => 1,
            'title' => 'Featured Products',
            'product_limit' => 12,
        ],
        'homepage_categories_setup' => [
            'enabled' => 1,
            'product_limit' => 8,
        ],
    ],

    'currency_code' => 'PKR',

    'branding_assets' => [
        'company/fastxhorizon-web.webp' => 'fastxhorizon-web.webp',
        'company/fastxhorizon-invoice.png' => 'fastxhorizon-invoice.png',
        'company/fastxhorizon-mobile.webp' => 'fastxhorizon-mobile.webp',
        'company/fastxhorizon-footer.webp' => 'fastxhorizon-footer.webp',
        'company/fastxhorizon-favicon.png' => 'fastxhorizon-favicon.png',
        'company/fastxhorizon-loader.webp' => 'fastxhorizon-loader.webp',
    ],

    'branding_setting_values' => [
        'company_web_logo' => ['image_name' => 'fastxhorizon-web.webp', 'storage' => 'public'],
        'company_web_logo_png' => ['image_name' => 'fastxhorizon-invoice.png', 'storage' => 'public'],
        'company_mobile_logo' => ['image_name' => 'fastxhorizon-mobile.webp', 'storage' => 'public'],
        'company_footer_logo' => ['image_name' => 'fastxhorizon-footer.webp', 'storage' => 'public'],
        'company_fav_icon' => ['image_name' => 'fastxhorizon-favicon.png', 'storage' => 'public'],
        'loader_gif' => ['image_name' => 'fastxhorizon-loader.webp', 'storage' => 'public'],
    ],
];
