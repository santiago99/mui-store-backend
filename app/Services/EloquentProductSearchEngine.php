<?php

namespace App\Services;

use App\Contracts\ProductSearchEngine;
use App\DTO\BrandDTO;
use App\DTO\CategoryDTO;
use App\DTO\ProductDTO;
use App\DTO\ProductFieldValueDTO;
use App\DTO\ProductSearchCriteria;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class EloquentProductSearchEngine implements ProductSearchEngine
{
    private ?Builder $filteredQuery = null;

    public function search(ProductSearchCriteria $criteria): PaginatorContract
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

        // Build filtered query (before pagination)
        $this->filteredQuery = Product::with('category', 'brand')
            ->defaultFilter($filterParams)
            ->extendedFilter($criteria->filters);

        // Get paginated results
        // We have to clone the query to not to apply pagination to original query
        $paginatedResults = (clone $this->filteredQuery)->paginate($criteria->perPage, ['*'], 'page', $criteria->page);
        //$paginatedResults = $this->filteredQuery->paginate($criteria->perPage, ['*'], 'page', $criteria->page);

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

        \Illuminate\Support\Facades\Log::info('ProductDTO', [
            'id' => $product->id,
            'sku' => $product->sku,
            'title' => $product->title,
            'description' => $product->description,
            'price' => $product->price,
            'imageUrl' => $product->imageUrl,
            'categoryId' => $product->category_id,
            'productClassId' => $product->product_class_id,
            'brandId' => $product->brand_id,
            'fields' => $fieldDTOs,
            'createdAt' => $product->created_at->toDateTimeString(),
            'updatedAt' => $product->updated_at->toDateTimeString(),
        ]);

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
     */
    public function getFilters(ProductSearchCriteria $criteria): ?Collection
    {
        // Only calculate filters when categoryId is present and page == 1
        if ($criteria->categoryId === null || $criteria->page !== 1) {
            return null;
        }

        // Ensure we have a filtered query (should be set by search() method)
        if ($this->filteredQuery === null) {
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
            $this->filteredQuery = Product::with('category', 'brand')
                ->defaultFilter($filterParams)
                ->extendedFilter($criteria->filters);
        }

        // Get category and its product class
        $category = Category::find($criteria->categoryId);
        $productClass = $category?->productClass;

        if (! $productClass) {
            return null;
        }

        // Create aggregator with the filtered query
        $aggregator = new ProductFilterAggregator($this->filteredQuery);

        // Aggregate all filters
        return $aggregator->aggregateAll($productClass);
    }
}
