<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');

    // Ensure required roles exist for tests
    Role::query()->firstOrCreate(['name' => Role::ADMIN]);
    Role::query()->firstOrCreate(['name' => Role::CUSTOMER]);

    // Create admin user
    $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');
    $this->admin = User::factory()->create([
        'role_id' => $adminRoleId,
    ]);
});

describe('Category Admin CRUD Operations', function () {

    describe('POST /api/v1/admin/categories (store)', function () {
        test('admin can create a new category with valid data', function () {
            $categoryData = [
                'name' => 'Test Category',
                'description' => 'A test category description',
                'is_active' => true,
            ];

            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', $categoryData);

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
            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        test('validates slug uniqueness', function () {
            Category::factory()->withName('Existing Category')->create(['slug' => 'existing-slug']);

            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', [
                'name' => 'New Category',
                'slug' => 'existing-slug',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
        });

        test('requires admin authentication', function () {
            $response = $this->postJson('/api/v1/admin/categories', [
                'name' => 'Test Category',
            ]);

            $response->assertUnauthorized();
        });

        test('customer cannot create categories', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);

            $response = $this->actingAs($customer)->postJson('/api/v1/admin/categories', [
                'name' => 'Test Category',
            ]);

            $response->assertForbidden();
        });
    });

    describe('PUT /api/v1/admin/categories/{id} (update)', function () {
        test('admin can update a category with valid data', function () {
            $category = Category::factory()->withName('Original Category')->create();

            $updateData = [
                'name' => 'Updated Category',
                'description' => 'Updated description',
                'is_active' => false,
            ];

            $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/categories/{$category->id}", $updateData);

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
            $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/categories/{$category2->id}", [
                'slug' => 'category-one',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
        });

        test('requires admin authentication', function () {
            $category = Category::factory()->create();

            $response = $this->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

            $response->assertUnauthorized();
        });

        test('customer cannot update categories', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);
            $category = Category::factory()->create();

            $response = $this->actingAs($customer)->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

            $response->assertForbidden();
        });
    });

    describe('DELETE /api/v1/admin/categories/{id} (destroy)', function () {
        test('admin can delete an empty category', function () {
            // Create a category and ensure it has no children or products
            $category = Category::factory()->root()->withName('Empty Category')->create();

            $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/categories/{$category->id}");

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

            $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/categories/{$parentCategory->id}");

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

            $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/categories/{$category->id}");

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot delete category with products. Please move or delete products first.',
                ]);

            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        });

        test('requires admin authentication', function () {
            $category = Category::factory()->create();

            $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

            $response->assertUnauthorized();
        });

        test('customer cannot delete categories', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);
            $category = Category::factory()->create();

            $response = $this->actingAs($customer)->deleteJson("/api/v1/admin/categories/{$category->id}");

            $response->assertForbidden();
        });
    });

    describe('POST /api/v1/admin/categories/{id}/move', function () {
        test('admin can move category to new parent', function () {
            $parent1 = Category::factory()->root()->withName('Parent 1')->create();
            $parent2 = Category::factory()->root()->withName('Parent 2')->create();
            $child = Category::factory()->withName('Child Category')->create(['parent_id' => $parent1->id]);

            $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/categories/{$child->id}/move", [
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

            $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/categories/{$category->id}/move", [
                'parent_id' => $category->id,
            ]);

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot move category to itself or its descendant.',
                ]);
        });

        test('requires admin authentication', function () {
            $category = Category::factory()->create();

            $response = $this->postJson("/api/v1/admin/categories/{$category->id}/move", [
                'parent_id' => null,
            ]);

            $response->assertUnauthorized();
        });

        test('customer cannot move categories', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);
            $category = Category::factory()->create();

            $response = $this->actingAs($customer)->postJson("/api/v1/admin/categories/{$category->id}/move", [
                'parent_id' => null,
            ]);

            $response->assertForbidden();
        });
    });
});
