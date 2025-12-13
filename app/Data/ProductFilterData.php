<?php

namespace App\Data;

use App\Enums\FilterType;
use App\Enums\ProductFieldType;

class ProductFilterData
{
    public function __construct(
        public int $productFieldId,
        public int $productClassId,
        public ?FilterType $filterType,
        public string $name,
        public string $slug,
        public ProductFieldType $type,
        public int $filterWeight,
        public ?array $options = null,
        public ?float $min = null,
        public ?float $max = null,
        public array $filterOptions = [],
    ) {}
}
