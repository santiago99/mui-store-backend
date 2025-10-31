<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): ProductResource
    {
        $validated = $request->validated();
        $fieldValues = $validated['field_values'] ?? [];
        unset($validated['field_values']);

        $product = Product::create($validated);

        // Create field values if provided
        if (! empty($fieldValues) && $product->product_class_id) {
            $this->createFieldValues($product, $fieldValues);
        }

        return new ProductResource($product->load(['category', 'productClass', 'fieldValues.productField']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();
        $fieldValues = $validated['field_values'] ?? [];
        unset($validated['field_values']);

        $product->update($validated);

        // Update field values if provided
        if (! empty($fieldValues) && $product->product_class_id) {
            $this->updateFieldValues($product, $fieldValues);
        }

        return new ProductResource($product->load(['category', 'productClass', 'fieldValues.productField']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Create field values for a product.
     */
    private function createFieldValues(Product $product, array $fieldValues): void
    {
        foreach ($fieldValues as $fieldId => $value) {
            if ($value !== null) {
                $field = \App\Models\ProductField::find($fieldId);
                if ($field) {
                    \App\Models\ProductFieldValue::create([
                        'product_id' => $product->id,
                        'product_field_id' => $fieldId,
                        'value_string' => in_array($field->type, [\App\Enums\ProductFieldType::String, \App\Enums\ProductFieldType::Enum]) ? $value : null,
                        'value_int' => $field->type === \App\Enums\ProductFieldType::Integer ? $value : null,
                        'value_float' => $field->type === \App\Enums\ProductFieldType::Float ? $value : null,
                    ]);
                }
            }
        }
    }

    /**
     * Update field values for a product.
     */
    private function updateFieldValues(Product $product, array $fieldValues): void
    {
        // Delete existing field values
        $product->fieldValues()->delete();

        // Create new field values
        $this->createFieldValues($product, $fieldValues);
    }
}
