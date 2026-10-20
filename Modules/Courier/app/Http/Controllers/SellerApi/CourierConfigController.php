<?php

namespace Modules\Courier\app\Http\Controllers\SellerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Courier\app\Http\Requests\SaveCourierProviderRequest;
use Modules\Courier\app\Services\CourierConfigService;
use Modules\Courier\app\Services\ProviderRegistry;

class CourierConfigController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly CourierConfigService $config,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['providers' => $this->providers->catalog()]);
    }

    public function update(SaveCourierProviderRequest $request): JsonResponse
    {
        $this->config->save($request);

        return response()->json([
            'ok'      => true,
            'message' => translate('Delivery_partner_settings_updated_successfully'),
        ]);
    }
}
