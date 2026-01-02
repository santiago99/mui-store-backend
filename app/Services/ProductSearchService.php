<?php

namespace App\Services;

use App\Contracts\ProductSearchEngine;
use App\DTO\ProductSearchCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductSearchService
{
    public function __construct(
        private ProductSearchEngine $searchEngine
    ) {}

    public function search(ProductSearchCriteria $criteria): LengthAwarePaginator
    {
        return $this->searchEngine->search($criteria);
    }

    public function getFilters(ProductSearchCriteria $criteria): ?Collection
    {
        return $this->searchEngine->getFilters($criteria);
    }
}
