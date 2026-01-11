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

    /**
     * Extract virtual filters (-1 for brand, -2 for price) from filters array
     * and populate the corresponding direct properties.
     */
    public function extractVirtualFilters(): void
    {
        // Extract brand filter (-1)
        if (isset($this->filters['-1']) && $this->brandId === null) {
            $brandValue = $this->filters['-1'];
            if (is_array($brandValue) && ! empty($brandValue)) {
                $this->brandId = count($brandValue) === 1 ? (int) $brandValue[0] : array_map('intval', $brandValue);
            }
            unset($this->filters['-1']);
        }

        // Extract price filter (-2)
        if (isset($this->filters['-2']) && $this->priceMin === null && $this->priceMax === null) {
            $priceValue = $this->filters['-2'];
            if (is_array($priceValue)) {
                if (isset($priceValue['min'])) {
                    $this->priceMin = (float) $priceValue['min'];
                }
                if (isset($priceValue['max'])) {
                    $this->priceMax = (float) $priceValue['max'];
                }
            }
            unset($this->filters['-2']);
        }
    }
}
