<?php

namespace App\Contracts;

use App\DTO\ProductSearchCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductSearchEngine
{
    public function search(ProductSearchCriteria $criteria): LengthAwarePaginator;

    public function getFilters(ProductSearchCriteria $criteria): ?Collection;
}
