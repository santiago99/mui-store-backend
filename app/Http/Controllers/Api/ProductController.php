<?php

namespace App\Http\Controllers\Api;

use App\DTO\ProductSearchCriteria;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Resources\ProductFilterResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private ProductSearchService $searchService
    ) {}

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

        // Create search criteria DTO
        $criteria = new ProductSearchCriteria(
            page: $requestParams['page'],
            perPage: $requestParams['per_page'],
            categoryId: $requestParams['category_id'] ?? null,
            brandId: $requestParams['brand_id'] ?? null,
            brandSlug: $requestParams['brand_slug'] ?? null,
            priceMin: $requestParams['price_min'] ?? null,
            priceMax: $requestParams['price_max'] ?? null,
            sortBy: $requestParams['sort_by'],
            sortDirection: $requestParams['sort_direction'],
            filters: $filters,
        );

        // Search using service
        $products = $this->searchService->search($criteria);

        $response = ProductResource::collection($products);

        // Get filters using service (only calculated when category_id is present and page=1)
        $filterData = $this->searchService->getFilters($criteria);

        if ($filterData !== null) {
            // Add filters to response
            $response->additional(['filters' => ProductFilterResource::collection($filterData)]);
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
