<?php

namespace Modules\Courier\app\Http\Controllers\SellerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Courier\app\Services\ProviderRegistry;

class CourierProviderController extends Controller
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function index(): JsonResponse
    {
        return response()->json(['providers' => $this->providers->enabledOptions()]);
    }
}
