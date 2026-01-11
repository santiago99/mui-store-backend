<?php

namespace App\Services;

use App\Contracts\ProductSearchEngine;
use App\Data\ProductFilterData;
use App\DTO\BrandDTO;
use App\DTO\CategoryDTO;
use App\DTO\ProductDTO;
use App\DTO\ProductFieldValueDTO;
use App\DTO\ProductSearchCriteria;
use App\Enums\FilterType;
use App\Enums\ProductFieldType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductClass;
use App\Models\ProductField;
use App\Models\ProductFieldValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentProductSearchEngine implements ProductSearchEngine
{
    private const EXCLUDE_BRAND = 'brand';

    private const EXCLUDE_PRICE = 'price';

    private ?Builder $filteredQuery = null;

    public function search(ProductSearchCriteria $criteria): PaginatorContract
    {
        // Extract virtual filters (-1, -2) into direct properties
        $criteria->extractVirtualFilters();

        // Build filtered query (before pagination)
        $this->filteredQuery = $this->buildFilteredQuery($criteria);

        // Get paginated results
        // We have to clone the query to not to apply pagination to original query
        $paginatedResults = (clone $this->filteredQuery)->paginate($criteria->perPage, ['*'], 'page', $criteria->page);
        // $paginatedResults = $this->filteredQuery->paginate($criteria->perPage, ['*'], 'page', $criteria->page);

        // Convert Eloquent models to DTOs
        $productDTOs = $paginatedResults->getCollection()->map(function (Product $product) {
            return $this->convertToDTO($product);
        });

        // Create new paginator with DTOs
        return new Paginator(
            $productDTOs,
            $paginatedResults->total(),
            $paginatedResults->perPage(),
            $paginatedResults->currentPage(),
            [
                'path' => $paginatedResults->path(),
                'pageName' => $paginatedResults->getPageName(),
            ]
        );
    }

    /**
     * Build a filtered query based on the search criteria.
     */
    private function buildFilteredQuery(ProductSearchCriteria $criteria): Builder
    {
        // Build filter array for defaultFilter scope
        $filterParams = [
            'category_id' => $criteria->categoryId,
            'brand_id' => $criteria->brandId,
            'brand_slug' => $criteria->brandSlug,
            'price_min' => $criteria->priceMin,
            'price_max' => $criteria->priceMax,
            'sort_by' => $criteria->sortBy,
            'sort_direction' => $criteria->sortDirection,
        ];

        // Remove null values to match existing behavior
        $filterParams = array_filter($filterParams, fn ($value) => $value !== null);

        // Build filtered query
        return Product::with('category', 'brand')
            ->defaultFilter($filterParams)
            ->extendedFilter($criteria->filters);
    }

    /**
     * Build a filtered query excluding a specific filter.
     * Used for facet isolation - when calculating available values for a filter,
     * we exclude that filter from the query to show all possible values.
     *
     * @param  ProductSearchCriteria  $criteria  The search criteria
     * @param  string|null  $excludeFilterId  The filter ID to exclude: 'brand', 'price', or productFieldId
     */
    private function buildFilteredQueryExcluding(ProductSearchCriteria $criteria, ?string $excludeFilterId): Builder
    {
        // Build filter array for defaultFilter scope
        $filterParams = [
            'category_id' => $criteria->categoryId,
            'sort_by' => $criteria->sortBy,
            'sort_direction' => $criteria->sortDirection,
        ];

        // Exclude brand filter if excludeFilterId is 'brand'
        if ($excludeFilterId !== self::EXCLUDE_BRAND) {
            $filterParams['brand_id'] = $criteria->brandId;
            $filterParams['brand_slug'] = $criteria->brandSlug;
        }

        // Exclude price filter if excludeFilterId is 'price'
        if ($excludeFilterId !== self::EXCLUDE_PRICE) {
            $filterParams['price_min'] = $criteria->priceMin;
            $filterParams['price_max'] = $criteria->priceMax;
        }

        // Remove null values to match existing behavior
        $filterParams = array_filter($filterParams, fn ($value) => $value !== null);

        // Build extended filters excluding the specified filter
        $extendedFilters = $criteria->filters;
        if ($excludeFilterId !== null && isset($extendedFilters[$excludeFilterId])) {
            $extendedFilters = array_diff_key($extendedFilters, [$excludeFilterId => true]);
        }

        // Build filtered query
        return Product::with('category', 'brand')
            ->defaultFilter($filterParams)
            ->extendedFilter($extendedFilters);
    }

    /**
     * Convert Eloquent Product model to ProductDTO.
     */
    private function convertToDTO(Product $product): ProductDTO
    {
        $categoryDTO = null;
        if ($product->relationLoaded('category') && $product->category) {
            $categoryDTO = new CategoryDTO(
                id: $product->category->id,
                name: $product->category->name,
                slug: $product->category->slug,
            );
        }

        $brandDTO = null;
        if ($product->relationLoaded('brand') && $product->brand) {
            $brandDTO = new BrandDTO(
                id: $product->brand->id,
                name: $product->brand->name,
                slug: $product->brand->slug,
            );
        }

        $fieldDTOs = [];
        if ($product->relationLoaded('fieldValues') && $product->fieldValues) {
            $fieldDTOs = $product->fieldValues->filter(function ($fieldValue) {
                return $fieldValue->relationLoaded('productField') && $fieldValue->productField;
            })->map(function ($fieldValue) {
                return new ProductFieldValueDTO(
                    id: $fieldValue->productField->id,
                    type: $fieldValue->productField->type,
                    name: $fieldValue->productField->name,
                    options: $fieldValue->productField->options,
                    value: $fieldValue->value,
                );
            })->toArray();
        }

        return new ProductDTO(
            id: $product->id,
            sku: $product->sku,
            title: $product->title,
            description: $product->description,
            price: (float) $product->price,
            imageUrl: $product->imageUrl,
            categoryId: $product->category_id,
            category: $categoryDTO,
            productClassId: $product->product_class_id,
            brandId: $product->brand_id,
            brand: $brandDTO,
            fields: $fieldDTOs,
            createdAt: $product->created_at->toDateTimeString(),
            updatedAt: $product->updated_at->toDateTimeString(),
        );
    }

    /**
     * Get filter data for the search criteria.
     * Returns null if filters should not be calculated (when categoryId is not present or page != 1).
     * Implements facet isolation: each filter is calculated excluding itself from the query.
     */
    public function getFilters(ProductSearchCriteria $criteria): ?Collection
    {
        // Extract virtual filters (-1, -2) into direct properties
        $criteria->extractVirtualFilters();

        // Only calculate filters when categoryId is present and page == 1
        if ($criteria->categoryId === null || $criteria->page !== 1) {
            return null;
        }

        // Get category and its product class
        $category = Category::find($criteria->categoryId);
        $productClass = $category?->productClass;

        if (! $productClass) {
            return null;
        }

        $filterableFields = $productClass->filterableFields()->get();

        // Aggregate Brand filter (exclude brand filter from query)
        $brandQuery = $this->buildFilteredQueryExcluding($criteria, self::EXCLUDE_BRAND);
        $brandFilterData = $this->aggregateBrandFilter($brandQuery, $productClass->id, FilterType::Checkboxes, -1, null);

        // Aggregate Price filter (exclude price filter from query)
        $priceQuery = $this->buildFilteredQueryExcluding($criteria, self::EXCLUDE_PRICE);
        $priceFilterData = $this->aggregatePriceFilter($priceQuery, $productClass->id, FilterType::Range, -2, null);

        // Aggregate filter data for each field (exclude that field from query)
        $filterData = collect([$brandFilterData, $priceFilterData]);

        foreach ($filterableFields as $field) {
            $filterType = $field->pivot->filter_type ? FilterType::from($field->pivot->filter_type) : null;

            // Merge field options with pivot options (pivot options take precedence)
            $fieldOptions = $field->options ?? [];
            $pivotOptions = $field->pivot->options ?? [];

            // Build query excluding this specific field filter
            $fieldQuery = $this->buildFilteredQueryExcluding($criteria, (string) $field->id);
            $fieldFilterData = $this->aggregateFieldFilter($fieldQuery, $field, $productClass->id, $filterType, $field->pivot->filter_weight ?? 0, array_merge($fieldOptions, $pivotOptions));

            $filterData->push($fieldFilterData);
        }

        return $filterData;
    }

    /**
     * Aggregate filter data for a product field.
     */
    private function aggregateFieldFilter(Builder $query, ProductField $field, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options = null): ProductFilterData
    {
        $productFieldId = $field->id;

        // Handle regular product field filters
        if ($filterType === FilterType::Range) {
            return $this->aggregateRangeFilter($query, $field, $productClassId, $filterType, $filterWeight, $options);
        }

        if (in_array($filterType, [FilterType::Checkboxes, FilterType::Select])) {
            return $this->aggregateCheckboxFilter($query, $field, $productClassId, $filterType, $filterWeight, $options);
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
    private function aggregateRangeFilter(Builder $query, ProductField $field, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = (clone $query)->pluck('id');

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
    private function aggregateCheckboxFilter(Builder $query, ProductField $field, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = (clone $query)->pluck('id');

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
    private function aggregateBrandFilter(Builder $query, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = (clone $query)->pluck('id');

        // Get brands that are used by filtered products, with counts
        $results = Brand::whereHas('products', function ($q) use ($productIds) {
            $q->whereIn('products.id', $productIds);
        })
            ->withCount(['products' => function ($q) use ($productIds) {
                $q->whereIn('products.id', $productIds);
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
    private function aggregatePriceFilter(Builder $query, int $productClassId, ?FilterType $filterType, int $filterWeight, ?array $options): ProductFilterData
    {
        $productIds = (clone $query)->pluck('id');

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
     * This is a static method for use cases where we don't have search criteria (e.g., CategoryController).
     */
    public static function aggregateAllForQuery(Builder $query, ProductClass $productClass): Collection
    {
        $instance = new self;
        $filterableFields = $productClass->filterableFields()->get();

        // Aggregate Brand filter
        $brandFilterData = $instance->aggregateBrandFilter($query, $productClass->id, FilterType::Checkboxes, -1, null);

        // Aggregate Price filter
        $priceFilterData = $instance->aggregatePriceFilter($query, $productClass->id, FilterType::Range, -2, null);

        // Aggregate filter data for each field
        $filterData = collect([$brandFilterData, $priceFilterData]);

        foreach ($filterableFields as $field) {
            $filterType = $field->pivot->filter_type ? FilterType::from($field->pivot->filter_type) : null;

            // Merge field options with pivot options (pivot options take precedence)
            $fieldOptions = $field->options ?? [];
            $pivotOptions = $field->pivot->options ?? [];

            $fieldFilterData = $instance->aggregateFieldFilter($query, $field, $productClass->id, $filterType, $field->pivot->filter_weight ?? 0, array_merge($fieldOptions, $pivotOptions));

            $filterData->push($fieldFilterData);
        }

        return $filterData;
    }
}
