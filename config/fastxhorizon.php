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
];
