<?php

use App\Enums\ProductFieldType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductField;
use App\Models\ProductFieldValue;

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

        describe('brand_id filter', function () {
            test('can filter products by single brand_id', function () {
                $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two']);

                $productsBrand1 = Product::factory()->count(3)->create(['brand_id' => $brand1->id]);
                $productsBrand2 = Product::factory()->count(2)->create(['brand_id' => $brand2->id]);

                $response = $this->getJson("/api/v1/products?brand_id={$brand1->id}");

                $response->assertSuccessful()
                    ->assertJsonCount(3, 'data')
                    ->assertJsonPath('data.0.brand.id', $brand1->id)
                    ->assertJsonPath('data.1.brand.id', $brand1->id)
                    ->assertJsonPath('data.2.brand.id', $brand1->id);
            });

            test('can filter products by array of brand_ids', function () {
                $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two']);
                $brand3 = Brand::factory()->create(['name' => 'Brand Three']);

                $productsBrand1 = Product::factory()->count(2)->create(['brand_id' => $brand1->id]);
                $productsBrand2 = Product::factory()->count(3)->create(['brand_id' => $brand2->id]);
                $productsBrand3 = Product::factory()->count(1)->create(['brand_id' => $brand3->id]);

                $response = $this->getJson("/api/v1/products?brand_id[]={$brand1->id}&brand_id[]={$brand2->id}");

                $response->assertSuccessful()
                    ->assertJsonCount(5, 'data');

                $brandIds = collect($response->json('data'))->pluck('brand.id')->unique()->sort()->values();
                expect($brandIds)->toContain($brand1->id, $brand2->id)
                    ->not->toContain($brand3->id);
            });

            test('returns empty array when brand has no products', function () {
                $brand = Brand::factory()->create(['name' => 'Empty Brand']);
                Product::factory()->count(2)->create(); // Products with other brands

                $response = $this->getJson("/api/v1/products?brand_id={$brand->id}");

                $response->assertSuccessful()
                    ->assertJsonCount(0, 'data');
            });

            test('rejects invalid brand_id', function () {
                $response = $this->getJson('/api/v1/products?brand_id=99999');

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['brand_id']);
            });

            test('rejects invalid brand_id in array', function () {
                $brand = Brand::factory()->create();
                $response = $this->getJson("/api/v1/products?brand_id[]={$brand->id}&brand_id[]=99999");

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['brand_id']);
            });

            test('rejects empty brand_id array', function () {
                // Test validation directly with an empty array
                $request = \App\Http\Requests\IndexProductRequest::create('/api/v1/products', 'GET', ['brand_id' => []]);
                $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $request->rules());

                expect($validator->fails())->toBeTrue()
                    ->and($validator->errors()->has('brand_id'))->toBeTrue();
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

        describe('filters parameter', function () {
            describe('virtual filter -1 (Brand)', function () {
                test('can filter products by brand using filters parameter', function () {
                    $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                    $brand2 = Brand::factory()->create(['name' => 'Brand Two']);
                    $brand3 = Brand::factory()->create(['name' => 'Brand Three']);

                    $productsBrand1 = Product::factory()->count(2)->create(['brand_id' => $brand1->id]);
                    $productsBrand2 = Product::factory()->count(3)->create(['brand_id' => $brand2->id]);
                    $productsBrand3 = Product::factory()->count(1)->create(['brand_id' => $brand3->id]);

                    $filters = json_encode(['-1' => [$brand1->id, $brand2->id]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(5, 'data');

                    $brandIds = collect($response->json('data'))->pluck('brand.id')->unique()->sort()->values();
                    expect($brandIds)->toContain($brand1->id, $brand2->id)
                        ->not->toContain($brand3->id);
                });

                test('returns empty array when no products match brand filter', function () {
                    $brand = Brand::factory()->create();
                    Product::factory()->count(2)->create(); // Products with other brands

                    $filters = json_encode(['-1' => [$brand->id]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(0, 'data');
                });
            });

            describe('virtual filter -2 (Price)', function () {
                test('can filter products by price range using filters parameter', function () {
                    $product1 = Product::factory()->create(['price' => 50.00]);
                    $product2 = Product::factory()->create(['price' => 100.00]);
                    $product3 = Product::factory()->create(['price' => 150.00]);
                    $product4 = Product::factory()->create(['price' => 200.00]);

                    $filters = json_encode(['-2' => ['min' => 75.00, 'max' => 175.00]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(2, 'data');

                    $prices = collect($response->json('data'))->pluck('price')->sort()->values();
                    expect($prices)->toContain('100.00', '150.00')
                        ->not->toContain('50.00', '200.00');
                });

                test('can filter products by price min only', function () {
                    $product1 = Product::factory()->create(['price' => 50.00]);
                    $product2 = Product::factory()->create(['price' => 100.00]);
                    $product3 = Product::factory()->create(['price' => 150.00]);

                    $filters = json_encode(['-2' => ['min' => 100.00]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(2, 'data');

                    $prices = collect($response->json('data'))->pluck('price')->sort()->values();
                    expect($prices)->toContain('100.00', '150.00')
                        ->not->toContain('50.00');
                });

                test('can filter products by price max only', function () {
                    $product1 = Product::factory()->create(['price' => 50.00]);
                    $product2 = Product::factory()->create(['price' => 100.00]);
                    $product3 = Product::factory()->create(['price' => 150.00]);

                    $filters = json_encode(['-2' => ['max' => 100.00]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(2, 'data');

                    $prices = collect($response->json('data'))->pluck('price')->sort()->values();
                    expect($prices)->toContain('50.00', '100.00')
                        ->not->toContain('150.00');
                });
            });

            describe('product field textfield filter', function () {
                test('can filter products by textfield product field', function () {
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::String,
                        'name' => 'Color',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();
                    $product3 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Red',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Blue',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Red',
                    ]);

                    $filters = json_encode([$productField->id => 'Red']);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(2, 'data');

                    $productIds = collect($response->json('data'))->pluck('id')->toArray();
                    expect($productIds)->toContain($product1->id, $product3->id)
                        ->not->toContain($product2->id);
                });

                test('textfield filter uses LIKE for partial matching', function () {
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::String,
                        'name' => 'Model',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'iPhone 15',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Samsung Galaxy',
                    ]);

                    $filters = json_encode([$productField->id => 'iPhone']);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(1, 'data')
                        ->assertJsonPath('data.0.id', $product1->id);
                });
            });

            describe('product field range filter', function () {
                test('can filter products by integer range product field', function () {
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::Integer,
                        'name' => 'RAM',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();
                    $product3 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_int' => 8,
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_int' => 16,
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $productField->id,
                        'value_int' => 32,
                    ]);

                    $filters = json_encode([$productField->id => ['min' => 10, 'max' => 20]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(1, 'data')
                        ->assertJsonPath('data.0.id', $product2->id);
                });

                test('can filter products by float range product field', function () {
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::Float,
                        'name' => 'Weight',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();
                    $product3 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_float' => 1.5,
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_float' => 2.0,
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $productField->id,
                        'value_float' => 3.5,
                    ]);

                    $filters = json_encode([$productField->id => ['min' => 1.8, 'max' => 2.5]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(1, 'data')
                        ->assertJsonPath('data.0.id', $product2->id);
                });
            });

            describe('product field checkboxes/select filter', function () {
                test('can filter products by array of values (checkboxes/select)', function () {
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::Enum,
                        'name' => 'Operating System',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();
                    $product3 = Product::factory()->create();
                    $product4 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Windows',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'macOS',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Linux',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product4->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Windows',
                    ]);

                    $filters = json_encode([$productField->id => ['Windows', 'macOS']]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(3, 'data');

                    $productIds = collect($response->json('data'))->pluck('id')->toArray();
                    expect($productIds)->toContain($product1->id, $product2->id, $product4->id)
                        ->not->toContain($product3->id);
                });

                test('can filter products by integer array values', function () {
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::Integer,
                        'name' => 'Storage',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();
                    $product3 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_int' => 128,
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_int' => 256,
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $productField->id,
                        'value_int' => 512,
                    ]);

                    $filters = json_encode([$productField->id => [128, 512]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(2, 'data');

                    $productIds = collect($response->json('data'))->pluck('id')->toArray();
                    expect($productIds)->toContain($product1->id, $product3->id)
                        ->not->toContain($product2->id);
                });
            });

            describe('combined filters', function () {
                test('can combine virtual filters with product field filters', function () {
                    $category = Category::factory()->create();
                    $brand = Brand::factory()->create();
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::String,
                        'name' => 'Color',
                    ]);

                    $product1 = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 100.00]);
                    $product2 = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 200.00]);
                    $product3 = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 150.00]);

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Red',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Blue',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Red',
                    ]);

                    $filters = json_encode([
                        '-1' => [$brand->id],
                        '-2' => ['min' => 120.00, 'max' => 180.00],
                        $productField->id => 'Red',
                    ]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(1, 'data')
                        ->assertJsonPath('data.0.id', $product3->id);
                });

                test('can combine multiple product field filters', function () {
                    $field1 = ProductField::factory()->create([
                        'type' => ProductFieldType::String,
                        'name' => 'Color',
                    ]);
                    $field2 = ProductField::factory()->create([
                        'type' => ProductFieldType::Integer,
                        'name' => 'RAM',
                    ]);

                    $product1 = Product::factory()->create();
                    $product2 = Product::factory()->create();
                    $product3 = Product::factory()->create();

                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $field1->id,
                        'value_string' => 'Red',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product1->id,
                        'product_field_id' => $field2->id,
                        'value_int' => 16,
                    ]);

                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $field1->id,
                        'value_string' => 'Red',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product2->id,
                        'product_field_id' => $field2->id,
                        'value_int' => 8,
                    ]);

                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $field1->id,
                        'value_string' => 'Blue',
                    ]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product3->id,
                        'product_field_id' => $field2->id,
                        'value_int' => 16,
                    ]);

                    $filters = json_encode([
                        $field1->id => 'Red',
                        $field2->id => 16,
                    ]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful()
                        ->assertJsonCount(1, 'data')
                        ->assertJsonPath('data.0.id', $product1->id);
                });
            });

            describe('filters validation', function () {
                test('rejects invalid filter key (non-numeric and not -1 or -2)', function () {
                    $filters = json_encode(['invalid_key' => 'value']);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertUnprocessable()
                        ->assertJsonValidationErrors(['filters']);
                });

                test('rejects range filter with min greater than max', function () {
                    $filters = json_encode(['-2' => ['min' => 100, 'max' => 50]]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertUnprocessable()
                        ->assertJsonValidationErrors(['filters']);
                });

                test('rejects empty array for checkboxes/select filter', function () {
                    $productField = ProductField::factory()->create();
                    $filters = json_encode([$productField->id => []]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertUnprocessable()
                        ->assertJsonValidationErrors(['filters']);
                });

                test('accepts valid filters structure', function () {
                    $brand = Brand::factory()->create();
                    $productField = ProductField::factory()->create([
                        'type' => ProductFieldType::String,
                    ]);

                    $product = Product::factory()->create(['brand_id' => $brand->id]);
                    ProductFieldValue::factory()->create([
                        'product_id' => $product->id,
                        'product_field_id' => $productField->id,
                        'value_string' => 'Test',
                    ]);

                    $filters = json_encode([
                        '-1' => [$brand->id],
                        '-2' => ['min' => 10, 'max' => 100],
                        $productField->id => 'Test',
                    ]);
                    $response = $this->getJson("/api/v1/products?filters={$filters}");

                    $response->assertSuccessful();
                });
            });
        });

        describe('filter data in response', function () {
            test('returns filters when category_id is present and page=1', function () {
                $productClass = \App\Models\ProductClass::factory()->create();
                $category = Category::factory()->create(['product_class_id' => $productClass->id]);
                $brand = Brand::factory()->create();
                $product = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'price' => 100.00,
                ]);

                $response = $this->getJson("/api/v1/products?category_id={$category->id}&page=1");

                $response->assertSuccessful()
                    ->assertJsonStructure([
                        'data',
                        'filters' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'type',
                                'filterType',
                                'filterWeight',
                            ],
                        ],
                    ]);

                // Check that Brand and Price filters are present
                $filters = $response->json('filters');
                $filterIds = collect($filters)->pluck('id')->toArray();
                expect($filterIds)->toContain(-1, -2); // Brand and Price filters
            });

            test('does not return filters when page > 1', function () {
                $productClass = \App\Models\ProductClass::factory()->create();
                $category = Category::factory()->create(['product_class_id' => $productClass->id]);
                Product::factory()->count(30)->create(['category_id' => $category->id]);

                $response = $this->getJson("/api/v1/products?category_id={$category->id}&page=2&per_page=10");

                $response->assertSuccessful();
                expect($response->json('filters'))->toBeNull();
            });

            test('does not return filters when category_id is not present', function () {
                $brand = Brand::factory()->create();
                Product::factory()->create(['brand_id' => $brand->id]);

                $response = $this->getJson("/api/v1/products?brand_id={$brand->id}");

                $response->assertSuccessful();
                expect($response->json('filters'))->toBeNull();
            });

            test('filters use facet isolation - brand filter shows all brands when price is filtered', function () {
                $productClass = \App\Models\ProductClass::factory()->create();
                $category = Category::factory()->create(['product_class_id' => $productClass->id]);
                $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two']);

                // Products with different brands and prices
                Product::factory()->count(3)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand1->id,
                    'price' => 50.00,
                ]);
                Product::factory()->count(2)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand2->id,
                    'price' => 150.00,
                ]);

                // Request with price filter applied (only products with price <= 100)
                $response = $this->getJson("/api/v1/products?category_id={$category->id}&price_max=100");

                $response->assertSuccessful();

                $filters = $response->json('filters');
                $brandFilter = collect($filters)->firstWhere('id', -1);
                $priceFilter = collect($filters)->firstWhere('id', -2);

                // Brand filter should show ALL brands (facet isolation - brand filter excluded from query)
                // But only brands that have products in the price range
                expect($brandFilter)->not->toBeNull();
                $brandOptions = collect($brandFilter['filterOptions']);
                // Brand One should be present (has products with price <= 100)
                expect($brandOptions->where('value', $brand1->id)->first())->not->toBeNull();
                expect($brandOptions->where('value', $brand1->id)->first()['count'])->toBe(3);
                // Brand Two should NOT be present (no products with price <= 100)
                expect($brandOptions->where('value', $brand2->id)->first())->toBeNull();

                // Price filter should show full range (facet isolation - price filter excluded)
                // But only for products that match other filters (none in this case, so all products)
                expect($priceFilter)->not->toBeNull();
                expect((float) $priceFilter['min'])->toBe(50.0);
                expect((float) $priceFilter['max'])->toBe(150.0); // Full range, not filtered range
            });

            test('filters use facet isolation - price filter shows full range when brand is filtered', function () {
                $productClass = \App\Models\ProductClass::factory()->create();
                $category = Category::factory()->create(['product_class_id' => $productClass->id]);
                $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two']);

                // Products with different brands and prices
                Product::factory()->count(3)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand1->id,
                    'price' => 50.00,
                ]);
                Product::factory()->count(2)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand2->id,
                    'price' => 150.00,
                ]);

                // Request with brand filter applied (only Brand One)
                $response = $this->getJson("/api/v1/products?category_id={$category->id}&brand_id={$brand1->id}");

                $response->assertSuccessful();

                $filters = $response->json('filters');
                $brandFilter = collect($filters)->firstWhere('id', -1);
                $priceFilter = collect($filters)->firstWhere('id', -2);

                // Brand filter should show ALL brands (facet isolation - brand filter excluded)
                expect($brandFilter)->not->toBeNull();
                $brandOptions = collect($brandFilter['filterOptions']);
                // Both brands should be present
                expect($brandOptions->where('value', $brand1->id)->first())->not->toBeNull();
                expect($brandOptions->where('value', $brand2->id)->first())->not->toBeNull();

                // Price filter should show full range (facet isolation - price filter excluded)
                // But only for products that match other filters (Brand One in this case)
                expect($priceFilter)->not->toBeNull();
                expect((float) $priceFilter['min'])->toBe(50.0);
                expect((float) $priceFilter['max'])->toBe(150.0); // Full range, not just Brand One's range
            });

            test('filters use facet isolation - product field filter shows all values when other filters applied', function () {
                $productClass = \App\Models\ProductClass::factory()->create();
                $category = Category::factory()->create(['product_class_id' => $productClass->id]);
                $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two']);
                $productField = ProductField::factory()->create([
                    'type' => ProductFieldType::String,
                ]);

                // Attach field to product class as filterable
                $productClass->filterableFields()->attach($productField->id, [
                    'is_filter' => true,
                    'filter_type' => \App\Enums\FilterType::Checkboxes->value,
                    'filter_weight' => 1,
                ]);

                // Products with different brands and field values
                $product1 = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand1->id,
                    'price' => 50.00,
                ]);
                $product2 = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand1->id,
                    'price' => 75.00,
                ]);
                $product3 = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand2->id,
                    'price' => 100.00,
                ]);

                // Create field values
                ProductFieldValue::factory()->create([
                    'product_id' => $product1->id,
                    'product_field_id' => $productField->id,
                    'value_string' => 'Value1',
                ]);
                ProductFieldValue::factory()->create([
                    'product_id' => $product2->id,
                    'product_field_id' => $productField->id,
                    'value_string' => 'Value2',
                ]);
                ProductFieldValue::factory()->create([
                    'product_id' => $product3->id,
                    'product_field_id' => $productField->id,
                    'value_string' => 'Value1',
                ]);

                // Request with brand filter applied (only Brand One)
                $response = $this->getJson("/api/v1/products?category_id={$category->id}&brand_id={$brand1->id}");

                $response->assertSuccessful();

                $filters = $response->json('filters');
                $fieldFilter = collect($filters)->firstWhere('id', $productField->id);

                // Field filter should show ALL values (facet isolation - field filter excluded)
                // But only values that exist in products matching other filters (Brand One)
                expect($fieldFilter)->not->toBeNull();
                $fieldOptions = collect($fieldFilter['filterOptions']);
                // Value1 should be present (product1 has it)
                expect($fieldOptions->where('value', 'Value1')->first())->not->toBeNull();
                expect($fieldOptions->where('value', 'Value1')->first()['count'])->toBe(1);
                // Value2 should be present (product2 has it)
                expect($fieldOptions->where('value', 'Value2')->first())->not->toBeNull();
                expect($fieldOptions->where('value', 'Value2')->first()['count'])->toBe(1);
            });

            test('filters use facet isolation - brand and price filters show all values when product field is filtered', function () {
                $productClass = \App\Models\ProductClass::factory()->create();
                $category = Category::factory()->create(['product_class_id' => $productClass->id]);
                $brand1 = Brand::factory()->create(['name' => 'Brand One']);
                $brand2 = Brand::factory()->create(['name' => 'Brand Two']);
                $productField = ProductField::factory()->create([
                    'type' => ProductFieldType::String,
                ]);

                // Attach field to product class as filterable
                $productClass->filterableFields()->attach($productField->id, [
                    'is_filter' => true,
                    'filter_type' => \App\Enums\FilterType::Checkboxes->value,
                    'filter_weight' => 1,
                ]);

                // Products with different brands, prices, and field values
                $product1 = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand1->id,
                    'price' => 50.00,
                ]);
                $product2 = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand2->id,
                    'price' => 150.00,
                ]);
                $product3 = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand1->id,
                    'price' => 200.00,
                ]);

                // Create field values - only product1 and product2 have 'Value1'
                ProductFieldValue::factory()->create([
                    'product_id' => $product1->id,
                    'product_field_id' => $productField->id,
                    'value_string' => 'Value1',
                ]);
                ProductFieldValue::factory()->create([
                    'product_id' => $product2->id,
                    'product_field_id' => $productField->id,
                    'value_string' => 'Value1',
                ]);
                ProductFieldValue::factory()->create([
                    'product_id' => $product3->id,
                    'product_field_id' => $productField->id,
                    'value_string' => 'Value2',
                ]);

                // Request with field filter applied (only Value1)
                // Use JSON encoded filters in query string
                $filters = json_encode([$productField->id => ['Value1']]);
                $response = $this->getJson("/api/v1/products?category_id={$category->id}&filters={$filters}");

                $response->assertSuccessful();

                $responseFilters = $response->json('filters');
                $brandFilter = collect($responseFilters)->firstWhere('id', -1);
                $priceFilter = collect($responseFilters)->firstWhere('id', -2);

                // Brand filter should show ALL brands (facet isolation - brand filter excluded)
                // But only brands that have products matching the field filter
                expect($brandFilter)->not->toBeNull();
                $brandOptions = collect($brandFilter['filterOptions']);
                // Both brands should be present (both have products with Value1)
                expect($brandOptions->where('value', $brand1->id)->first())->not->toBeNull();
                expect($brandOptions->where('value', $brand2->id)->first())->not->toBeNull();

                // Price filter should show full range (facet isolation - price filter excluded)
                // But only for products matching other filters (Value1)
                expect($priceFilter)->not->toBeNull();
                expect((float) $priceFilter['min'])->toBe(50.0);
                expect((float) $priceFilter['max'])->toBe(150.0); // Full range of products with Value1
            });
        });
    });

});
