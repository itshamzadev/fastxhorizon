<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public storefront contract intended for a separately deployed frontend.
 * Existing mobile APIs and Blade routes remain unchanged during migration.
 */
class FrontendCatalogController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $products = Product::query()
            ->active()
            ->publiclyAvailable()
            ->purchasable()
            ->with(['seller.shop', 'category'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', (int) $request->integer('brand_id')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . addcslashes($request->string('q')->toString(), '%_') . '%'))
            ->latest('id')
            ->paginate(min(max((int) $request->input('per_page', 24), 1), 60));

        return response()->json([
            'data' => ProductResource::collection($products->getCollection())->resolve(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function product(string $slug): JsonResponse
    {
        $product = Product::query()
            ->active()
            ->publiclyAvailable()
            ->purchasable()
            ->with(['seller.shop', 'category'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['data' => (new ProductResource($product))->resolve()]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('home_status', true)
            ->orderBy('priority')
            ->orderBy('position')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)->resolve()]);
    }
}
