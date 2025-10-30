<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_field_id',
        'value_string',
        'value_int',
        'value_float',
    ];

    protected $primaryKey = ['product_id', 'product_field_id'];

    public $incrementing = false;

    /**
     * Get the product that owns the field value.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product field that owns the field value.
     */
    public function productField()
    {
        return $this->belongsTo(ProductField::class);
    }

    /**
     * Get the value based on the field type.
     */
    public function getValueAttribute()
    {
        return match ($this->productField->type) {
            \App\Enums\ProductFieldType::Integer => $this->value_int,
            \App\Enums\ProductFieldType::Float => $this->value_float,
            \App\Enums\ProductFieldType::String, \App\Enums\ProductFieldType::Enum => $this->value_string,
        };
    }

    /**
     * Set the value based on the field type.
     */
    public function setValueAttribute($value)
    {
        $this->value_string = null;
        $this->value_int = null;
        $this->value_float = null;

        match ($this->productField->type) {
            \App\Enums\ProductFieldType::Integer => $this->value_int = $value,
            \App\Enums\ProductFieldType::Float => $this->value_float = $value,
            \App\Enums\ProductFieldType::String, \App\Enums\ProductFieldType::Enum => $this->value_string = $value,
        };
    }
}
