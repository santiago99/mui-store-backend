<?php

use App\Models\Brand;
use App\Models\Product;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');
});

describe('Brand API Operations', function () {

    describe('GET /api/v1/brands/{brand_slug} (show)', function () {
        test('can retrieve a specific brand by slug', function () {
            $brand = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple']);

            $response = $this->getJson('/api/v1/brands/apple');

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'slug',
                        'productsCount',
                        'createdAt',
                        'updatedAt',
                    ],
                ])
                ->assertJson([
                    'data' => [
                        'id' => $brand->id,
                        'name' => 'Apple',
                        'slug' => 'apple',
                    ],
                ]);
        });

        test('returns 404 for non-existent brand slug', function () {
            $response = $this->getJson('/api/v1/brands/non-existent-brand');

            $response->assertNotFound();
        });

        test('returns brand with products count when products exist', function () {
            $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
            Product::factory()->count(3)->create(['brand_id' => $brand->id]);

            $response = $this->getJson('/api/v1/brands/samsung');

            $response->assertSuccessful();
            $responseData = $response->json();
            expect($responseData['data']['productsCount'])->toBe(3);
        });

        test('works with auto-generated slug', function () {
            $brand = Brand::factory()->create(['name' => 'Test Brand Name']);

            $response = $this->getJson('/api/v1/brands/test-brand-name');

            $response->assertSuccessful()
                ->assertJson([
                    'data' => [
                        'name' => 'Test Brand Name',
                        'slug' => 'test-brand-name',
                    ],
                ]);
        });
    });
});
