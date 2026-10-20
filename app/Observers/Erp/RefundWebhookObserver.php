<?php

namespace App\Observers\Erp;

use App\Models\RefundRequest;
use App\Services\Erp\ErpResourceTransformer;
use App\Traits\DispatchesErpWebhooks;

class RefundWebhookObserver
{
    use DispatchesErpWebhooks;

    private const RELATIONS = ['order.seller.shop', 'order.customer'];

    public function created(RefundRequest $refund): void
    {
        $this->dispatchErpWebhook('refund.created', fn() => ErpResourceTransformer::refund($refund->loadMissing(self::RELATIONS)));
    }

    public function updated(RefundRequest $refund): void
    {
        $this->dispatchErpWebhook('refund.updated', fn() => ErpResourceTransformer::refund($refund->loadMissing(self::RELATIONS)));
    }
}
