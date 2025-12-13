<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Resources\ProductFilterResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductFilterAggregator;
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
        // TODO: Do we need to support JSON string?
        /* if (is_string($filters)) {
            $filters = json_decode($filters, true) ?? [];
        } */
        if (! is_array($filters)) {
            $filters = [];
        }

        // Build filtered query (before pagination)
        $filteredQuery = Product::with('category', 'brand'/* , 'fieldValues.productField' */)
            ->defaultFilter($requestParams)
            ->extendedFilter($filters);

        // Get paginated results
        $products = (clone $filteredQuery)->paginate($requestParams['per_page']);

        $response = ProductResource::collection($products);

        // Calculate filters only if category_id is present and page=1
        // TODO: Temporary disabled (no support on frontend yet)
        if (0 && isset($requestParams['category_id']) && $requestParams['page'] == 1) {
            $category = Category::find($requestParams['category_id']);
            $productClass = $category?->productClass;

            if ($productClass) {
                $aggregator = new ProductFilterAggregator($filteredQuery);
                $filterData = $aggregator->aggregateAll($productClass);

                // Add filters to response
                $response->additional(['filters' => ProductFilterResource::collection($filterData)]);
            }
        }

        return $response;
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'productClass', 'brand', 'fieldValues.productField']));
    }
}
