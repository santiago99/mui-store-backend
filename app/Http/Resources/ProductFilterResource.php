<?php

namespace App\Http\Resources;

use App\Data\ProductFilterData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductFilterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // If resource is a ProductFilterData DTO
        if ($this->resource instanceof ProductFilterData) {
            $data = $this->resource;

            $result = [
                'id' => $data->productFieldId,
                'name' => $data->name,
                'slug' => $data->slug,
                'type' => $data->type->value,
                'filterType' => $data->filterType?->value,
                'filterWeight' => $data->filterWeight,
                'options' => $data->options,
            ];

            // Add min/max for range filters
            if ($data->filterType === \App\Enums\FilterType::Range) {
                $result['min'] = $data->min;
                $result['max'] = $data->max;
            }

            // Add filter options for checkboxes/select
            if (in_array($data->filterType, [\App\Enums\FilterType::Checkboxes, \App\Enums\FilterType::Select])) {
                $result['filterOptions'] = $data->filterOptions;
            }

            return $result;
        }

        // Legacy support: if resource is a ProductField with pivot (for backward compatibility)
        $filterType = $this->pivot->filter_type ? \App\Enums\FilterType::from($this->pivot->filter_type) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'filterType' => $filterType?->value,
            'filterWeight' => $this->pivot->filter_weight ?? 0,
            'options' => $this->pivot->options,
        ];
    }
}
