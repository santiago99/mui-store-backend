<?php

namespace App\Http\Controllers\Api;

use App\Enums\FilterType;
use App\Enums\ProductFieldType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductFilterResource;
use App\Models\Category;
use App\Models\ProductField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::active()
            ->get()
            ->toTree();

        return CategoryResource::collection($categories);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): CategoryResource
    {
        $category->load('ancestors');

        return new CategoryResource($category);
    }

    /**
     * Get products for a category and its descendants.
     */
    public function products(Category $category): JsonResponse
    {
        $products = $category->getAllProducts()->with('category')->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get filters from product class associated with the category.
     */
    public function filters(Category $category): AnonymousResourceCollection
    {
        $productClass = $category->productClass;

        if (! $productClass) {
            return ProductFilterResource::collection(collect());
        }

        $filterableFields = $productClass->filterableFields()->get();

        ProductFilterResource::cacheProductClass($productClass);

        // Create synthetic Brand filter
        $brandFilter = $this->createBrandFilter($productClass);

        // Create synthetic Price filter
        $priceFilter = $this->createPriceFilter($productClass);

        // Prepend Brand and Price filters to the collection
        $allFilters = collect([$brandFilter, $priceFilter])->merge($filterableFields);

        return ProductFilterResource::collection($allFilters);
    }

    /**
     * Create a synthetic Brand filter object.
     */
    private function createBrandFilter($productClass): ProductField
    {
        $brandFilter = new ProductField;
        $brandFilter->setAttribute('id', -1);
        $brandFilter->setAttribute('name', 'Brand');
        $brandFilter->setAttribute('slug', 'brand');
        $brandFilter->setAttribute('type', ProductFieldType::String);
        $brandFilter->setAttribute('options', null);
        $brandFilter->exists = true; // Mark as existing so it behaves like a loaded model

        // Create pivot object with required properties
        $pivot = new \stdClass;
        $pivot->product_class_id = $productClass->id;
        $pivot->filter_type = FilterType::Checkboxes->value;
        $pivot->filter_weight = -1;
        $pivot->options = null;

        // Set the pivot relationship
        $brandFilter->setRelation('pivot', $pivot);

        return $brandFilter;
    }

    /**
     * Create a synthetic Price filter object.
     */
    private function createPriceFilter($productClass): ProductField
    {
        $priceFilter = new ProductField;
        $priceFilter->setAttribute('id', -2);
        $priceFilter->setAttribute('name', 'Price');
        $priceFilter->setAttribute('slug', 'price');
        $priceFilter->setAttribute('type', ProductFieldType::Float);
        $priceFilter->setAttribute('options', null);
        $priceFilter->exists = true; // Mark as existing so it behaves like a loaded model

        // Create pivot object with required properties
        $pivot = new \stdClass;
        $pivot->product_class_id = $productClass->id;
        $pivot->filter_type = FilterType::Range->value;
        $pivot->filter_weight = -2;
        $pivot->options = null;

        // Set the pivot relationship
        $priceFilter->setRelation('pivot', $pivot);

        return $priceFilter;
    }
}
