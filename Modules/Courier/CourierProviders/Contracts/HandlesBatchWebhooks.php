<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Illuminate\Http\Request;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;

interface HandlesBatchWebhooks
{
    public function parseWebhookEvents(Request $request): array;
}
