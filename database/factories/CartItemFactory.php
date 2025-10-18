<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Cached product IDs to avoid multiple database queries.
     */
    private static ?array $productIds = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //'user_id' => \App\Models\User::factory(),
            //'product_id' => $this->getRandomProductId(),
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }
}
