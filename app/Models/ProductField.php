<?php

namespace App\Models;

use App\Enums\ProductFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'options',
    ];

    protected $casts = [
        'type' => ProductFieldType::class,
        'options' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($productField) {
            if (empty($productField->slug)) {
                $productField->slug = Str::slug($productField->name);
            }
        });
    }

    /**
     * Get the product classes that use this field.
     */
    public function productClasses()
    {
        return $this->belongsToMany(ProductClass::class, 'product_class_product_field')
            ->withPivot('weight', 'is_filter', 'filter_type', 'filter_weight', 'options');
    }

    /**
     * Get the field values for this field.
     */
    public function fieldValues()
    {
        return $this->hasMany(ProductFieldValue::class);
    }
}
