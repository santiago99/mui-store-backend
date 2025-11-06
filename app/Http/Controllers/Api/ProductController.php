<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
            'brand_id' => 'integer|exists:brands,id',
            'brand_slug' => 'string|exists:brands,slug',
            'sort_by' => 'string|in:title,price,created_at',
            'sort_direction' => 'string|in:asc,desc',
        ]);

        $query = Product::with('category', 'brand');

        // Apply category filter
        if (isset($filter['category_id'])) {
            $query->where('category_id', $filter['category_id']);
        }
        
        // Apply brand filter (id or slug)
        if (isset($filter['brand_id'])) {
            $query->where('brand_id', $filter['brand_id']);
        } elseif (isset($filter['brand_slug'])) {
            $query->whereHas('brand', function ($q) use ($filter) {
                $q->where('slug', $filter['brand_slug']);
            });
        }

        // Apply sorting
        $query->orderBy($filter['sort_by'], $filter['sort_direction']);

        // Get paginated results
        $products = $query->paginate($filter['per_page']);

        return ProductResource::collection($products);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'productClass', 'brand', 'fieldValues.productField']));
    }
}
