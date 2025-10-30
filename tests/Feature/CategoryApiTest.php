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

    describe('POST /api/v1/categories (store)', function () {
        test('can create a new category with valid data', function () {
            $categoryData = [
                'name' => 'Test Category',
                'description' => 'A test category description',
                'is_active' => true,
            ];

            $response = $this->postJson('/api/v1/categories', $categoryData);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Category created successfully',
                    'data' => [
                        'name' => 'Test Category',
                        'slug' => 'test-category',
                        'description' => 'A test category description',
                        'isActive' => true,
                        'parentId' => null,
                    ],
                ]);

            $this->assertDatabaseHas('categories', [
                'name' => 'Test Category',
                'slug' => 'test-category',
                'description' => 'A test category description',
                'is_active' => true,
                'parent_id' => null,
            ]);
        });

        test('validates required fields', function () {
            $response = $this->postJson('/api/v1/categories', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        test('validates slug uniqueness', function () {
            Category::factory()->withName('Existing Category')->create(['slug' => 'existing-slug']);

            $response = $this->postJson('/api/v1/categories', [
                'name' => 'New Category',
                'slug' => 'existing-slug',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
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

    describe('PUT /api/v1/categories/{id} (update)', function () {
        test('can update a category with valid data', function () {
            $category = Category::factory()->withName('Original Category')->create();

            $updateData = [
                'name' => 'Updated Category',
                'description' => 'Updated description',
                'is_active' => false,
            ];

            $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Category updated successfully',
                    'data' => [
                        'id' => $category->id,
                        'name' => 'Updated Category',
                        'description' => 'Updated description',
                        'isActive' => false,
                    ],
                ]);

            $this->assertDatabaseHas('categories', [
                'id' => $category->id,
                'name' => 'Updated Category',
                'description' => 'Updated description',
                'is_active' => false,
            ]);
        });

        test('validates slug uniqueness excluding current category', function () {
            $category1 = Category::factory()->withName('Category One')->create(['slug' => 'category-one']);
            $category2 = Category::factory()->withName('Category Two')->create(['slug' => 'category-two']);

            // Update category2 to use category1's slug should fail
            $response = $this->putJson("/api/v1/categories/{$category2->id}", [
                'slug' => 'category-one',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
        });
    });

    describe('DELETE /api/v1/categories/{id} (destroy)', function () {
        test('can delete an empty category', function () {
            // Create a category and ensure it has no children or products
            $category = Category::factory()->root()->withName('Empty Category')->create();

            $response = $this->deleteJson("/api/v1/categories/{$category->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Category deleted successfully',
                ]);

            $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        });

        test('prevents deletion if category has children', function () {
            $parentCategory = Category::factory()->root()->withName('Parent Category')->create();
            $childCategory = Category::factory()->withName('Child Category')->create(['parent_id' => $parentCategory->id]);

            $response = $this->deleteJson("/api/v1/categories/{$parentCategory->id}");

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot delete category with subcategories. Please delete subcategories first.',
                ]);

            $this->assertDatabaseHas('categories', ['id' => $parentCategory->id]);
        });

        test('prevents deletion if category has products', function () {
            $category = Category::factory()->root()->withName('Category With Products')->create();
            Product::factory()->create(['category_id' => $category->id]);

            // Verify it has products
            expect($category->products()->exists())->toBeTrue();

            $response = $this->deleteJson("/api/v1/categories/{$category->id}");

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot delete category with products. Please move or delete products first.',
                ]);

            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        });
    });
});

describe('Nested Set Functionality', function () {

    describe('POST /api/v1/categories/{id}/move', function () {
        test('can move category to new parent', function () {
            $parent1 = Category::factory()->root()->withName('Parent 1')->create();
            $parent2 = Category::factory()->root()->withName('Parent 2')->create();
            $child = Category::factory()->withName('Child Category')->create(['parent_id' => $parent1->id]);

            $response = $this->postJson("/api/v1/categories/{$child->id}/move", [
                'parent_id' => $parent2->id,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Category moved successfully',
                    'data' => [
                        'id' => $child->id,
                        'parentId' => $parent2->id,
                    ],
                ]);

            $this->assertDatabaseHas('categories', [
                'id' => $child->id,
                'parent_id' => $parent2->id,
            ]);
        });

        test('prevents moving category to itself', function () {
            $category = Category::factory()->root()->withName('Test Category')->create();

            $response = $this->postJson("/api/v1/categories/{$category->id}/move", [
                'parent_id' => $category->id,
            ]);

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot move category to itself or its descendant.',
                ]);
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
