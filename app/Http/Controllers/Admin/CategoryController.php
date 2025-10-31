<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        // Check if category has children
        if (! $category->isLeaf()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories. Please delete subcategories first.',
            ], Response::HTTP_CONFLICT);
        }

        $category->loadCount('products');
        // Check if category has products
        if ($category->products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with products. Please move or delete products first.',
            ], Response::HTTP_CONFLICT);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Move a category to a new parent.
     */
    public function move(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $parentId = $request->input('parent_id');

        // Prevent moving category to itself or its descendant
        if ($parentId && ($parentId == $category->id || $category->descendants()->where('id', $parentId)->exists())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move category to itself or its descendant.',
            ], Response::HTTP_CONFLICT);
        }

        $category->parent_id = $parentId;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category moved successfully',
            'data' => new CategoryResource($category),
        ]);
    }
}
