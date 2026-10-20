<?php

return [
    // Disabled until a verified sandbox contract and credentials are supplied.
    'enabled' => (bool) env('SAFEPAY_ENABLED', false),
    'environment' => env('SAFEPAY_ENV', 'sandbox'),
    'api_key' => env('SAFEPAY_API_KEY'),
    'secret_key' => env('SAFEPAY_SECRET_KEY'),
    'webhook_secret' => env('SAFEPAY_WEBHOOK_SECRET'),
    'base_url' => env('SAFEPAY_BASE_URL'),
    'success_url' => env('SAFEPAY_SUCCESS_URL'),
    'cancel_url' => env('SAFEPAY_CANCEL_URL'),
];
