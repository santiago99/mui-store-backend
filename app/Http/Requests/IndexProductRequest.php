<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'page' => 1,
            'per_page' => 24,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'category_id' => 'integer|exists:categories,id',
            'brand_id' => 'integer|exists:brands,id',
            'brand_slug' => 'string|exists:brands,slug',
            'sort_by' => 'string|in:title,price,created_at',
            'sort_direction' => 'string|in:asc,desc',
        ];
    }
}
