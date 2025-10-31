<?php

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');
});

describe('Category API CRUD Operations', function () {

    describe('GET /api/v1/categories (index)', function () {
        test('can retrieve all active categories as tree structure', function () {
            // Create test categories with hierarchy
            $parentCategory = Category::factory()->root()->withName('Electronics')->create();
            $childCategory = Category::factory()->withName('Smartphones')->create(['parent_id' => $parentCategory->id]);
            $inactiveCategory = Category::factory()->inactive()->withName('Inactive Category')->create();

            $response = $this->getJson('/api/v1/categories');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'isActive',
                            'parentId',
                            'isLeaf',
                            'children',
                            'createdAt',
                            'updatedAt',
                        ],
                    ],
                ]);

            // Should only return active categories
            $responseData = $response->json();
            expect($responseData['data'])->toHaveCount(1); // Only Electronics (parent)
            expect($responseData['data'][0]['name'])->toBe('Electronics');
            expect($responseData['data'][0]['children'])->toHaveCount(1);
            expect($responseData['data'][0]['children'][0]['name'])->toBe('Smartphones');
        });

        test('returns empty array when no categories exist', function () {
            $response = $this->getJson('/api/v1/categories');

            $response->assertStatus(200)
                ->assertJson(['data' => []]);
        });
    });

    describe('GET /api/v1/categories/{id} (show)', function () {
        test('can retrieve a specific category with ancestors loaded', function () {
            $parentCategory = Category::factory()->root()->withName('Parent Category')->create();
            $childCategory = Category::factory()->withName('Child Category')->create(['parent_id' => $parentCategory->id]);

            $response = $this->getJson("/api/v1/categories/{$childCategory->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $childCategory->id,
                        'name' => 'Child Category',
                        'parentId' => $parentCategory->id,
                    ],
                ]);

            // Check that ancestors are loaded
            $responseData = $response->json();
            expect($responseData['data']['ancestors'])->toHaveCount(1);
            expect($responseData['data']['ancestors'][0]['name'])->toBe('Parent Category');
        });

        test('returns 404 for non-existent category', function () {
            $response = $this->getJson('/api/v1/categories/00000000-0000-0000-0000-000000000000');

            $response->assertStatus(404);
        });
    });

    describe('GET /api/v1/categories/{id}/products', function () {
        test('returns products from category and descendants', function () {
            $parentCategory = Category::factory()->root()->withName('Electronics')->create();
            $childCategory = Category::factory()->withName('Smartphones')->create(['parent_id' => $parentCategory->id]);

            $parentProduct = Product::factory()->create(['category_id' => $parentCategory->id]);
            $childProduct = Product::factory()->create(['category_id' => $childCategory->id]);

            $response = $this->getJson("/api/v1/categories/{$parentCategory->id}/products");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

            $responseData = $response->json();
            expect($responseData['data'])->toHaveCount(2);

            // Check that both products are returned
            $productIds = collect($responseData['data'])->pluck('id')->toArray();
            expect($productIds)->toContain($parentProduct->id);
            expect($productIds)->toContain($childProduct->id);
        });
    });
});

describe('Model Scopes and Relationships', function () {

    test('active scope filters active categories', function () {
        Category::factory()->withName('Active Category')->create(['is_active' => true]);
        Category::factory()->inactive()->withName('Inactive Category')->create();

        $activeCategories = Category::active()->get();

        expect($activeCategories)->toHaveCount(1);
        expect($activeCategories->first()->name)->toBe('Active Category');
    });

    test('products relationship works correctly', function () {
        $category = Category::factory()->withName('Test Category')->create();
        $product1 = Product::factory()->create(['category_id' => $category->id]);
        $product2 = Product::factory()->create(['category_id' => $category->id]);

        $categoryProducts = $category->products;

        expect($categoryProducts)->toHaveCount(2);
        expect($categoryProducts->pluck('id')->toArray())->toContain($product1->id);
        expect($categoryProducts->pluck('id')->toArray())->toContain($product2->id);
    });
});

describe('Model Events', function () {

    test('slug is auto-generated on create', function () {
        $category = Category::factory()->withName('Auto Generated Slug')->create();

        expect($category->slug)->toBe('auto-generated-slug');
    });
});
