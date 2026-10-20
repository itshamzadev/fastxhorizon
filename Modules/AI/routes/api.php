<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\AI\app\Http\Controllers\API\V1\ShoppingAssistantController as ApiShoppingAssistantController;
use Modules\AI\app\Http\Controllers\API\V3\AIAuctionProductController;
use Modules\AI\app\Http\Controllers\API\V3\AIProductController;

Route::group(['prefix' => 'v3/seller', 'as' => 'v3/seller.', 'middleware' => ['api_lang']], function () {
    Route::group(['middleware' => ['seller_api_auth']], function () {
        Route::group(['prefix' => 'product', 'as' => 'product.'], function () {
            Route::post('title-auto-fill', [AIProductController::class, 'titleAutoFill'])->name('title-auto-fill');
            Route::post('description-auto-fill', [AIProductController::class, 'descriptionAutoFill'])->name('description-auto-fill');
            Route::post('general-setup-auto-fill', [AIProductController::class, 'generalSetupAutoFill'])->name('general-setup-auto-fill');
            Route::post('price-others-auto-fill', [AIProductController::class, 'pricingAndOthersAutoFill'])->name('price-others-auto-fill');
            Route::post('seo-section-auto-fill', [AIProductController::class, 'productSeoSectionAutoFill'])->name('seo-section-auto-fill');
            Route::post('variation-setup-auto-fill', [AIProductController::class, 'productVariationSetupAutoFill'])->name('variation-setup-auto-fill');
            Route::post('analyze-image-auto-fill', [AIProductController::class, 'generateTitleFromImages'])->name('analyze-image-auto-fill');
            Route::post('generate-title-suggestions', [AIProductController::class, 'generateProductTitleSuggestion'])->name('generate-title-suggestions');
            Route::get('generate-limit-check', [AIProductController::class, 'generateLimitCheck']);
        });

        Route::group(['prefix' => 'auction/product', 'as' => 'auction.product.'], function () {
            Route::post('title-auto-fill', [AIAuctionProductController::class, 'titleAutoFill'])->name('title-auto-fill');
            Route::post('description-auto-fill', [AIAuctionProductController::class, 'descriptionAutoFill'])->name('description-auto-fill');
            Route::post('general-setup-auto-fill', [AIAuctionProductController::class, 'generalSetupAutoFill'])->name('general-setup-auto-fill');
            Route::post('shipping-policy-auto-fill', [AIAuctionProductController::class, 'shippingPolicyAutoFill'])->name('shipping-policy-auto-fill');
            Route::post('auction-info-auto-fill', [AIAuctionProductController::class, 'auctionInfoAutoFill'])->name('auction-info-auto-fill');
            Route::post('seo-section-auto-fill', [AIAuctionProductController::class, 'seoSectionAutoFill'])->name('seo-section-auto-fill');
            Route::post('analyze-image-auto-fill', [AIAuctionProductController::class, 'generateTitleFromImages'])->name('analyze-image-auto-fill');
            Route::post('generate-title-suggestions', [AIAuctionProductController::class, 'generateProductTitleSuggestion'])->name('generate-title-suggestions');
            Route::post('setup-auto-fill', [AIAuctionProductController::class, 'setupAutoFill'])->name('setup-auto-fill');
            Route::get('generate-limit-check', [AIAuctionProductController::class, 'generateLimitCheck'])->name('generate-limit-check');
        });
    });
});

Route::group(['prefix' => 'v1', 'middleware' => ['api_lang']], function () {

    Route::group(['prefix'     => 'ai/assistant', 'as'         => 'v1/ai/assistant.', 'middleware' => ['apiGuestCheck', 'throttle:60,1'], ], function () {
        Route::post('upload-image', [ApiShoppingAssistantController::class, 'uploadImage'])
            ->middleware('throttle:15,1');
        Route::post('sessions', [ApiShoppingAssistantController::class, 'startSession'])->middleware('throttle:30,1');
        Route::get('sessions', [ApiShoppingAssistantController::class, 'listSessions']);
        Route::get('sessions/{id}', [ApiShoppingAssistantController::class, 'getSession']);
        Route::delete('sessions/{id}', [ApiShoppingAssistantController::class, 'deleteSession'])->middleware('throttle:30,1');
        Route::post('sessions/{id}/message', [ApiShoppingAssistantController::class, 'sendMessage'])->middleware('throttle:20,1');
    });

    Route::group(['prefix' => 'customer', 'as' => 'v1/customer.', 'middleware' => ['auth:api']], function () {
        Route::group(['prefix' => 'auction/product', 'as' => 'auction.product.'], function () {
            Route::post('title-auto-fill', [AIAuctionProductController::class, 'titleAutoFill'])->name('title-auto-fill');
            Route::post('description-auto-fill', [AIAuctionProductController::class, 'descriptionAutoFill'])->name('description-auto-fill');
            Route::post('general-setup-auto-fill', [AIAuctionProductController::class, 'generalSetupAutoFill'])->name('general-setup-auto-fill');
            Route::post('shipping-policy-auto-fill', [AIAuctionProductController::class, 'shippingPolicyAutoFill'])->name('shipping-policy-auto-fill');
            Route::post('auction-info-auto-fill', [AIAuctionProductController::class, 'auctionInfoAutoFill'])->name('auction-info-auto-fill');
            Route::post('seo-section-auto-fill', [AIAuctionProductController::class, 'seoSectionAutoFill'])->name('seo-section-auto-fill');
            Route::post('analyze-image-auto-fill', [AIAuctionProductController::class, 'generateTitleFromImages'])->name('analyze-image-auto-fill');
            Route::post('generate-title-suggestions', [AIAuctionProductController::class, 'generateProductTitleSuggestion'])->name('generate-title-suggestions');
            Route::post('setup-auto-fill', [AIAuctionProductController::class, 'setupAutoFill'])->name('setup-auto-fill');
            Route::get('generate-limit-check', [AIAuctionProductController::class, 'generateLimitCheck'])->name('generate-limit-check');
        });
    });
});
