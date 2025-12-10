<?php

use App\Models\Collection;
use App\Models\Product;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');
});

describe('Collection API Operations', function () {

    describe('GET /api/v1/collections/{collection_slug}/products', function () {
        test('can retrieve products by collection slug', function () {
            $collection = Collection::factory()->create(['name' => 'New Products', 'slug' => 'new']);
            $products = Product::factory()->count(5)->create();
            $collection->products()->attach($products->pluck('id'));

            $response = $this->getJson('/api/v1/collections/new/products');

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'sku',
                            'title',
                            'description',
                            'price',
                            'imageUrl',
                            'categoryId',
                            'productClassId',
                            'brandId',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ])
                ->assertJsonCount(5, 'data');
        });

        test('returns products with relationships loaded', function () {
            $collection = Collection::factory()->create(['name' => 'Featured', 'slug' => 'featured']);
            $product = Product::factory()->create();
            $collection->products()->attach($product->id);

            $response = $this->getJson('/api/v1/collections/featured/products');

            $response->assertSuccessful();
            $responseData = $response->json();
            expect($responseData['data'])->toHaveCount(1);
            expect($responseData['data'][0])->toHaveKey('id', $product->id);
        });

        test('respects limit parameter', function () {
            $collection = Collection::factory()->create(['name' => 'Limited', 'slug' => 'limited']);
            $products = Product::factory()->count(10)->create();
            $collection->products()->attach($products->pluck('id'));

            $response = $this->getJson('/api/v1/collections/limited/products?limit=3');

            $response->assertSuccessful()
                ->assertJsonCount(3, 'data');
        });

        test('returns all products when limit is not provided', function () {
            $collection = Collection::factory()->create(['name' => 'All Products', 'slug' => 'all']);
            $products = Product::factory()->count(15)->create();
            $collection->products()->attach($products->pluck('id'));

            $response = $this->getJson('/api/v1/collections/all/products');

            $response->assertSuccessful()
                ->assertJsonCount(15, 'data');
        });

        test('returns empty array when collection has no products', function () {
            $collection = Collection::factory()->create(['name' => 'Empty Collection', 'slug' => 'empty']);

            $response = $this->getJson('/api/v1/collections/empty/products');

            $response->assertSuccessful()
                ->assertJsonCount(0, 'data');
        });

        test('returns 404 for non-existent collection slug', function () {
            $response = $this->getJson('/api/v1/collections/non-existent/products');

            $response->assertNotFound();
        });

        test('validates limit parameter minimum value', function () {
            $collection = Collection::factory()->create(['name' => 'Test', 'slug' => 'test']);

            $response = $this->getJson('/api/v1/collections/test/products?limit=0');

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['limit']);
        });

        test('validates limit parameter maximum value', function () {
            $collection = Collection::factory()->create(['name' => 'Test', 'slug' => 'test']);

            $response = $this->getJson('/api/v1/collections/test/products?limit=101');

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['limit']);
        });

        test('validates limit parameter is integer', function () {
            $collection = Collection::factory()->create(['name' => 'Test', 'slug' => 'test']);

            $response = $this->getJson('/api/v1/collections/test/products?limit=invalid');

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['limit']);
        });

        test('works with auto-generated slug', function () {
            $collection = Collection::factory()->create(['name' => 'Test Collection Name']);
            $product = Product::factory()->create();
            $collection->products()->attach($product->id);

            $response = $this->getJson('/api/v1/collections/test-collection-name/products');

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data');
        });

        test('only returns products from the specified collection', function () {
            $collection1 = Collection::factory()->create(['name' => 'Collection 1', 'slug' => 'collection-1']);
            $collection2 = Collection::factory()->create(['name' => 'Collection 2', 'slug' => 'collection-2']);
            $products1 = Product::factory()->count(3)->create();
            $products2 = Product::factory()->count(2)->create();
            $collection1->products()->attach($products1->pluck('id'));
            $collection2->products()->attach($products2->pluck('id'));

            $response = $this->getJson('/api/v1/collections/collection-1/products');

            $response->assertSuccessful()
                ->assertJsonCount(3, 'data');
        });
    });
});
