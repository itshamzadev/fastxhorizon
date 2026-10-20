<?php

namespace App\Http\Controllers\RestAPI\v1\Erp;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Services\Erp\ErpResourceTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    private const RELATIONS = [
        'order:id,order_status,order_amount,seller_id,customer_id',
        'order.seller.shop',
        'order.customer:id,f_name,l_name,email,phone',
    ];

    public function index(Request $request): JsonResponse
    {
        $limit = (int)($request['limit'] ?? 10);

        $paginator = RefundRequest::with(self::RELATIONS)
            ->orderBy('id')
            ->paginate(perPage: $limit)
            ->appends($request->query());

        return response()->json([
            'data' => collect($paginator->items())->map(fn($refund) => ErpResourceTransformer::refund($refund))->all(),
            'next_page_url' => $paginator->nextPageUrl(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $refund = RefundRequest::with(self::RELATIONS)->find($id);

        if (!$refund) {
            return response()->json([
                'errors' => [['code' => 'refund-404', 'message' => translate('refund_not_found')]],
            ], 404);
        }

        return response()->json(ErpResourceTransformer::refund($refund));
    }

    public function count(): JsonResponse
    {
        return response()->json([
            'total_refund_count' => RefundRequest::count(),
        ]);
    }
}
