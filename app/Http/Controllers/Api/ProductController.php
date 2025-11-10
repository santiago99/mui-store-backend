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
        $filter = $request->validated();

        // Get paginated results
        $products = Product::with('category', 'brand')
            ->defaultFilter($filter)
            ->paginate($filter['per_page']);

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
