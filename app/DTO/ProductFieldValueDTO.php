<?php

namespace App\DTO;

use App\Enums\ProductFieldType;

class ProductFieldValueDTO
{
    public function __construct(
        public int $id,
        public ProductFieldType $type,
        public string $name,
        public ?array $options,
        public mixed $value,
    ) {}
}
