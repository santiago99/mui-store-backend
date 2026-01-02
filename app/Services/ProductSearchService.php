<?php

namespace App\Services;

use App\Contracts\ProductSearchEngine;
use App\DTO\ProductSearchCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductSearchService
{
    public function __construct(
        private ProductSearchEngine $searchEngine
    ) {}

    public function search(ProductSearchCriteria $criteria): LengthAwarePaginator
    {
        return $this->searchEngine->search($criteria);
    }
}
