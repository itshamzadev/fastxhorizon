<?php

return [
    // Existing catalog and ledger values are stored in this currency.
    'base_currency' => env('FXH_BASE_CURRENCY', 'PKR'),
    'fallback_currency' => env('FXH_FALLBACK_CURRENCY', 'USD'),
    'rate_provider_url' => env('CURRENCY_RATE_PROVIDER_URL', 'https://open.er-api.com/v6/latest/USD'),
    'rate_provider_api_key' => env('CURRENCY_RATE_API_KEY'),
    'country_override' => env('FXH_TEST_VISITOR_COUNTRY'),
    'country_currency_map' => [
        'PK' => 'PKR', 'US' => 'USD', 'GB' => 'GBP', 'AE' => 'AED',
        'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'OM' => 'OMR',
        'BH' => 'BHD', 'CA' => 'CAD', 'AU' => 'AUD', 'JP' => 'JPY',
        'CN' => 'CNY', 'IN' => 'INR', 'TR' => 'TRY',
        'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR',
        'NL' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'PT' => 'EUR',
        'IE' => 'EUR', 'FI' => 'EUR', 'GR' => 'EUR',
    ],
    'phone_country_currency_map' => [
        '92' => 'PKR', '971' => 'AED', '44' => 'GBP', '966' => 'SAR',
        '974' => 'QAR', '965' => 'KWD', '968' => 'OMR', '973' => 'BHD',
        '91' => 'INR', '90' => 'TRY', '81' => 'JPY', '86' => 'CNY',
        '61' => 'AUD', '1' => null,
    ],
];
