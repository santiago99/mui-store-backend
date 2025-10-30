<?php

namespace App\Models;

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
        'price',
        'imageUrl',
        'category_id',
        'sku',
        'product_class_id',
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
}
