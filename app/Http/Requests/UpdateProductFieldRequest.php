<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductFieldRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:product_fields,slug,'.$this->route('product_field'),
            'type' => 'sometimes|in:integer,float,string,enum',
            'options' => 'sometimes|array',
            'options.enum_options' => 'required_if:type,enum|array',
            'options.enum_options.*' => 'string',
        ];
    }
}
