<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\CartItem;
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

        \Illuminate\Support\Facades\Log::debug(self::$productIds);
        $randomProductIds = $count == 1 ? [array_rand(self::$productIds)] : array_rand(self::$productIds, $count);
        \Illuminate\Support\Facades\Log::debug($randomProductIds);
        return array_map(fn($id) => self::$productIds[$id], $randomProductIds);
    }
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create categories first
        $this->createCategories();

        // Create products with categories
        Product::factory(100)->create();

        $this->createUsersWithCartItems();
        //User::factory(10)->hasCartItems(3)->create();
    }

    private function createUsersWithCartItems(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $itemsCount = mt_rand(0, 5);
            if ($itemsCount > 0) {
                $user = User::factory()->state([
                    'email' => 'example' . $i . '@example.com',
                ])->create();
                foreach ($this->getRandomProductIds($itemsCount) as $productId) {
                    CartItem::factory()->for($user)->state([
                        'product_id' => $productId,
                    ])->create();
                }
            } else {
                User::factory()->state([
                    'email' => 'example' . $i . '@example.com',
                ])->create();
            }
        }
    }

    /**
     * Create a two-level category tree with 15-20 items
     */
    private function createCategories(): void
    {
        // Level 1 - Root Categories
        $electronics = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and gadgets',
            'is_active' => true,
        ]);

        $clothing = Category::create([
            'name' => 'Clothing & Fashion',
            'slug' => 'clothing-fashion',
            'description' => 'Fashion and clothing items',
            'is_active' => true,
        ]);

        $home = Category::create([
            'name' => 'Home & Garden',
            'slug' => 'home-garden',
            'description' => 'Home improvement and garden supplies',
            'is_active' => true,
        ]);

        $sports = Category::create([
            'name' => 'Sports & Outdoors',
            'slug' => 'sports-outdoors',
            'description' => 'Sports equipment and outdoor gear',
            'is_active' => true,
        ]);

        $booksMedia = Category::create([
            'name' => 'Books & Media',
            'slug' => 'books-media',
            'description' => 'Books, movies, and digital media',
            'is_active' => true,
        ]);

        // Level 2 - Subcategories for Electronics
        $smartphones = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'description' => 'Mobile phones and accessories',
            'is_active' => true,
            'parent_id' => $electronics->id,
        ]);

        $laptops = Category::create([
            'name' => 'Laptops & Computers',
            'slug' => 'laptops-computers',
            'description' => 'Laptops, desktops, and computer accessories',
            'is_active' => true,
            'parent_id' => $electronics->id,
        ]);

        $audio = Category::create([
            'name' => 'Audio & Headphones',
            'slug' => 'audio-headphones',
            'description' => 'Speakers, headphones, and audio equipment',
            'is_active' => true,
            'parent_id' => $electronics->id,
        ]);

        $cameras = Category::create([
            'name' => 'Cameras & Photography',
            'slug' => 'cameras-photography',
            'description' => 'Digital cameras and photography equipment',
            'is_active' => true,
            'parent_id' => $electronics->id,
        ]);

        // Level 2 - Subcategories for Clothing
        $mensClothing = Category::create([
            'name' => 'Men\'s Clothing',
            'slug' => 'mens-clothing',
            'description' => 'Clothing for men',
            'is_active' => true,
            'parent_id' => $clothing->id,
        ]);

        $womensClothing = Category::create([
            'name' => 'Women\'s Clothing',
            'slug' => 'womens-clothing',
            'description' => 'Clothing for women',
            'is_active' => true,
            'parent_id' => $clothing->id,
        ]);

        $shoes = Category::create([
            'name' => 'Shoes & Footwear',
            'slug' => 'shoes-footwear',
            'description' => 'All types of footwear',
            'is_active' => true,
            'parent_id' => $clothing->id,
        ]);

        $accessories = Category::create([
            'name' => 'Fashion Accessories',
            'slug' => 'fashion-accessories',
            'description' => 'Bags, jewelry, and fashion accessories',
            'is_active' => true,
            'parent_id' => $clothing->id,
        ]);

        // Level 2 - Subcategories for Home & Garden
        $furniture = Category::create([
            'name' => 'Furniture',
            'slug' => 'furniture',
            'description' => 'Home and office furniture',
            'is_active' => true,
            'parent_id' => $home->id,
        ]);

        $kitchen = Category::create([
            'name' => 'Kitchen & Dining',
            'slug' => 'kitchen-dining',
            'description' => 'Kitchen appliances and dining accessories',
            'is_active' => true,
            'parent_id' => $home->id,
        ]);

        $garden = Category::create([
            'name' => 'Garden & Outdoor',
            'slug' => 'garden-outdoor',
            'description' => 'Garden tools and outdoor furniture',
            'is_active' => true,
            'parent_id' => $home->id,
        ]);

        // Level 2 - Subcategories for Sports
        $fitness = Category::create([
            'name' => 'Fitness & Exercise',
            'slug' => 'fitness-exercise',
            'description' => 'Fitness equipment and exercise gear',
            'is_active' => true,
            'parent_id' => $sports->id,
        ]);

        $outdoor = Category::create([
            'name' => 'Outdoor Recreation',
            'slug' => 'outdoor-recreation',
            'description' => 'Camping, hiking, and outdoor activities',
            'is_active' => true,
            'parent_id' => $sports->id,
        ]);

        // Level 2 - Subcategories for Books & Media
        $books = Category::create([
            'name' => 'Books',
            'slug' => 'books',
            'description' => 'Physical and digital books',
            'is_active' => true,
            'parent_id' => $booksMedia->id,
        ]);

        $movies = Category::create([
            'name' => 'Movies & TV',
            'slug' => 'movies-tv',
            'description' => 'DVDs, Blu-rays, and streaming content',
            'is_active' => true,
            'parent_id' => $booksMedia->id,
        ]);

        // Total: 5 root categories + 15 subcategories = 20 categories
    }
}
