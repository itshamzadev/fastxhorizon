<?php

use Illuminate\Support\Facades\Route;
use Modules\Courier\app\Http\Controllers\Admin\CourierDispatchController;
use Modules\Courier\app\Http\Controllers\Admin\CourierLocationController;
use Modules\Courier\app\Http\Controllers\Vendor\CourierConfigController;

Route::middleware('seller')->prefix('vendor/courier')->name('vendor.courier.')->group(function () {
    // Configuration answers to the admin's vendor setup setting alone: a vendor
    // keeps their own carrier credentials editable while the platform's
    // delivery partner service is off — only booking one onto an order is gated.
    Route::middleware('vendor_delivery_partner_config')->group(function () {
        Route::get('config', [CourierConfigController::class, 'index'])->name('config.index');
        Route::put('config', [CourierConfigController::class, 'update'])->name('config.update');
    });

    // Tracking sits outside the gate so a shipment booked before the admin
    // switched vendor setup off stays visible on the order.
    Route::middleware('vendor_delivery_partner_setup')->group(function () {
        Route::post('dispatch', [CourierDispatchController::class, 'store'])->name('dispatch');
        Route::post('revise', [CourierDispatchController::class, 'revise'])->name('revise');
        Route::post('estimate', [CourierDispatchController::class, 'estimate'])->name('estimate');

        Route::get('locations/stores', [CourierLocationController::class, 'stores'])->name('locations.stores');
        Route::get('locations/cities', [CourierLocationController::class, 'cities'])->name('locations.cities');
        Route::get('locations/zones/{cityId}', [CourierLocationController::class, 'zones'])->name('locations.zones');
        Route::get('locations/areas/{zoneId}', [CourierLocationController::class, 'areas'])->name('locations.areas');
    });

    Route::get('track/{consignmentId}', [CourierDispatchController::class, 'track'])->name('track');
});
