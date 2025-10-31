<?php

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

describe('Product Admin CRUD Operations', function () {

    describe('POST /api/v1/admin/products (store)', function () {
        test('admin can create a new product with valid data', function () {
            $productData = [
                'title' => 'Test Product',
                'price' => 99.99,
                'imageUrl' => 'https://example.com/image.jpg',
            ];

            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', $productData);

            $response->assertStatus(201)
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
                        'title' => 'Test Product',
                        'price' => 99.99,
                        'imageUrl' => 'https://example.com/image.jpg',
                    ],
                ]);

            $this->assertDatabaseHas('products', $productData);
        });

        test('validates required fields', function () {
            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'price', 'imageUrl']);
        });

        test('validates title is string and max length', function () {
            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
                'title' => str_repeat('a', 256), // Too long
                'price' => 99.99,
                'imageUrl' => 'https://example.com/image.jpg',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title']);
        });

        test('validates price is numeric and positive', function () {
            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'price' => -10, // Negative price
                'imageUrl' => 'https://example.com/image.jpg',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['price']);
        });

        test('validates imageUrl is valid URL', function () {
            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'price' => 99.99,
                'imageUrl' => 'not-a-valid-url',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['imageUrl']);
        });

        test('requires admin authentication', function () {
            $response = $this->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'price' => 99.99,
                'imageUrl' => 'https://example.com/image.jpg',
            ]);

            $response->assertUnauthorized();
        });

        test('customer cannot create products', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);

            $response = $this->actingAs($customer)->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'price' => 99.99,
                'imageUrl' => 'https://example.com/image.jpg',
            ]);

            $response->assertForbidden();
        });
    });

    describe('PUT /api/v1/admin/products/{id} (update)', function () {
        test('admin can update a product with valid data', function () {
            $product = Product::factory()->create();

            $updateData = [
                'title' => 'Updated Product',
                'price' => 199.99,
                'imageUrl' => 'https://example.com/updated.jpg',
            ];

            $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/products/{$product->id}", $updateData);

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
                        'title' => 'Updated Product',
                        'price' => 199.99,
                        'imageUrl' => 'https://example.com/updated.jpg',
                    ],
                ]);

            $this->assertDatabaseHas('products', array_merge(['id' => $product->id], $updateData));
        });

        test('admin can partially update a product', function () {
            $product = Product::factory()->create([
                'title' => 'Original Title',
                'price' => 100.00,
                'imageUrl' => 'https://example.com/original.jpg',
            ]);

            $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => 'Updated Title Only',
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $product->id,
                        'title' => 'Updated Title Only',
                        'price' => 100.00, // Should remain unchanged
                        'imageUrl' => 'https://example.com/original.jpg', // Should remain unchanged
                    ],
                ]);
        });

        test('validates data when updating', function () {
            $product = Product::factory()->create();

            $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => str_repeat('a', 256), // Too long
                'price' => -50, // Negative
                'imageUrl' => 'invalid-url',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'price', 'imageUrl']);
        });

        test('returns 404 when updating non-existent product', function () {
            $response = $this->actingAs($this->admin)->putJson('/api/v1/admin/products/00000000-0000-0000-0000-000000000000', [
                'title' => 'Updated Product',
            ]);

            $response->assertStatus(404);
        });

        test('requires admin authentication', function () {
            $product = Product::factory()->create();

            $response = $this->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => 'Updated Product',
            ]);

            $response->assertUnauthorized();
        });

        test('customer cannot update products', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);
            $product = Product::factory()->create();

            $response = $this->actingAs($customer)->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => 'Updated Product',
            ]);

            $response->assertForbidden();
        });
    });

    describe('DELETE /api/v1/admin/products/{id} (destroy)', function () {
        test('admin can delete a product', function () {
            $product = Product::factory()->create();

            $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/products/{$product->id}");

            $response->assertStatus(204);

            $this->assertDatabaseMissing('products', ['id' => $product->id]);
        });

        test('returns 404 when deleting non-existent product', function () {
            $response = $this->actingAs($this->admin)->deleteJson('/api/v1/admin/products/00000000-0000-0000-0000-000000000000');

            $response->assertStatus(404);
        });

        test('requires admin authentication', function () {
            $product = Product::factory()->create();

            $response = $this->deleteJson("/api/v1/admin/products/{$product->id}");

            $response->assertUnauthorized();
        });

        test('customer cannot delete products', function () {
            $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
            $customer = User::factory()->create([
                'role_id' => $customerRoleId,
            ]);
            $product = Product::factory()->create();

            $response = $this->actingAs($customer)->deleteJson("/api/v1/admin/products/{$product->id}");

            $response->assertForbidden();
        });
    });

    describe('Edge Cases and Error Handling', function () {
        test('handles large numbers for price', function () {
            $productData = [
                'title' => 'Expensive Product',
                'price' => 999999.99,
                'imageUrl' => 'https://example.com/expensive.jpg',
            ];

            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', $productData);

            $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'price' => 999999.99,
                    ],
                ]);
        });

        test('handles very long URLs', function () {
            $longUrl = 'https://example.com/'.str_repeat('very-long-path/', 50).'image.jpg';

            $productData = [
                'title' => 'Product with Long URL',
                'price' => 50.00,
                'imageUrl' => $longUrl,
            ];

            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', $productData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['imageUrl']);
        });

        test('handles special characters in title', function () {
            $productData = [
                'title' => 'Product with Special Chars: !@#$%^&*()',
                'price' => 75.50,
                'imageUrl' => 'https://example.com/special.jpg',
            ];

            $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', $productData);

            $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'title' => 'Product with Special Chars: !@#$%^&*()',
                    ],
                ]);
        });
    });
});
