<?php

namespace App\Traits;

use App\Jobs\DispatchErpWebhookJob;
use App\Models\ErpApiToken;

trait DispatchesErpWebhooks
{
    /**
     * @param callable():array $payloadResolver Builds the transformed payload only
     *        when at least one webhook-enabled token exists, avoiding wasted work.
     */
    protected function dispatchErpWebhook(string $event, callable $payloadResolver): void
    {
        if (!ErpApiToken::withWebhook()->exists()) {
            return;
        }

        DispatchErpWebhookJob::dispatch($event, $payloadResolver());
    }
}
