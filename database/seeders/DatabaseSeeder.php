<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Collection;
use App\Models\Role;
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

        if (empty(self::$productIds)) {
            $this->command->error('No product IDs found');
            return [];
        }
        // \Illuminate\Support\Facades\Log::debug(self::$productIds);
        // Get $count random product indices
        $randomProductIndices = $count == 1 ? [array_rand(self::$productIds)] : array_rand(self::$productIds, $count);

        // \Illuminate\Support\Facades\Log::debug($randomProductIds);
        // Map the random product indices to product IDs'
        return array_map(fn ($index) => self::$productIds[$index], $randomProductIndices);
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedRolesAndAdmin();

        // Create categories and product classes first
        $this->call(ProductCatalogImportSeeder::class);

        $this->createCollections();
        $this->createUsersWithCartItems();
        // User::factory(10)->hasCartItems(3)->create();
    }

    private function seedRolesAndAdmin(): void
    {
        // Ensure roles exist
        $customer = Role::query()->firstOrCreate(['name' => Role::CUSTOMER]);
        $admin = Role::query()->firstOrCreate(['name' => Role::ADMIN]);

        // Backfill existing users without a role to customer
        User::query()->whereNull('role_id')->update(['role_id' => $customer->id]);

        // Promote ADMIN_EMAIL user to admin if present
        $adminEmail = (string) (env('ADMIN_EMAIL') ?? '');
        if ($adminEmail !== '') {
            /** @var User|null $adminUser */
            $adminUser = User::query()->where('email', $adminEmail)->first();
            if ($adminUser) {
                $adminUser->forceFill(['role_id' => $admin->id])->save();
            }
        }
    }

    private function createUsersWithCartItems(): void
    {
        $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');

        for ($i = 0; $i < 4; $i++) {
            $itemsCount = mt_rand(0, 5);
            $user = User::factory()->state([
                'email' => 'example'.$i.'@example.com',
                'role_id' => $customerRoleId,
            ])->create();

            if ($itemsCount > 0) {
                foreach ($this->getRandomProductIds($itemsCount) as $productId) {
                    CartItem::factory()->for($user)->state([
                        'product_id' => $productId,
                    ])->create();
                }
            }
        }
    }

    private function createCollections(): void
    {
        $productIds = \App\Models\Product::pluck('id')->toArray();

        if (count($productIds) < 12) {
            return; // Not enough products to create collections
        }

        // Create 'new' collection
        $newCollection = Collection::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New']
        );

        $newProductIds = $this->getRandomProductIds(12);
        $newCollection->products()->sync($newProductIds);

        // Create 'featured' collection
        $featuredCollection = Collection::firstOrCreate(
            ['slug' => 'featured'],
            ['name' => 'Featured']
        );

        $featuredProductIds = $this->getRandomProductIds(12);
        $featuredCollection->products()->sync($featuredProductIds);
    }
}
