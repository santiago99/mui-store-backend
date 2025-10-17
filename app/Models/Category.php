<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Support\Str;


class Category extends Model
{
    use NodeTrait;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the products for the category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all descendants of this category.
     */
    public function descendants()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get root categories (categories without parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get the full path of the category (e.g., "Electronics > Phones > Smartphones").
     */
    // public function getFullPathAttribute()
    // {
    //     $ancestors = $this->ancestors()->pluck('name')->toArray();
    //     $ancestors[] = $this->name;

    //     return implode(' > ', $ancestors);
    // }

    /**
     * Get the depth level of the category.
     */
    // public function getDepthAttribute()
    // {
    //     return $this->ancestors()->count();
    // }


    /**
     * Get all products from this category and its descendants.
     */
    // public function getAllProducts()
    // {
    //     $categoryIds = $this->descendants()->pluck('id')->toArray();
    //     $categoryIds[] = $this->id;

    //     return Product::whereIn('category_id', $categoryIds);
    // }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
