<?php

use Modules\Courier\CourierProviders\AramexProvider;
use Modules\Courier\CourierProviders\DelhiveryProvider;
use Modules\Courier\CourierProviders\DhlProvider;
use Modules\Courier\CourierProviders\GarudaExpressProvider;
use Modules\Courier\CourierProviders\LalamoveProvider;
use Modules\Courier\CourierProviders\PathaoNepalProvider;
use Modules\Courier\CourierProviders\PathaoProvider;
use Modules\Courier\CourierProviders\RedxProvider;
use Modules\Courier\CourierProviders\ShiprocketProvider;
use Modules\Courier\CourierProviders\TcsProvider;

return [
    'name' => 'Courier',

    'active_provider' => env('COURIER_PROVIDER'),

    'providers' => [
        'pathao'   => PathaoProvider::class,
        'pathao_nepal' => PathaoNepalProvider::class,
        'redx'     => RedxProvider::class,
        'dhl'      => DhlProvider::class,
        'lalamove' => LalamoveProvider::class,
        'delhivery' => DelhiveryProvider::class,
        'aramex'    => AramexProvider::class,
        'shiprocket' => ShiprocketProvider::class,
        'tcs'       => TcsProvider::class,
        'garuda_express' => GarudaExpressProvider::class,
    ],

    'webhook' => [
        'route_prefix' => 'courier/webhook',
    ],

    'http' => [
        'timeout' => (int) env('COURIER_HTTP_TIMEOUT', 30),
        'retries' => (int) env('COURIER_HTTP_RETRIES', 0),
    ],
];
