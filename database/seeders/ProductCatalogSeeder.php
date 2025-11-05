<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductClass;
use App\Models\ProductField;
use App\Models\Brand;
use Illuminate\Database\Seeder;
use \Illuminate\Database\Eloquent\Collection;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createCategories();
        $leafCategories = Category::whereIsLeaf()->with('productClass')->get();
        $this->createProductClassesForLeafCategories($leafCategories);
        $brands = Brand::factory(rand(7,10))->create();
        // Create products with categories and field values
        Product::factory(100)->recycle($brands)->recycle($leafCategories)->withFieldValues()->create();
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

    /**
     * Create ProductClass for each leaf category with 3-5 fields
     */
    private function createProductClassesForLeafCategories(Collection $leafCategories): void
    {
        // Get all leaf categories (categories without children)

        foreach ($leafCategories as $category) {

            // Create a ProductClass for this category
            $productClass = ProductClass::factory()->state([
                'name' => $category->name,
                'slug' => $category->slug . '-class',])->create();

            // Associate the ProductClass with the category
            $category->productClass()->associate($productClass)->save();

            $fieldCount = rand(3, 5);
            // \Illuminate\Support\Facades\Log::debug('Creating fields for product class', [
            //     'productClass' => $productClass->name,
            //     'fieldCount' => $fieldCount,
            // ]);
            // Create 3-5 fields for this ProductClass
            $weight = 0;
            ProductField::factory($fieldCount)
                ->hasAttached($productClass, fn () => ['weight' => $weight++])
                ->create();
        }
    }
}
