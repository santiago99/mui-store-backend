<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Cached leaf category IDs to avoid multiple database queries.
     */
    private static ?array $leafCategoryIds = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'imageUrl' => '',
            'category_id' => $this->getRandomLeafCategoryId(),
            'sku' => $this->faker->uuid(),
        ];
    }

    /**
     * Get a random leaf category ID (categories without children).
     * Uses static caching to fetch categories only once per factory execution.
     */
    private function getRandomLeafCategoryId(): ?int
    {
        // Initialize cache if not already done
        if (self::$leafCategoryIds === null) {
            self::$leafCategoryIds = $this->fetchLeafCategoryIds();
        }

        // Return random category ID from cached list
        if (empty(self::$leafCategoryIds)) {
            return null;
        }

        return self::$leafCategoryIds[array_rand(self::$leafCategoryIds)];
    }

    /**
     * Fetch leaf category IDs from database.
     */
    private function fetchLeafCategoryIds(): array
    {
        // Get all leaf categories (categories that don't have children)
        $leafCategories = \App\Models\Category::whereDoesntHave('children')->pluck('id')->toArray();

        if (empty($leafCategories)) {
            // If no leaf categories exist, get any category
            $anyCategory = \App\Models\Category::inRandomOrder()->first();

            return $anyCategory ? [$anyCategory->id] : [];
        }

        return $leafCategories;
    }
}
