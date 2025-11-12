<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($productClass) {
            if (empty($productClass->slug)) {
                $productClass->slug = Str::slug($productClass->name);
            }
        });
    }

    /**
     * Get the fields for the product class.
     */
    public function fields()
    {
        return $this->belongsToMany(ProductField::class, 'product_class_product_field')
            ->withPivot('weight', 'is_filter', 'filter_type', 'filter_weight', 'options')
            ->orderByPivot('weight');
    }

    /**
     * Get the filterable fields for the product class.
     */
    public function filterableFields()
    {
        return $this->belongsToMany(ProductField::class, 'product_class_product_field')
            ->withPivot('weight', 'is_filter', 'filter_type', 'filter_weight', 'options')
            ->wherePivot('is_filter', true)
            ->orderByPivot('filter_weight');
    }

    /**
     * Get the products for the product class.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the categories for the product class.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
