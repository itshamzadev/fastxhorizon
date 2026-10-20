<?php

namespace Modules\Courier\CourierProviders\Contracts;

use Illuminate\Http\Request;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;

interface HandlesWebhooks
{
    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): WebhookEvent;
}
