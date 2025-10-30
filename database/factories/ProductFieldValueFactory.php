<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductFieldValue>
 */
class ProductFieldValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productField = \App\Models\ProductField::factory()->create();

        $values = [
            'value_string' => null,
            'value_int' => null, 
            'value_float' => null
        ];

        switch ($productField->type) {
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
                $values['value_string'] = $this->faker->randomElement($productField->options['enum_options'] ?? ['Option 1', 'Option 2']);
                break;
        }

        return $values;
    }
}
