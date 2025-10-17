<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->mergeIfMissing([
            'page' => 1,
            'per_page' => 24,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]);
        // Validate pagination parameters
        $filter = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'category_id' => 'integer|exists:categories,id',
            'sort_by' => 'string|in:title,price,created_at',
            'sort_direction' => 'string|in:asc,desc',
        ]);

        $query = Product::with('category');

        // Apply category filter
        if (isset($filter['category_id'])) {
            $query->where('category_id', $filter['category_id']);
        }

        // Apply sorting
        $query->orderBy($filter['sort_by'], $filter['sort_direction']);

        // Get paginated results
        $products = $query->paginate($filter['per_page']);

        return ProductResource::collection($products);
        // return response()->json([
        //     'success' => true,
        //     'data' => ProductResource::collection($products->items()),
        //     'pagination' => [
        //         'current_page' => $products->currentPage(),
        //         'per_page' => $products->perPage(),
        //         'total' => $products->total(),
        //         'last_page' => $products->lastPage(),
        //         'from' => $products->firstItem(),
        //         'to' => $products->lastItem(),
        //         'has_more_pages' => $products->hasMorePages(),
        //     ],
        //     'links' => [
        //         'first' => $products->url(1),
        //         'last' => $products->url($products->lastPage()),
        //         'prev' => $products->previousPageUrl(),
        //         'next' => $products->nextPageUrl(),
        //     ]
        // ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create($request->validated());

        return new ProductResource($product);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return new ProductResource($product->load('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
