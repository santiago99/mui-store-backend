<?php

namespace App\DTO;

class ProductSearchCriteria
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ?int $categoryId = null,
        public int|array|null $brandId = null,
        public ?string $brandSlug = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public string $sortBy = 'created_at',
        public string $sortDirection = 'desc',
        public array $filters = [],
    ) {}
}
