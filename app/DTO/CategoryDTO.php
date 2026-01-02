<?php

namespace App\DTO;

class CategoryDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}
}
