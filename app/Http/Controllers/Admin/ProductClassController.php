<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductClassRequest;
use App\Http\Requests\UpdateProductClassRequest;
use App\Http\Resources\ProductClassResource;
use App\Models\ProductClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $productClasses = ProductClass::with('fields')->get();

        return ProductClassResource::collection($productClasses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductClassRequest $request): ProductClassResource
    {
        $productClass = ProductClass::create($request->validated());

        // Attach fields if provided
        if ($request->has('field_ids')) {
            $fieldIds = $request->input('field_ids');
            $weights = array_fill(0, count($fieldIds), 0);
            $productClass->fields()->attach(array_combine($fieldIds, array_map(fn ($weight, $index) => ['weight' => $index], $weights, array_keys($fieldIds))));
        }

        return new ProductClassResource($productClass->load('fields'));
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductClass $productClass): ProductClassResource
    {
        return new ProductClassResource($productClass->load('fields'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductClassRequest $request, ProductClass $productClass): ProductClassResource
    {
        $productClass->update($request->validated());

        // Update field associations if provided
        if ($request->has('field_ids')) {
            $fieldIds = $request->input('field_ids');
            $weights = array_fill(0, count($fieldIds), 0);
            $productClass->fields()->sync(array_combine($fieldIds, array_map(fn ($weight, $index) => ['weight' => $index], $weights, array_keys($fieldIds))));
        }

        return new ProductClassResource($productClass->load('fields'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductClass $productClass): JsonResponse
    {
        $productClass->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
