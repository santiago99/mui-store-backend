<?php

namespace App\Services;

use App\Data\ProductFilterData;
use App\Enums\FilterType;
use App\Enums\ProductFieldType;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductClass;
use App\Models\ProductField;
use App\Models\ProductFieldValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductFilterAggregator
{
    private ?Collection $filteredProductIds = null;

    public function __construct(
        private Builder $productQuery
    ) {}

    /**
     * Aggregate filter data for a product field.
     */
    public function aggregate(ProductField $field, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options = null): ProductFilterData
    {
        $productFieldId = $field->id;

        // Handle special filters
        if ($productFieldId === -1) {
            return $this->aggregateBrandFilter($productClassId, $filterType, $filterWeight, $options);
        }

        if ($productFieldId === -2) {
            return $this->aggregatePriceFilter($productClassId, $filterType, $filterWeight, $options);
        }

        // Handle regular product field filters
        if ($filterType === FilterType::Range) {
            return $this->aggregateRangeFilter($field, $productClassId, $filterType, $filterWeight, $options);
        }

        if (in_array($filterType, [FilterType::Checkboxes, FilterType::Select])) {
            return $this->aggregateCheckboxFilter($field, $productClassId, $filterType, $filterWeight, $options);
        }

        // Default: return basic filter data without min/max or options
        return new ProductFilterData(
            productFieldId: $productFieldId,
            productClassId: $productClassId,
            filterType: $filterType,
            name: $field->name,
            slug: $field->slug,
            type: $field->type,
            filterWeight: $filterWeight,
            options: $options,
        );
    }

    /**
     * Aggregate range filter data (min/max values).
     */
    private function aggregateRangeFilter(ProductField $field, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = $this->getFilteredProductIds();

        $valueField = $field->type === ProductFieldType::Integer ? 'value_int' : 'value_float';

        $values = ProductFieldValue::where('product_field_id', $field->id)
            ->whereIn('product_id', $productIds)
            ->get();

        $min = $values->whereNotNull($valueField)->min($valueField);
        $max = $values->whereNotNull($valueField)->max($valueField);

        return new ProductFilterData(
            productFieldId: $field->id,
            productClassId: $productClassId,
            filterType: $filterType,
            name: $field->name,
            slug: $field->slug,
            type: $field->type,
            filterWeight: $filterWeight,
            options: $options,
            min: $min !== null ? (float) $min : null,
            max: $max !== null ? (float) $max : null,
        );
    }

    /**
     * Aggregate checkbox/select filter data (available options with counts).
     */
    private function aggregateCheckboxFilter(ProductField $field, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = $this->getFilteredProductIds();

        // Use enum options from ProductField if available
        if ($field->type === ProductFieldType::Enum && ! empty($field->options['enum_options'])) {
            $enumOptions = $field->options['enum_options'];
            $filterOptions = [];

            foreach ($enumOptions as $enumValue) {
                $count = ProductFieldValue::where('product_field_id', $field->id)
                    ->whereIn('product_id', $productIds)
                    ->where('value_string', $enumValue)
                    ->count();

                $filterOptions[] = [
                    'value' => $enumValue,
                    'displayValue' => $enumValue,
                    'count' => $count,
                ];
            }

            return new ProductFilterData(
                productFieldId: $field->id,
                productClassId: $productClassId,
                filterType: $filterType,
                name: $field->name,
                slug: $field->slug,
                type: $field->type,
                filterWeight: $filterWeight,
                options: $options,
                filterOptions: $filterOptions,
            );
        }

        // Get distinct values with counts from product_field_values
        $valueField = match ($field->type) {
            ProductFieldType::Integer => 'value_int',
            ProductFieldType::Float => 'value_float',
            default => 'value_string',
        };

        $results = DB::table('product_field_values')
            ->selectRaw("{$valueField} as value, COUNT(*) as count")
            ->where('product_field_id', $field->id)
            ->whereIn('product_id', $productIds)
            ->whereNotNull($valueField)
            ->groupBy('value')
            ->orderBy('value')
            ->get();

        $filterOptions = $results->map(function ($item) {
            return [
                'value' => $item->value,
                'displayValue' => $item->value,
                'count' => (int) $item->count,
            ];
        })->toArray();

        return new ProductFilterData(
            productFieldId: $field->id,
            productClassId: $productClassId,
            filterType: $filterType,
            name: $field->name,
            slug: $field->slug,
            type: $field->type,
            filterWeight: $filterWeight,
            options: $options,
            filterOptions: $filterOptions,
        );
    }

    /**
     * Aggregate brand filter data.
     */
    private function aggregateBrandFilter(int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = $this->getFilteredProductIds();

        // Get brands that are used by filtered products, with counts
        $results = Brand::whereHas('products', function ($query) use ($productIds) {
            $query->whereIn('products.id', $productIds);
        })
            ->withCount(['products' => function ($query) use ($productIds) {
                $query->whereIn('products.id', $productIds);
            }])
            ->orderBy('name')
            ->get();

        $filterOptions = $results->map(function ($brand) {
            return [
                'value' => $brand->id,
                'displayValue' => $brand->name,
                'count' => (int) $brand->products_count,
            ];
        })->toArray();

        return new ProductFilterData(
            productFieldId: -1,
            productClassId: $productClassId,
            filterType: $filterType,
            name: 'Brand',
            slug: 'brand',
            type: ProductFieldType::String,
            filterWeight: $filterWeight,
            options: $options,
            filterOptions: $filterOptions,
        );
    }

    /**
     * Aggregate price filter data.
     */
    private function aggregatePriceFilter(int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = $this->getFilteredProductIds();

        $result = Product::whereIn('id', $productIds)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return new ProductFilterData(
            productFieldId: -2,
            productClassId: $productClassId,
            filterType: $filterType,
            name: 'Price',
            slug: 'price',
            type: ProductFieldType::Float,
            filterWeight: $filterWeight,
            options: $options,
            min: $result?->min_price !== null ? floor($result->min_price) : null,
            max: $result?->max_price !== null ? floor($result->max_price) : null,
        );
    }

    /**
     * Aggregate all filters for a product class (Brand, Price, and all filterable fields).
     */
    public function aggregateAll(ProductClass $productClass): Collection
    {
        $filterableFields = $productClass->filterableFields()->get();

        // Create synthetic Brand filter
        $brandFilter = $this->createBrandFilter();
        $brandFilterData = $this->aggregate(
            $brandFilter,
            $productClass->id,
            FilterType::Checkboxes,
            -1,
            null
        );

        // Create synthetic Price filter
        $priceFilter = $this->createPriceFilter();
        $priceFilterData = $this->aggregate(
            $priceFilter,
            $productClass->id,
            FilterType::Range,
            -2,
            null
        );

        // Aggregate filter data for each field
        $filterData = collect([$brandFilterData, $priceFilterData]);

        foreach ($filterableFields as $field) {
            $filterType = $field->pivot->filter_type ? FilterType::from($field->pivot->filter_type) : null;
            $filterData->push($this->aggregate(
                $field,
                $productClass->id,
                $filterType,
                $field->pivot->filter_weight ?? 0,
                $field->pivot->options
            ));
        }

        return $filterData;
    }

    /**
     * Create a synthetic Brand filter object.
     */
    private function createBrandFilter(): ProductField
    {
        $brandFilter = new ProductField;
        $brandFilter->setAttribute('id', -1);
        $brandFilter->setAttribute('name', 'Brand');
        $brandFilter->setAttribute('slug', 'brand');
        $brandFilter->setAttribute('type', ProductFieldType::String);
        $brandFilter->setAttribute('options', null);
        $brandFilter->exists = true;

        return $brandFilter;
    }

    /**
     * Create a synthetic Price filter object.
     */
    private function createPriceFilter(): ProductField
    {
        $priceFilter = new ProductField;
        $priceFilter->setAttribute('id', -2);
        $priceFilter->setAttribute('name', 'Price');
        $priceFilter->setAttribute('slug', 'price');
        $priceFilter->setAttribute('type', ProductFieldType::Float);
        $priceFilter->setAttribute('options', null);
        $priceFilter->exists = true;

        return $priceFilter;
    }

    /**
     * Get filtered product IDs from the query builder.
     */
    private function getFilteredProductIds(): Collection
    {
        if ($this->filteredProductIds === null) {
            // Clone the query to avoid modifying the original
            $query = clone $this->productQuery;
            $this->filteredProductIds = $query->pluck('id');
        }

        return $this->filteredProductIds;
    }
}
