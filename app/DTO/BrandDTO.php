<?php

namespace App\DTO;

class BrandDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}
}
