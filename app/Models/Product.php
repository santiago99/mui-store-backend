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
     * Get the collections that contain the product.
     */
    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_product');
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
                $brandIds = array_unique(array_map('intval', $filter['brand_id']));
                // Safeguard: skip empty arrays to avoid SQL errors
                if (! empty($brandIds)) {
                    $query->whereIn('brand_id', $brandIds);
                }
            } else {
                $query->where('brand_id', (int) $filter['brand_id']);
            }
        } elseif (isset($filter['brand_slug'])) {
            // We can use whereHas since brand is always loaded with the product
            $query->whereHas('brand', function ($q) use ($filter) {
                $q->where('slug', $filter['brand_slug']);
            });
        }

        // Apply price range filter
        if (isset($filter['price_min']) && isset($filter['price_max'])) {
            $query->whereBetween('price', [(float) $filter['price_min'], (float) $filter['price_max']]);
        } elseif (isset($filter['price_min'])) {
            $query->where('price', '>=', (float) $filter['price_min']);
        } elseif (isset($filter['price_max'])) {
            $query->where('price', '<=', (float) $filter['price_max']);
        }

        // Apply sorting
        if (isset($filter['sort_by']) && isset($filter['sort_direction'])) {
            $query->orderBy($filter['sort_by'], $filter['sort_direction']);
        }
    }

    /**
     * Scope a query to apply filters from the filters parameter.
     */
    #[Scope]
    protected function extendedFilter(Builder $query, array $filters): void
    {
        if (empty($filters)) {
            return;
        }

        foreach ($filters as $key => $value) {
            $filterKey = (string) $key;

            // Handle product field filters
            $productFieldId = (int) $filterKey;
            if ($productFieldId <= 0) {
                continue;
            }

            // Get the product field to determine its type
            $productField = \App\Models\ProductField::find($productFieldId);
            if (! $productField) {
                continue;
            }

            // Determine filter type from value structure
            if (is_array($value)) {
                // Check if it's a range filter (has min/max keys)
                if (isset($value['min']) || isset($value['max'])) {
                    $this->applyRangeFilter($query, $productField, $value);
                } else {
                    // Checkboxes/select filter: array of values
                    $this->applyMultiValueFilter($query, $productField, $value);
                }
            } else {
                // Textfield filter: single value
                $this->applyTextfieldFilter($query, $productField, $value);
            }
        }
    }

    /**
     * Apply a range filter for a product field.
     */
    protected function applyRangeFilter(Builder $query, \App\Models\ProductField $productField, array $range): void
    {
        $valueColumn = match ($productField->type) {
            \App\Enums\ProductFieldType::Integer => 'value_int',
            \App\Enums\ProductFieldType::Float => 'value_float',
            default => 'value_string',
        };

        $query->whereHas('fieldValues', function ($q) use ($productField, $range, $valueColumn) {
            $q->where('product_field_id', $productField->id);

            if (isset($range['min']) && isset($range['max'])) {
                $q->whereBetween($valueColumn, [(float) $range['min'], (float) $range['max']]);
            } elseif (isset($range['min'])) {
                $q->where($valueColumn, '>=', (float) $range['min']);
            } elseif (isset($range['max'])) {
                $q->where($valueColumn, '<=', (float) $range['max']);
            }
        });
    }

    /**
     * Apply a multi-value filter (checkboxes/select) for a product field.
     */
    protected function applyMultiValueFilter(Builder $query, \App\Models\ProductField $productField, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $valueColumn = match ($productField->type) {
            \App\Enums\ProductFieldType::Integer => 'value_int',
            \App\Enums\ProductFieldType::Float => 'value_float',
            default => 'value_string',
        };

        // Convert values to appropriate types
        $typedValues = match ($productField->type) {
            \App\Enums\ProductFieldType::Integer => array_map('intval', $values),
            \App\Enums\ProductFieldType::Float => array_map('floatval', $values),
            default => array_map('strval', $values),
        };

        $query->whereHas('fieldValues', function ($q) use ($productField, $typedValues, $valueColumn) {
            $q->where('product_field_id', $productField->id)
                ->whereIn($valueColumn, $typedValues);
        });
    }

    /**
     * Apply a textfield filter for a product field.
     */
    protected function applyTextfieldFilter(Builder $query, \App\Models\ProductField $productField, mixed $value): void
    {
        $valueColumn = match ($productField->type) {
            \App\Enums\ProductFieldType::Integer => 'value_int',
            \App\Enums\ProductFieldType::Float => 'value_float',
            default => 'value_string',
        };

        $query->whereHas('fieldValues', function ($q) use ($productField, $value, $valueColumn) {
            $q->where('product_field_id', $productField->id);

            // For string fields, use LIKE for partial matching; for numeric, use exact match
            if ($productField->type === \App\Enums\ProductFieldType::String || $productField->type === \App\Enums\ProductFieldType::Enum) {
                $q->where($valueColumn, 'LIKE', '%'.(string) $value.'%');
            } else {
                $typedValue = match ($productField->type) {
                    \App\Enums\ProductFieldType::Integer => (int) $value,
                    \App\Enums\ProductFieldType::Float => (float) $value,
                    default => $value,
                };
                $q->where($valueColumn, $typedValue);
            }
        });
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
