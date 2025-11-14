<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductFilterResource;
use App\Models\Category;
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

        return ProductFilterResource::collection($filterableFields);
    }
}
