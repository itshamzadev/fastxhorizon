<?php

namespace App\Http\Controllers\RestAPI\v1\Erp;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\Erp\ErpResourceTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int)($request['limit'] ?? 10);

        $paginator = Seller::with('shop')
            ->withCount('orders')
            ->withCount(['reviews' => fn($query) => $query->withoutGlobalScope('active')->where('reviews.status', 1)])
            ->withAvg(['reviews' => fn($query) => $query->withoutGlobalScope('active')->where('reviews.status', 1)], 'rating')
            ->orderBy('id')
            ->paginate(perPage: $limit)
            ->appends($request->query());

        return response()->json([
            'data' => collect($paginator->items())->map(fn($seller) => ErpResourceTransformer::vendor($seller))->all(),
            'next_page_url' => $paginator->nextPageUrl(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $seller = Seller::with('shop')
            ->withCount('orders')
            ->withCount(['reviews' => fn($query) => $query->withoutGlobalScope('active')->where('reviews.status', 1)])
            ->withAvg(['reviews' => fn($query) => $query->withoutGlobalScope('active')->where('reviews.status', 1)], 'rating')
            ->find($id);

        if (!$seller) {
            return response()->json([
                'errors' => [['code' => 'vendor-404', 'message' => translate('vendor_not_found')]],
            ], 404);
        }

        return response()->json(ErpResourceTransformer::vendor($seller));
    }
}
