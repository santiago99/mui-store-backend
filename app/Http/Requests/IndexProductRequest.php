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

        // Parse filters if it's a JSON string
        /* if ($this->has('filters') && is_string($this->input('filters'))) {
            $parsed = json_decode($this->input('filters'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $this->merge(['filters' => $parsed]);
            }
        } */
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
            'filters' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (! is_array($value)) {
                        return;
                    }

                    foreach ($value as $key => $filterValue) {
                        // Validate key: must be integer (product_field_id) or special values (-1, -2)
                        if (! is_numeric($key) && $key !== '-1' && $key !== '-2') {
                            $fail('The '.$attribute.' keys must be product field IDs (integers) or special values (-1, -2).');
                        }

                        // Validate value structure
                        if (is_array($filterValue)) {
                            // Check if it's a range filter (has min/max keys)
                            if (isset($filterValue['min']) || isset($filterValue['max'])) {
                                // Range filter: validate min/max are numeric
                                if (isset($filterValue['min']) && ! is_numeric($filterValue['min'])) {
                                    $fail('The '.$attribute.'.'.$key.'.min must be numeric.');
                                }
                                if (isset($filterValue['max']) && ! is_numeric($filterValue['max'])) {
                                    $fail('The '.$attribute.'.'.$key.'.max must be numeric.');
                                }
                                if (isset($filterValue['min']) && isset($filterValue['max']) && (float) $filterValue['min'] > (float) $filterValue['max']) {
                                    $fail('The '.$attribute.'.'.$key.'.min must be less than or equal to max.');
                                }
                            } else {
                                // Checkboxes/select filter: must be non-empty array
                                if (empty($filterValue)) {
                                    $fail('The '.$attribute.'.'.$key.' must not be empty when provided as an array.');
                                }
                            }
                        } elseif (! is_string($filterValue) && ! is_numeric($filterValue)) {
                            $fail('The '.$attribute.'.'.$key.' must be a string, number, or array.');
                        }
                    }
                },
            ],
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
