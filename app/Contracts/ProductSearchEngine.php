<?php

namespace App\Contracts;

use App\DTO\ProductSearchCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductSearchEngine
{
    public function search(ProductSearchCriteria $criteria): LengthAwarePaginator;
}
