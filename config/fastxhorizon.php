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

    'admin_sync' => [
        'target_email' => 'admin@fastxhorizon.com',
        'canonical_role_id' => 1,
        'canonical_role_name' => 'Master Admin',
    ],

    'mail_addresses' => [
        'info' => 'info@fastxhorizon.com',
        'support' => 'support@fastxhorizon.com',
        'contact' => 'contact@fastxhorizon.com',
        'orders' => 'orders@fastxhorizon.com',
        'sellers' => 'sellers@fastxhorizon.com',
        'resellers' => 'resellers@fastxhorizon.com',
        'affiliates' => 'affiliates@fastxhorizon.com',
        'returns' => 'returns@fastxhorizon.com',
        'billing' => 'billing@fastxhorizon.com',
        'news' => 'news@fastxhorizon.com',
        'noreply' => 'noreply@fastxhorizon.com',
        'security' => 'security@fastxhorizon.com',
        'careers' => 'careers@fastxhorizon.com',
    ],

    'mail_identity_display_names' => [
        'info' => 'FastXHorizon - Info',
        'support' => 'FastXHorizon - Support',
        'contact' => 'FastXHorizon - Contact',
        'orders' => 'FastXHorizon - Orders',
        'sellers' => 'FastXHorizon - Sellers',
        'resellers' => 'FastXHorizon - Resellers',
        'affiliates' => 'FastXHorizon - Affiliates',
        'partners' => 'FastXHorizon - Partners',
        'returns' => 'FastXHorizon - Returns',
        'billing' => 'FastXHorizon - Billing',
        'news' => 'FastXHorizon - News',
        'noreply' => 'FastXHorizon - No Reply',
        'security' => 'FastXHorizon - Security',
        'privacy' => 'FastXHorizon - Privacy',
        'legal' => 'FastXHorizon - Legal',
        'careers' => 'FastXHorizon - Careers',
        'press' => 'FastXHorizon - Press',
    ],

    'mail_identity_by_user_type' => [
        'customer' => 'noreply',
        'seller' => 'sellers',
        'vendor' => 'sellers',
        'delivery_man' => 'noreply',
        'promoter' => 'affiliates',
        'admin' => 'info',
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
        'company_public_email' => 'info@fastxhorizon.com',
        'company_public_phone' => '+923265680129',
        'company_public_website' => 'www.fastxhorizon.com',
        'company_founded_year' => '2026',
        'company_registration_type' => '',
        'company_registration_number' => '',
        'company_registration_date' => '',
        'company_public_office_address' => '',
        'company_show_office_address' => '0',
        'company_copyright_text' => '© FastXHorizon. All rights reserved.',
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

    'canonical_logo_source' => 'fastxhorizon.png',

    'canonical_logo_variants' => [
        'company/fastxhorizon-web.png' => 900,
        'company/fastxhorizon-invoice.png' => 1000,
        'company/fastxhorizon-mobile.png' => 450,
        'company/fastxhorizon-footer.png' => 700,
    ],

    'branding_assets' => [
        'company/fastxhorizon-favicon.png' => 'fastxhorizon-favicon.png',
        'company/fastxhorizon-loader.webp' => 'fastxhorizon-loader.webp',
    ],

    'branding_setting_values' => [
        'company_web_logo' => ['image_name' => 'fastxhorizon-web.png', 'storage' => 'public'],
        'company_web_logo_png' => ['image_name' => 'fastxhorizon-invoice.png', 'storage' => 'public'],
        'company_mobile_logo' => ['image_name' => 'fastxhorizon-mobile.png', 'storage' => 'public'],
        'company_footer_logo' => ['image_name' => 'fastxhorizon-footer.png', 'storage' => 'public'],
        'company_fav_icon' => ['image_name' => 'fastxhorizon-favicon.png', 'storage' => 'public'],
        'loader_gif' => ['image_name' => 'fastxhorizon-loader.webp', 'storage' => 'public'],
    ],

    /*
    | These are the exact non-transactional records used by the active Blade
    | About Us page. Photos intentionally remain public/ paths because the
    | template resolves those values through dynamicAsset().
    */
    'business_page_visuals' => [
        'about-us' => [
            'hero_image' => 'theme:company-policy/about-hero.webp',
            'supporting_image_1' => 'theme:company-policy/about-intro.webp',
            'supporting_image_2' => 'theme:company-policy/about-global.webp',
        ],
    ],

    'leadership_profiles' => [
        [
            'name' => 'Muhammad Younas',
            'role' => 'CEO & Director',
            'photo' => 'public/assets/front-end/img/leadership/muhammad-younas-profile.png',
            'bio' => 'Leads Fast X Horizon’s overall business direction, marketplace growth, strategic planning, and company operations, with a focus on building a trusted and scalable commerce platform.',
            'portfolio_url' => null,
            'display_order' => 1,
            'is_active' => true,
        ],
        [
            'name' => 'Muhammad Hamza',
            'role' => 'Head of Technology & Operations',
            'photo' => 'public/assets/front-end/img/leadership/muhammad-hamza-profile.png',
            'bio' => 'Leads Fast X Horizon’s technology direction, platform development, digital systems, and technical operations, with a focus on building a reliable and scalable marketplace experience.',
            'portfolio_url' => 'https://itshamzadev.com',
            'display_order' => 2,
            'is_active' => true,
        ],
    ],

    'presentation_assets' => [
        'public/assets/front-end/img/company-policy/about-hero.webp',
        'public/assets/front-end/img/company-policy/about-intro.webp',
        'public/assets/front-end/img/company-policy/about-global.webp',
        'public/assets/front-end/img/leadership/muhammad-younas-profile.png',
        'public/assets/front-end/img/leadership/muhammad-hamza-profile.png',
    ],
];
