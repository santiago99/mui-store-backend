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
            'brand_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        // Reject empty arrays
                        if (empty($value)) {
                            $fail('The '.$attribute.' must not be empty when provided as an array.');

                            return;
                        }

                        // Check that all values are integers
                        $brandIds = [];
                        foreach ($value as $id) {
                            if (! is_numeric($id)) {
                                $fail('The '.$attribute.' must be an array of integers.');

                                return;
                            }
                            $brandIds[] = (int) $id;
                        }

                        // Make one request with whereIn and check results count == array length
                        $validBrandsCount = \App\Models\Brand::whereIn('id', $brandIds)->count();
                        if ($validBrandsCount !== count($brandIds)) {
                            $fail('The '.$attribute.' must be an array of valid brand IDs.');
                        }
                    } elseif (! is_numeric($value) || ! \App\Models\Brand::where('id', (int) $value)->exists()) {
                        $fail('The '.$attribute.' must be a valid brand ID.');
                    }
                },
            ],
            'brand_slug' => 'string|exists:brands,slug',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'sort_by' => 'string|in:title,price,created_at',
            'sort_direction' => 'string|in:asc,desc',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $priceMin = $this->input('price_min');
            $priceMax = $this->input('price_max');

            if ($priceMin !== null && $priceMax !== null && (float) $priceMax < (float) $priceMin) {
                $validator->errors()->add('price_max', 'The price_max must be greater than or equal to price_min.');
            }
        });
    }
}
