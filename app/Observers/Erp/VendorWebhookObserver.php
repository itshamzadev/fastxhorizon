<?php

namespace App\Observers\Erp;

use App\Models\Seller;
use App\Services\Erp\ErpResourceTransformer;
use App\Traits\DispatchesErpWebhooks;

class VendorWebhookObserver
{
    use DispatchesErpWebhooks;

    public function created(Seller $seller): void
    {
        $this->dispatchErpWebhook('vendor.created', fn() => ErpResourceTransformer::vendor($seller->loadMissing('shop')));
    }

    public function updated(Seller $seller): void
    {
        $this->dispatchErpWebhook('vendor.updated', fn() => ErpResourceTransformer::vendor($seller->loadMissing('shop')));
    }
}
