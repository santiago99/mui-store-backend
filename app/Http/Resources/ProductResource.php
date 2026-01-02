<?php

namespace App\Http\Resources;

use App\DTO\ProductDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle ProductDTO
        if ($this->resource instanceof ProductDTO) {
            $dto = $this->resource;

            return [
                'id' => $dto->id,
                'sku' => $dto->sku,
                'title' => $dto->title,
                'description' => $dto->description,
                'price' => $dto->price,
                'imageUrl' => $dto->imageUrl,
                'categoryId' => $dto->categoryId,
                'category' => $dto->category ? [
                    'id' => $dto->category->id,
                    'name' => $dto->category->name,
                    'slug' => $dto->category->slug,
                ] : null,
                'productClassId' => $dto->productClassId,
                'brandId' => $dto->brandId,
                'brand' => $dto->brand ? [
                    'id' => $dto->brand->id,
                    'name' => $dto->brand->name,
                    'slug' => $dto->brand->slug,
                ] : null,
                'fields' => array_map(function ($field) {
                    return [
                        'id' => $field->id,
                        'type' => $field->type->value,
                        'name' => $field->name,
                        'options' => $field->options,
                        'value' => $field->value,
                    ];
                }, $dto->fields),
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ];
        }

        // Legacy support: Handle Eloquent models (for show() method)
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'imageUrl' => $this->imageUrl,
            'categoryId' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'productClassId' => $this->product_class_id,
            'brandId' => $this->brand_id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'fields' => ProductFieldValueResource::collection($this->whenLoaded('fieldValues')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
