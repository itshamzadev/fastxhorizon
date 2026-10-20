<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\app\Http\Controllers\AIController;
use Modules\AI\app\Http\Controllers\API\V3\AIAuctionProductController;
use Modules\AI\app\Http\Controllers\ShoppingAssistantController;

Route::group([], function () {
    Route::resource('ai', AIController::class)->names('ai');
});

Route::group([
    'prefix'     => 'ai/assistant',
    'as'         => 'ai.assistant.',
    // guestCheck seeds session('guest_id') with the storefront GuestUser id so AI cart rows land in the same guest cart checkout reads, not under the AI's UUID.
    'middleware' => ['guestCheck', 'throttle:60,1'],
], function () {
    // Tighter limits on the paid endpoints (provider + vision calls).
    Route::post('upload-image',          [ShoppingAssistantController::class, 'uploadImage'])->middleware('throttle:15,1')->name('upload-image');
    Route::post('sessions',              [ShoppingAssistantController::class, 'startSession'])->middleware('throttle:30,1')->name('sessions.start');
    Route::get('sessions',               [ShoppingAssistantController::class, 'listSessions'])->name('sessions.list');
    Route::get('sessions/{id}',          [ShoppingAssistantController::class, 'getSession'])->name('sessions.show');
    Route::delete('sessions/{id}',       [ShoppingAssistantController::class, 'deleteSession'])->middleware('throttle:30,1')->name('sessions.delete');
    Route::post('sessions/{id}/message', [ShoppingAssistantController::class, 'sendMessage'])->middleware('throttle:20,1')->name('sessions.message');
});

Route::group(['prefix' => 'customer', 'as' => 'customer.', 'middleware' => ['customer']], function () {
    Route::group(['prefix' => 'auction/product', 'as' => 'auction.product.'], function () {
        Route::get('title-auto-fill', [AIAuctionProductController::class, 'titleAutoFill'])->name('title-auto-fill');
        Route::get('description-auto-fill', [AIAuctionProductController::class, 'descriptionAutoFill'])->name('description-auto-fill');
        Route::get('general-setup-auto-fill', [AIAuctionProductController::class, 'generalSetupAutoFill'])->name('general-setup-auto-fill');
        Route::get('shipping-policy-auto-fill', [AIAuctionProductController::class, 'shippingPolicyAutoFill'])->name('shipping-policy-auto-fill');
        Route::get('auction-info-auto-fill', [AIAuctionProductController::class, 'auctionInfoAutoFill'])->name('auction-info-auto-fill');
        Route::get('seo-section-auto-fill', [AIAuctionProductController::class, 'seoSectionAutoFill'])->name('seo-section-auto-fill');
        Route::post('analyze-image-auto-fill', [AIAuctionProductController::class, 'generateTitleFromImages'])->name('analyze-image-auto-fill');
        Route::post('generate-title-suggestions', [AIAuctionProductController::class, 'generateProductTitleSuggestion'])->name('generate-title-suggestions');
    });
});
