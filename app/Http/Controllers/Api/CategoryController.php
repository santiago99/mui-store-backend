<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
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
}
