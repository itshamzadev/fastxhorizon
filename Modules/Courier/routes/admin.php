<?php

use Illuminate\Support\Facades\Route;
use Modules\Courier\app\Http\Controllers\Admin\CourierConfigController;
use Modules\Courier\app\Http\Controllers\Admin\CourierDispatchController;
use Modules\Courier\app\Http\Controllers\Admin\CourierLocationController;

Route::middleware('admin')->prefix('admin/courier')->name('admin.courier.')->group(function () {
    // Configuration sits outside the service gate: partners are set up before
    // the delivery partner service is switched on, and stay editable after it
    // is switched off — only booking one onto an order is gated.
    Route::middleware('delivery_partner_config')->group(function () {
        Route::get('config', [CourierConfigController::class, 'index'])->name('config.index');
        Route::put('config', [CourierConfigController::class, 'update'])->name('config.update');
    });

    // Tracking sits outside the gate so a shipment booked before the admin
    // switched the delivery partner service off stays visible on the order.
    Route::middleware('delivery_partner_service')->group(function () {
        Route::get('locations/stores', [CourierLocationController::class, 'stores'])->name('locations.stores');
        Route::get('locations/cities', [CourierLocationController::class, 'cities'])->name('locations.cities');
        Route::get('locations/zones/{cityId}', [CourierLocationController::class, 'zones'])->name('locations.zones');
        Route::get('locations/areas/{zoneId}', [CourierLocationController::class, 'areas'])->name('locations.areas');

        Route::post('dispatch', [CourierDispatchController::class, 'store'])->name('dispatch');
        Route::post('revise', [CourierDispatchController::class, 'revise'])->name('revise');
        Route::post('estimate', [CourierDispatchController::class, 'estimate'])->name('estimate');
    });

    Route::get('track/{consignmentId}', [CourierDispatchController::class, 'track'])->name('track');
});
