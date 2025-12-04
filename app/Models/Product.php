<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Illuminate\Notifications\Notifiable;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, HasUuids/* , Notifiable */;

    protected $fillable = [
        'title',
        'description',
        'price',
        'imageUrl',
        'category_id',
        'sku',
        'product_class_id',
        'brand_id',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the cart items for the product.
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the product class that owns the product.
     */
    public function productClass()
    {
        return $this->belongsTo(ProductClass::class);
    }

    /**
     * Get the field values for the product.
     */
    public function fieldValues()
    {
        return $this->hasMany(ProductFieldValue::class);
    }

    /**
     * Get the brand that owns the product.
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope a query to apply filters for category, brand, and sorting.
     */
    #[Scope]
    protected function defaultFilter(Builder $query, array $filter): void
    {
        // Apply category filter
        if (isset($filter['category_id'])) {
            $query->where('category_id', $filter['category_id']);
        }

        // Apply brand filter (id or slug)
        if (isset($filter['brand_id'])) {
            if (is_array($filter['brand_id'])) {
                $brandIds = unique(array_map('intval', $filter['brand_id']));
                // Safeguard: skip empty arrays to avoid SQL errors
                if (! empty($brandIds)) {
                    $query->whereIn('brand_id', $brandIds);
                }
            } else {
                $query->where('brand_id', (int) $filter['brand_id']);
            }
        } elseif (isset($filter['brand_slug'])) {
            $query->whereHas('brand', function ($q) use ($filter) {
                $q->where('slug', $filter['brand_slug']);
            });
        }

        // Apply sorting
        if (isset($filter['sort_by']) && isset($filter['sort_direction'])) {
            $query->orderBy($filter['sort_by'], $filter['sort_direction']);
        }
    }

    /**
     * Set the product class ID (only allowed during creation).
     */
    public function setProductClassId(string $productClassId): void
    {
        if ($this->exists) {
            throw new \RuntimeException('Product class ID cannot be changed after creation.');
        }

        $this->attributes['product_class_id'] = $productClassId;
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent product_class_id from being updated after creation
        static::updating(function (Product $product) {
            if ($product->isDirty('product_class_id') && $product->getOriginal('product_class_id') !== null) {
                // Restore the original value to prevent the update
                $product->product_class_id = $product->getOriginal('product_class_id');
            }
        });
    }
}
