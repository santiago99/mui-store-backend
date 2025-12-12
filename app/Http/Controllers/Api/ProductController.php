<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $requestParams = $request->validated();

        // Extract filters from request (may be JSON string or array)
        $filters = $request->input('filters');
        \Illuminate\Support\Facades\Log::info($filters);
        /* if (is_string($filters)) {
            $filters = json_decode($filters, true) ?? [];
        } */
        if (! is_array($filters)) {
            $filters = [];
        }

        // Get paginated results
        $products = Product::with('category', 'brand', 'fieldValues.productField')
            ->defaultFilter($requestParams)
            ->extendedFilter($filters)
            ->paginate($requestParams['per_page']);

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
