<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductFieldRequest;
use App\Http\Requests\UpdateProductFieldRequest;
use App\Http\Resources\ProductFieldResource;
use App\Models\ProductField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductFieldController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $productFields = ProductField::all();

        return ProductFieldResource::collection($productFields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductFieldRequest $request): ProductFieldResource
    {
        $productField = ProductField::create($request->validated());

        return new ProductFieldResource($productField);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductField $productField): ProductFieldResource
    {
        return new ProductFieldResource($productField);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductFieldRequest $request, ProductField $productField): ProductFieldResource
    {
        $productField->update($request->validated());

        return new ProductFieldResource($productField);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductField $productField): JsonResponse
    {
        $productField->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
