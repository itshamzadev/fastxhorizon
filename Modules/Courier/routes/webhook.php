<?php

use Illuminate\Support\Facades\Route;
use Modules\Courier\app\Http\Controllers\Webhook\CourierWebhookController;

$prefix = config('courier.webhook.route_prefix', 'courier/webhook');

Route::post($prefix.'/{provider}/{owner?}', [CourierWebhookController::class, 'handle'])
    ->name('courier.webhook');
