<?php

namespace Modules\Courier\app\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Courier\app\Services\CourierService;
use Modules\Courier\app\Services\ProviderRegistry;
use Modules\Courier\app\ValueObjects\CourierOwner;

class CourierWebhookController extends Controller
{
    public function __construct(
        private readonly CourierService $courier,
        private readonly ProviderRegistry $providers,
    ) {}

    public function handle(Request $request, string $provider, ?string $owner = null): Response
    {
        $providerId = $this->providers->resolveByRouteKey($provider);

        if ($providerId === null) {
            return response('Unknown provider', 404);
        }

        $courierOwner = CourierOwner::fromRouteKey($owner);
        $event = $this->courier->handleWebhook($providerId, $request, $courierOwner);

        if ($event === null) {
            return response('Invalid signature', 401);
        }

        $ack = $this->providers->forOwner($courierOwner)->driver($providerId)->webhookAcknowledgement();

        return response($ack['body'] ?? '', $ack['status'] ?? 200, $ack['headers'] ?? []);
    }
}
