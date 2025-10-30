<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductField>
 */
class ProductFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fieldTypes = ['integer', 'float', 'string', 'enum'];
        $type = $this->faker->randomElement($fieldTypes);

        $fieldNames = [
            'integer' => ['RAM (GB)', 'Storage (GB)', 'Screen Size (inches)', 'Weight (kg)', 'Battery Life (hours)'],
            'float' => ['Price', 'Weight', 'Screen Size', 'Battery Capacity', 'Processor Speed'],
            'string' => ['CPU Type', 'Operating System', 'Brand', 'Model', 'Color', 'Material'],
            'enum' => ['Resolution', 'Connection Type', 'Operating System', 'Storage Type', 'Processor Brand'],
        ];

        $name = $this->faker->randomElement($fieldNames[$type]);
        $options = null;

        if ($type === 'enum') {
            \Illuminate\Support\Facades\Log::debug($name);
            $enumOptions = [
                'Resolution' => ['1920x1080', '2560x1440', '3840x2160', '1366x768'],
                'Connection Type' => ['USB-A', 'USB-C', 'Bluetooth', 'WiFi', 'Ethernet'],
                'Operating System' => ['Windows', 'macOS', 'Linux', 'Android', 'iOS'],
                'Storage Type' => ['SSD', 'HDD', 'NVMe', 'eMMC'],
                'Processor Brand' => ['Intel', 'AMD', 'Apple', 'Qualcomm'],
            ];
            
            $enumKey = array_search($name, $fieldNames['enum']);
            $options = ['enum_options' => $enumOptions[$name] ?? ['Option 1', 'Option 2', 'Option 3']];
            /* \Illuminate\Support\Facades\Log::debug('Enum field type', [
                'name' => $name,
                'enumKey' => $enumKey,
                'enumOptions' => $enumOptions[$name],
                'options' => $options,
            ]); */
        }

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.$this->faker->unique()->randomNumber(4),
            'type' => $type,
            'options' => $options,
        ];
    }
}
