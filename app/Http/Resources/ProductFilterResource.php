<?php

namespace App\Http\Resources;

use App\Models\ProductClass;
use App\Models\ProductField;
use App\Models\ProductFieldValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductFilterResource extends JsonResource
{
    /**
     * The product class instance for the current collection.
     */
    private static array $productClasses = [];

    /**
     * Set the product class for the resource collection.
     */
    public static function cacheProductClass(?ProductClass $productClass): void
    {
        self::$productClasses[$productClass->id] = $productClass;
    }

    private static function getProductClass(int $productClassId): ?ProductClass
    {
        return self::$productClasses[$productClassId] ?? null;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $filterType = $this->pivot->filter_type ? \App\Enums\FilterType::from($this->pivot->filter_type) : null;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'filterType' => $filterType?->value,
            'filterWeight' => $this->pivot->filter_weight ?? 0,
            'options' => $this->pivot->options,
        ];

        // Calculate min/max for range filters
        if ($filterType === \App\Enums\FilterType::Range) {
            $minMax = $this->calculateMinMax($this->pivot->product_class_id);
            $data['min'] = $minMax['min'];
            $data['max'] = $minMax['max'];
            //$data['dbg'] = $minMax['dbg'];
        }

        // Calculate filter options for checkboxes/select
        if (in_array($filterType, [\App\Enums\FilterType::Checkboxes, \App\Enums\FilterType::Select])) {
            $data['filterOptions'] = $this->calculateFilterOptions($this->pivot->product_class_id);
        }

        return $data;
    }

    /**
     * Calculate min and max values for range filters.
     *
     * @return array{min: mixed, max: mixed}
     */
    private function calculateMinMax(int $productClassId): array
    {
        $productClass = self::getProductClass($productClassId);
        $dbg = ['class_id' => $productClassId, 'field_id' => $this->id];

        if (! $productClass) {
            return ['min' => null, 'max' => null, 'dbg' => $dbg];
        }

        $productIds = $this->getProductIds($productClass);
        $dbg['product_ids'] = $productIds;
        $values = ProductFieldValue::where('product_field_id', $this->id)
            ->whereIn('product_id', $productIds)
            ->get();

        $valueField = $this->type === \App\Enums\ProductFieldType::Integer ? 'value_int' : 'value_float';

        return [
            'min' => $values->whereNotNull($valueField)->min($valueField),
            'max' => $values->whereNotNull($valueField)->max($valueField),
            'dbg' => $dbg,
        ];
    }

    /**
     * Calculate filter options for checkboxes/select filters.
     *
     * @return array<int|float|string>
     */
    private function calculateFilterOptions(int $productClassId): array
    {
        // Use enum options from ProductField if available
        if ($this->type === \App\Enums\ProductFieldType::Enum && ! empty($this->options['enum_options'])) {
            return $this->options['enum_options'];
        }

        $productClass = self::getProductClass($productClassId);
        if (! $productClass) {
            return [];
        }

        // Get distinct values from product_field_values
        $productIds = $this->getProductIds($productClass);
        $query = ProductFieldValue::where('product_field_id', $this->id)
            ->whereIn('product_id', $productIds);

        $valueField = match ($this->type) {
            \App\Enums\ProductFieldType::Integer => 'value_int',
            \App\Enums\ProductFieldType::Float => 'value_float',
            default => 'value_string',
        };

        return $query->whereNotNull($valueField)
            ->distinct()
            ->orderBy($valueField)
            ->pluck($valueField)
            ->toArray();
    }

    /**
     * Get product IDs for a product class.
     */
    private function getProductIds(ProductClass $productClass): \Illuminate\Support\Collection
    {
        static $productIdsCache = [];

        $cacheKey = $productClass->id;

        if (! isset($productIdsCache[$cacheKey])) {
            $productIdsCache[$cacheKey] = $productClass->products()->pluck('id');
        }

        return $productIdsCache[$cacheKey];
    }
}
