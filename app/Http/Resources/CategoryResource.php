<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // \Illuminate\Support\Facades\Log::debug('CategoryResource::toArray', [
        //     'name' => $this->name,
        //     'relationLoaded' => $this->relationLoaded('children'),
        //     'whenLoaded' => $this->whenLoaded('children', 'Loaded', 'Not Loaded'),
        //     'whenLoadedAncestors' => $this->whenLoaded('ancestors', 'Loaded', 'Not Loaded'),
        // ]);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'isActive' => $this->is_active,
            'parentId' => $this->parent_id,
            'isLeaf' => $this->isLeaf(),
            'productsCount' => $this->whenCounted('products'),
            // 'children' => $this->whenLoaded('children', fn() => CategoryResource::collection($this->children)),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            // 'parent' => new CategoryResource($this->whenLoaded('parent')),
            'ancestors' => $this->whenLoaded('ancestors', fn () => $this->ancestors->map(
                fn ($ancestor) => [
                    'id' => $ancestor->id,
                    'name' => $ancestor->name,
                ]
            )),
            'createdAt' => $this->created_at->toDateTimeString(),
            'updatedAt' => $this->updated_at->toDateTimeString(),
        ];
    }
}
