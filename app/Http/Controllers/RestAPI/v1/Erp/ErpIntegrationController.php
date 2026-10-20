<?php

namespace App\Http\Controllers\RestAPI\v1\Erp;

use App\Http\Controllers\Controller;
use App\Models\ErpApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpIntegrationController extends Controller
{
    /**
     * Connection test. The ErpTokenAuthMiddleware has already validated the
     * credentials and attached the matched token to the request.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var ErpApiToken $token */
        $token = $request->attributes->get('erp_token');

        return response()->json([
            'status' => true,
            'message' => translate('connection_successful'),
            'data' => [
                'token_name' => $token->name,
                'connected_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [],
        ]);
    }
}
