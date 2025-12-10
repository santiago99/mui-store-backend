<?php

namespace App\Http\Resources;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductClass;
use App\Models\ProductField;
use App\Models\ProductFieldValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            // $data['dbg'] = $minMax['dbg'];
        }

        // Calculate filter options for checkboxes/select
        if (in_array($filterType, [\App\Enums\FilterType::Checkboxes, \App\Enums\FilterType::Select])) {
            // Handle Brand filter specially
            if ($this->id === -1) {
                $data['filterOptions'] = $this->calculateBrandFilterOptions($this->pivot->product_class_id);
            } else {
                $data['filterOptions'] = $this->calculateFilterOptions($this->pivot->product_class_id);
            }
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

        // Handle Price filter (id === -2) - query products table directly
        if ($this->id === -2) {
            $result = Product::whereIn('id', $productIds)
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            return [
                'min' => $result?->min_price,
                'max' => $result?->max_price,
                'dbg' => $dbg,
            ];
        }

        // Handle other filters - query ProductFieldValue table
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
     * @return array<int, array{value: mixed, displayValue: mixed, count: int}>
     */
    private function calculateFilterOptions(int $productClassId): array
    {
        $productClass = self::getProductClass($productClassId);
        if (! $productClass) {
            return [];
        }

        $productIds = $this->getProductIds($productClass);

        // Use enum options from ProductField if available
        if ($this->type === \App\Enums\ProductFieldType::Enum && ! empty($this->options['enum_options'])) {
            $enumOptions = $this->options['enum_options'];
            $result = [];

            foreach ($enumOptions as $enumValue) {
                $count = ProductFieldValue::where('product_field_id', $this->id)
                    ->whereIn('product_id', $productIds)
                    ->where('value_string', $enumValue)
                    ->count();

                $result[] = [
                    'value' => $enumValue,
                    'displayValue' => $enumValue,
                    'count' => $count,
                ];
            }

            return $result;
        }

        // Get distinct values with counts from product_field_values
        $valueField = match ($this->type) {
            \App\Enums\ProductFieldType::Integer => 'value_int',
            \App\Enums\ProductFieldType::Float => 'value_float',
            default => 'value_string',
        };

        $results = DB::table('product_field_values')
            ->selectRaw("{$valueField} as value, COUNT(*) as count")
            ->where('product_field_id', $this->id)
            ->whereIn('product_id', $productIds)
            ->whereNotNull($valueField)
            ->groupBy('value')
            ->orderBy('value')
            ->get();

        \Illuminate\Support\Facades\Log::info('dbg', [
            'product_field_id' => $this->id,
            'product_ids' => $productIds->toArray(),
            'valueField' => $valueField,
            // 'results' => $results->toArray()
        ]);

        return $results->map(function ($item) {
            return [
                'value' => $item->value,
                'displayValue' => $item->value,
                'count' => (int) $item->count,
            ];
        })->toArray();
    }

    /**
     * Calculate brand filter options for checkboxes/select filters.
     *
     * @return array<int, array{value: mixed, displayValue: mixed, count: int}>
     */
    private function calculateBrandFilterOptions(int $productClassId): array
    {
        $productClass = self::getProductClass($productClassId);
        if (! $productClass) {
            return [];
        }

        $productIds = $this->getProductIds($productClass);

        // Get brands that are used by products in this product class, with counts
        $results = Brand::whereHas('products', function ($query) use ($productIds) {
            $query->whereIn('products.id', $productIds);
        })
            ->withCount(['products' => function ($query) use ($productIds) {
                $query->whereIn('products.id', $productIds);
            }])
            ->orderBy('name')
            ->get();

        return $results->map(function ($brand) {
            return [
                'value' => $brand->id,
                'displayValue' => $brand->name,
                'count' => (int) $brand->products_count,
            ];
        })->toArray();
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
