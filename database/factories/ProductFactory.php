<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ProductFieldValue;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Cached leaf categories to avoid multiple database queries.
     */
    private static ?array $leafCategories = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        //$category = $this->getRandomLeafCategory();
        return [
            'title' => $this->faker->words(3, true),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'imageUrl' => '',
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'sku' => $this->faker->uuid(),
        ];
    }

    public function withFieldValues(): static
    {
        return $this->afterCreating(function (\App\Models\Product $product) {
            $product->product_class_id = $product->category?->product_class_id;
            // Create field values for the product
            foreach ($product->productClass->fields as $field) {
                $values = [
                    'product_id' => $product->id,
                    'product_field_id' => $field->id,
                    'value_string' => null,
                    'value_int' => null,
                    'value_float' => null
                ];

                switch ($field->type) {
                    case \App\Enums\ProductFieldType::Integer:
                        $values['value_int'] = $this->faker->numberBetween(1, 1000);
                        break;
                    case \App\Enums\ProductFieldType::Float:
                        $values['value_float'] = $this->faker->randomFloat(2, 0.1, 999.99);
                        break;
                    case \App\Enums\ProductFieldType::String:
                        $values['value_string'] = $this->faker->words(2, true);
                        break;
                    case \App\Enums\ProductFieldType::Enum:
                        $values['value_string'] = $this->faker->randomElement($field->options['enum_options'] ?? ['Option 1', 'Option 2']);
                        break;
                }

                ProductFieldValue::create($values)->save();
            }
        });
    }
    /**
     * Get a random leaf category (categories without children).
     * Uses static caching to fetch categories only once per factory execution.
     */
    private function getRandomLeafCategory(): ?\App\Models\Category
    {
        // Initialize cache if not already done
        if (self::$leafCategories === null) {
            self::$leafCategories = $this->fetchLeafCategories();
        }

        // Return random category from cached list
        if (empty(self::$leafCategories)) {
            return null;
        }

        return self::$leafCategories[array_rand(self::$leafCategories)];
    }

    /**
     * Fetch leaf categories from database with product_class_id eager loaded.
     */
    private function fetchLeafCategories(): array
    {
        // Get all leaf categories with product_class_id
        $leafCategories = \App\Models\Category::whereIsLeaf()
            ->with('productClass')
            ->get()
            ->all();

        return $leafCategories;
    }

    /**
     * Create a product with a product class and field values.
     */
    /* public function withFieldValues(): static
    {
        return $this->state(function (array $attributes) {
            $productClass = \App\Models\ProductClass::factory()->create();
            $fields = \App\Models\ProductField::factory()->count(3)->create();

            // Attach fields to product class
            foreach ($fields as $index => $field) {
                $productClass->fields()->attach($field->id, ['weight' => $index]);
            }

            return [
                'product_class_id' => $productClass->id,
            ];
        })->afterCreating(function (\App\Models\Product $product) {
            // Create field values for the product
            
        });
    } */
}
