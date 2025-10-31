<?php

use App\Models\Product;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');
});

describe('Product API CRUD Operations', function () {

    describe('GET /api/v1/products (index)', function () {
        test('can retrieve all products', function () {
            // Create some test products
            $products = Product::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/products');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'sku',
                            'title',
                            'price',
                            'imageUrl',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'links',
                    'meta',
                ])
                ->assertJsonCount(3, 'data');
        });

        test('returns empty array when no products exist', function () {
            $response = $this->getJson('/api/v1/products');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'links',
                    'meta',
                ])
                ->assertJsonCount(0, 'data');
        });
    });

    describe('GET /api/v1/products/{id} (show)', function () {
        test('can retrieve a specific product', function () {
            $product = Product::factory()->create([
                'title' => 'Specific Product',
                'price' => 149.99,
                'imageUrl' => 'https://example.com/specific.jpg',
            ]);

            $response = $this->getJson("/api/v1/products/{$product->id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'sku',
                        'title',
                        'price',
                        'imageUrl',
                        'created_at',
                        'updated_at',
                    ],
                ])
                ->assertJson([
                    'data' => [
                        'id' => $product->id,
                        'title' => 'Specific Product',
                        'price' => 149.99,
                        'imageUrl' => 'https://example.com/specific.jpg',
                    ],
                ]);
        });

        test('returns 404 for non-existent product', function () {
            $response = $this->getJson('/api/v1/products/00000000-0000-0000-0000-000000000000');

            $response->assertStatus(404);
        });
    });

});
