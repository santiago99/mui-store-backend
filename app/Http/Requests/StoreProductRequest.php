<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'imageUrl' => 'required|url|max:500',
            'sku' => 'nullable|string|unique:products,sku',
            'product_class_id' => 'nullable|exists:product_classes,id',
            'field_values' => 'nullable|array',
            'field_values.*' => 'nullable',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('product_class_id') && $this->has('field_values')) {
                $productClassId = $this->input('product_class_id');
                $fieldValues = $this->input('field_values', []);

                if ($productClassId) {
                    $productClass = \App\Models\ProductClass::with('fields')->find($productClassId);

                    if ($productClass) {
                        foreach ($fieldValues as $fieldId => $value) {
                            $field = $productClass->fields->find($fieldId);

                            if (! $field) {
                                $validator->errors()->add("field_values.{$fieldId}", "Field {$fieldId} does not belong to this product class.");

                                continue;
                            }

                            if ($value !== null) {
                                $this->validateFieldValue($validator, $field, $value, "field_values.{$fieldId}");
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Validate field value based on field type.
     */
    private function validateFieldValue($validator, $field, $value, $key): void
    {
        switch ($field->type) {
            case \App\Enums\ProductFieldType::Integer:
                if (! is_numeric($value) || (int) $value != $value) {
                    $validator->errors()->add($key, "Field '{$field->name}' must be an integer.");
                }
                break;

            case \App\Enums\ProductFieldType::Float:
                if (! is_numeric($value)) {
                    $validator->errors()->add($key, "Field '{$field->name}' must be a number.");
                }
                break;

            case \App\Enums\ProductFieldType::String:
                if (! is_string($value)) {
                    $validator->errors()->add($key, "Field '{$field->name}' must be a string.");
                }
                break;

            case \App\Enums\ProductFieldType::Enum:
                $allowedValues = $field->options['enum_options'] ?? [];
                if (! in_array($value, $allowedValues)) {
                    $validator->errors()->add($key, "Field '{$field->name}' must be one of: ".implode(', ', $allowedValues));
                }
                break;
        }
    }
}
