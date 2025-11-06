<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');
});

describe('Product API Filterins and sorting', function () {


    describe('GET /api/v1/products (index) - Filters', function () {
        describe('category_id filter', function () {
            test('can filter products by category_id', function () {
                $category1 = Category::factory()->create();
                $category2 = Category::factory()->create();

                $productsInCategory1 = Product::factory()->count(3)->create(['category_id' => $category1->id]);
                $productsInCategory2 = Product::factory()->count(2)->create(['category_id' => $category2->id]);

                $response = $this->getJson("/api/v1/products?category_id={$category1->id}");

                $response->assertSuccessful()
                    ->assertJsonCount(3, 'data')
                    ->assertJsonPath('data.0.category.id', $category1->id)
                    ->assertJsonPath('data.1.category.id', $category1->id)
                    ->assertJsonPath('data.2.category.id', $category1->id);
            });

            test('returns empty array when category has no products', function () {
                $category = Category::factory()->create();
                Product::factory()->count(2)->create(); // Products in other categories

                $response = $this->getJson("/api/v1/products?category_id={$category->id}");

                $response->assertSuccessful()
                    ->assertJsonCount(0, 'data');
            });

            test('rejects invalid category_id', function () {
                $response = $this->getJson('/api/v1/products?category_id=99999');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['category_id']);
            });

            test('rejects non-integer category_id', function () {
                $response = $this->getJson('/api/v1/products?category_id=invalid');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['category_id']);
            });
        });

        describe('brand_slug filter', function () {
            test('can filter products by brand_slug', function () {
                $brand1 = Brand::factory()->create(['name' => 'Brand One', 'slug' => 'brand-one']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two', 'slug' => 'brand-two']);

                $productsBrand1 = Product::factory()->count(3)->create(['brand_id' => $brand1->id]);
                $productsBrand2 = Product::factory()->count(2)->create(['brand_id' => $brand2->id]);

                $response = $this->getJson('/api/v1/products?brand_slug=brand-one');

                $response->assertSuccessful()
                    ->assertJsonCount(3, 'data')
                    ->assertJsonPath('data.0.brand.slug', 'brand-one')
                    ->assertJsonPath('data.1.brand.slug', 'brand-one')
                    ->assertJsonPath('data.2.brand.slug', 'brand-one');
            });

            test('returns empty array when brand has no products', function () {
                $brand = Brand::factory()->create(['name' => 'Empty Brand', 'slug' => 'empty-brand']);
                Product::factory()->count(2)->create(); // Products with other brands

                $response = $this->getJson('/api/v1/products?brand_slug=empty-brand');

                $response->assertSuccessful()
                    ->assertJsonCount(0, 'data');
            });

            test('rejects invalid brand_slug', function () {
                $response = $this->getJson('/api/v1/products?brand_slug=non-existent-brand');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['brand_slug']);
            });
        });

        describe('sorting', function () {
            test('can sort by title ascending', function () {
                $product1 = Product::factory()->create(['title' => 'Zebra Product']);
                $product2 = Product::factory()->create(['title' => 'Apple Product']);
                $product3 = Product::factory()->create(['title' => 'Banana Product']);

                $response = $this->getJson('/api/v1/products?sort_by=title&sort_direction=asc');

                $response->assertSuccessful()
                    ->assertJsonPath('data.0.title', 'Apple Product')
                    ->assertJsonPath('data.1.title', 'Banana Product')
                    ->assertJsonPath('data.2.title', 'Zebra Product');
            });

            test('can sort by title descending', function () {
                $product1 = Product::factory()->create(['title' => 'Zebra Product']);
                $product2 = Product::factory()->create(['title' => 'Apple Product']);
                $product3 = Product::factory()->create(['title' => 'Banana Product']);

                $response = $this->getJson('/api/v1/products?sort_by=title&sort_direction=desc');

                $response->assertSuccessful()
                    ->assertJsonPath('data.0.title', 'Zebra Product')
                    ->assertJsonPath('data.1.title', 'Banana Product')
                    ->assertJsonPath('data.2.title', 'Apple Product');
            });

            test('can sort by price ascending', function () {
                $product1 = Product::factory()->create(['price' => 100.00]);
                $product2 = Product::factory()->create(['price' => 50.00]);
                $product3 = Product::factory()->create(['price' => 75.00]);

                $response = $this->getJson('/api/v1/products?sort_by=price&sort_direction=asc');

                $response->assertSuccessful()
                    ->assertJsonPath('data.0.price', '50.00')
                    ->assertJsonPath('data.1.price', '75.00')
                    ->assertJsonPath('data.2.price', '100.00');
            });

            test('can sort by price descending', function () {
                $product1 = Product::factory()->create(['price' => 100.00]);
                $product2 = Product::factory()->create(['price' => 50.00]);
                $product3 = Product::factory()->create(['price' => 75.00]);

                $response = $this->getJson('/api/v1/products?sort_by=price&sort_direction=desc');

                $response->assertSuccessful()
                    ->assertJsonPath('data.0.price', '100.00')
                    ->assertJsonPath('data.1.price', '75.00')
                    ->assertJsonPath('data.2.price', '50.00');
            });

            test('can sort by created_at ascending', function () {
                $product1 = Product::factory()->create();
                sleep(1); // Ensure different timestamps
                $product2 = Product::factory()->create();
                sleep(1);
                $product3 = Product::factory()->create();

                $response = $this->getJson('/api/v1/products?sort_by=created_at&sort_direction=asc');

                $response->assertSuccessful();
                $data = $response->json('data');
                expect($data[0]['id'])->toBe($product1->id)
                    ->and($data[1]['id'])->toBe($product2->id)
                    ->and($data[2]['id'])->toBe($product3->id);
            });

            test('can sort by created_at descending (default)', function () {
                $product1 = Product::factory()->create();
                sleep(1); // Ensure different timestamps
                $product2 = Product::factory()->create();
                sleep(1);
                $product3 = Product::factory()->create();

                $response = $this->getJson('/api/v1/products?sort_by=created_at&sort_direction=desc');

                $response->assertSuccessful();
                $data = $response->json('data');
                expect($data[0]['id'])->toBe($product3->id)
                    ->and($data[1]['id'])->toBe($product2->id)
                    ->and($data[2]['id'])->toBe($product1->id);
            });

            test('defaults to created_at desc when no sort specified', function () {
                $product1 = Product::factory()->create();
                sleep(1);
                $product2 = Product::factory()->create();
                sleep(1);
                $product3 = Product::factory()->create();

                $response = $this->getJson('/api/v1/products');

                $response->assertSuccessful();
                $data = $response->json('data');
                expect($data[0]['id'])->toBe($product3->id)
                    ->and($data[1]['id'])->toBe($product2->id)
                    ->and($data[2]['id'])->toBe($product1->id);
            });

            test('rejects invalid sort_by value', function () {
                $response = $this->getJson('/api/v1/products?sort_by=invalid_field');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['sort_by']);
            });

            test('rejects invalid sort_direction value', function () {
                $response = $this->getJson('/api/v1/products?sort_by=title&sort_direction=invalid');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['sort_direction']);
            });
        });

        describe('pagination', function () {
            test('can paginate with per_page parameter', function () {
                Product::factory()->count(10)->create();

                $response = $this->getJson('/api/v1/products?per_page=5');

                $response->assertSuccessful()
                    ->assertJsonCount(5, 'data')
                    ->assertJsonPath('meta.per_page', 5)
                    ->assertJsonPath('meta.total', 10);
            });

            test('can navigate to different pages', function () {
                Product::factory()->count(10)->create();

                $page1 = $this->getJson('/api/v1/products?per_page=5&page=1');
                $page2 = $this->getJson('/api/v1/products?per_page=5&page=2');

                $page1->assertSuccessful()
                    ->assertJsonCount(5, 'data')
                    ->assertJsonPath('meta.current_page', 1);

                $page2->assertSuccessful()
                    ->assertJsonCount(5, 'data')
                    ->assertJsonPath('meta.current_page', 2);

                // Ensure different products on different pages
                $page1Ids = collect($page1->json('data'))->pluck('id')->toArray();
                $page2Ids = collect($page2->json('data'))->pluck('id')->toArray();
                expect($page1Ids)->not->toContain(...$page2Ids);
            });

            test('rejects per_page greater than 100', function () {
                $response = $this->getJson('/api/v1/products?per_page=101');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['per_page']);
            });

            test('rejects per_page less than 1', function () {
                $response = $this->getJson('/api/v1/products?per_page=0');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['per_page']);
            });

            test('rejects page less than 1', function () {
                $response = $this->getJson('/api/v1/products?page=0');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['page']);
            });

            test('rejects non-integer page', function () {
                $response = $this->getJson('/api/v1/products?page=invalid');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['page']);
            });

            test('rejects non-integer per_page', function () {
                $response = $this->getJson('/api/v1/products?per_page=invalid');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['per_page']);
            });
        });

        describe('combined filters', function () {
            test('can combine category_id and brand_slug filters', function () {
                $category = Category::factory()->create();
                $brand = Brand::factory()->create(['name' => 'Test Brand', 'slug' => 'test-brand']);

                $matchingProducts = Product::factory()->count(3)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                ]);

                Product::factory()->count(2)->create(['category_id' => $category->id]); // Different brand
                Product::factory()->count(2)->create(['brand_id' => $brand->id]); // Different category

                $response = $this->getJson("/api/v1/products?category_id={$category->id}&brand_slug=test-brand");

                $response->assertSuccessful()
                    ->assertJsonCount(3, 'data')
                    ->assertJsonPath('data.0.category.id', $category->id)
                    ->assertJsonPath('data.0.brand.slug', 'test-brand');
            });

            test('can combine filters with sorting', function () {
                $category = Category::factory()->create();
                $products = Product::factory()->count(3)->create([
                    'category_id' => $category->id,
                    'title' => 'Z Product',
                ]);
                $products[1]->update(['title' => 'A Product']);
                $products[2]->update(['title' => 'M Product']);

                $response = $this->getJson("/api/v1/products?category_id={$category->id}&sort_by=title&sort_direction=asc");

                $response->assertSuccessful()
                    ->assertJsonCount(3, 'data')
                    ->assertJsonPath('data.0.title', 'A Product')
                    ->assertJsonPath('data.1.title', 'M Product')
                    ->assertJsonPath('data.2.title', 'Z Product');
            });

            test('can combine all filters with pagination', function () {
                $category = Category::factory()->create();
                $brand = Brand::factory()->create(['name' => 'Filter Brand', 'slug' => 'filter-brand']);

                Product::factory()->count(10)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'price' => 100.00,
                ]);

                $response = $this->getJson("/api/v1/products?category_id={$category->id}&brand_slug=filter-brand&sort_by=price&sort_direction=asc&per_page=5&page=1");

                $response->assertSuccessful()
                    ->assertJsonCount(5, 'data')
                    ->assertJsonPath('meta.current_page', 1)
                    ->assertJsonPath('meta.per_page', 5)
                    ->assertJsonPath('meta.total', 10);
            });
        });
    });

});
