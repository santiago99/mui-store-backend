<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private static ?array $productIds = null;

    private function getRandomProductIds($count = 1): array
    {
        if (self::$productIds === null) {
            self::$productIds = \App\Models\Product::pluck('id')->toArray();
        }

        // \Illuminate\Support\Facades\Log::debug(self::$productIds);
        $randomProductIds = $count == 1 ? [array_rand(self::$productIds)] : array_rand(self::$productIds, $count);

        // \Illuminate\Support\Facades\Log::debug($randomProductIds);
        return array_map(fn ($id) => self::$productIds[$id], $randomProductIds);
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create categories and product classes first
        $this->call(ProductCatalogSeeder::class);

        $this->createUsersWithCartItems();
        // User::factory(10)->hasCartItems(3)->create();
    }

    private function createUsersWithCartItems(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $itemsCount = mt_rand(0, 5);
            if ($itemsCount > 0) {
                $user = User::factory()->state([
                    'email' => 'example'.$i.'@example.com',
                ])->create();
                foreach ($this->getRandomProductIds($itemsCount) as $productId) {
                    CartItem::factory()->for($user)->state([
                        'product_id' => $productId,
                    ])->create();
                }
            } else {
                User::factory()->state([
                    'email' => 'example'.$i.'@example.com',
                ])->create();
            }
        }
    }
}
