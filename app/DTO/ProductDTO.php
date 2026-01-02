<?php

namespace App\DTO;

class ProductDTO
{
    public function __construct(
        public string $id,
        public ?string $sku,
        public string $title,
        public ?string $description,
        public float $price,
        public ?string $imageUrl,
        public ?int $categoryId,
        public ?CategoryDTO $category,
        public ?int $productClassId,
        public ?int $brandId,
        public ?BrandDTO $brand,
        public array $fields,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
