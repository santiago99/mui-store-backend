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
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'price',
                            'imageUrl',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ])
                ->assertJsonCount(3, 'data');
        });
        
        test('returns empty array when no products exist', function () {
            $response = $this->getJson('/api/v1/products');
            
            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => []
                ]);
        });
    });
    
    describe('POST /api/v1/products (store)', function () {
        test('can create a new product with valid data', function () {
            $productData = [
                'title' => 'Test Product',
                'price' => 99.99,
                'imageUrl' => 'https://example.com/image.jpg'
            ];
            
            $response = $this->postJson('/api/v1/products', $productData);
            
            $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'title',
                        'price',
                        'imageUrl',
                        'created_at',
                        'updated_at'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Product created successfully',
                    'data' => [
                        'title' => 'Test Product',
                        'price' => 99.99,
                        'imageUrl' => 'https://example.com/image.jpg'
                    ]
                ]);
            
            $this->assertDatabaseHas('products', $productData);
        });
        
        test('validates required fields', function () {
            $response = $this->postJson('/api/v1/products', []);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'price', 'imageUrl']);
        });
        
        test('validates title is string and max length', function () {
            $response = $this->postJson('/api/v1/products', [
                'title' => str_repeat('a', 256), // Too long
                'price' => 99.99,
                'imageUrl' => 'https://example.com/image.jpg'
            ]);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title']);
        });
        
        test('validates price is numeric and positive', function () {
            $response = $this->postJson('/api/v1/products', [
                'title' => 'Test Product',
                'price' => -10, // Negative price
                'imageUrl' => 'https://example.com/image.jpg'
            ]);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['price']);
        });
        
        test('validates imageUrl is valid URL', function () {
            $response = $this->postJson('/api/v1/products', [
                'title' => 'Test Product',
                'price' => 99.99,
                'imageUrl' => 'not-a-valid-url'
            ]);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['imageUrl']);
        });
    });
    
    describe('GET /api/v1/products/{id} (show)', function () {
        test('can retrieve a specific product', function () {
            $product = Product::factory()->create([
                'title' => 'Specific Product',
                'price' => 149.99,
                'imageUrl' => 'https://example.com/specific.jpg'
            ]);
            
            $response = $this->getJson("/api/v1/products/{$product->id}");
            
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'title',
                        'price',
                        'imageUrl',
                        'created_at',
                        'updated_at'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $product->id,
                        'title' => 'Specific Product',
                        'price' => 149.99,
                        'imageUrl' => 'https://example.com/specific.jpg'
                    ]
                ]);
        });
        
        test('returns 404 for non-existent product', function () {
            $response = $this->getJson('/api/v1/products/999');
            
            $response->assertStatus(404);
        });
    });
    
    describe('PUT /api/v1/products/{id} (update)', function () {
        test('can update a product with valid data', function () {
            $product = Product::factory()->create();
            
            $updateData = [
                'title' => 'Updated Product',
                'price' => 199.99,
                'imageUrl' => 'https://example.com/updated.jpg'
            ];
            
            $response = $this->putJson("/api/v1/products/{$product->id}", $updateData);
            
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'title',
                        'price',
                        'imageUrl',
                        'created_at',
                        'updated_at'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Product updated successfully',
                    'data' => [
                        'id' => $product->id,
                        'title' => 'Updated Product',
                        'price' => 199.99,
                        'imageUrl' => 'https://example.com/updated.jpg'
                    ]
                ]);
            
            $this->assertDatabaseHas('products', array_merge(['id' => $product->id], $updateData));
        });
        
        test('can partially update a product', function () {
            $product = Product::factory()->create([
                'title' => 'Original Title',
                'price' => 100.00,
                'imageUrl' => 'https://example.com/original.jpg'
            ]);
            
            $response = $this->putJson("/api/v1/products/{$product->id}", [
                'title' => 'Updated Title Only'
            ]);
            
            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $product->id,
                        'title' => 'Updated Title Only',
                        'price' => 100.00, // Should remain unchanged
                        'imageUrl' => 'https://example.com/original.jpg' // Should remain unchanged
                    ]
                ]);
        });
        
        test('validates data when updating', function () {
            $product = Product::factory()->create();
            
            $response = $this->putJson("/api/v1/products/{$product->id}", [
                'title' => str_repeat('a', 256), // Too long
                'price' => -50, // Negative
                'imageUrl' => 'invalid-url'
            ]);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'price', 'imageUrl']);
        });
        
        test('returns 404 when updating non-existent product', function () {
            $response = $this->putJson('/api/v1/products/999', [
                'title' => 'Updated Product'
            ]);
            
            $response->assertStatus(404);
        });
    });
    
    describe('DELETE /api/v1/products/{id} (destroy)', function () {
        test('can delete a product', function () {
            $product = Product::factory()->create();
            
            $response = $this->deleteJson("/api/v1/products/{$product->id}");
            
            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Product deleted successfully'
                ]);
            
            $this->assertDatabaseMissing('products', ['id' => $product->id]);
        });
        
        test('returns 404 when deleting non-existent product', function () {
            $response = $this->deleteJson('/api/v1/products/999');
            
            $response->assertStatus(404);
        });
    });
    
    describe('Edge Cases and Error Handling', function () {
        test('handles large numbers for price', function () {
            $productData = [
                'title' => 'Expensive Product',
                'price' => 999999.99,
                'imageUrl' => 'https://example.com/expensive.jpg'
            ];
            
            $response = $this->postJson('/api/v1/products', $productData);
            
            $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'price' => 999999.99
                    ]
                ]);
        });
        
        test('handles very long URLs', function () {
            $longUrl = 'https://example.com/' . str_repeat('very-long-path/', 50) . 'image.jpg';
            
            $productData = [
                'title' => 'Product with Long URL',
                'price' => 50.00,
                'imageUrl' => $longUrl
            ];
            
            $response = $this->postJson('/api/v1/products', $productData);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['imageUrl']);
        });
        
        test('handles special characters in title', function () {
            $productData = [
                'title' => 'Product with Special Chars: !@#$%^&*()',
                'price' => 75.50,
                'imageUrl' => 'https://example.com/special.jpg'
            ];
            
            $response = $this->postJson('/api/v1/products', $productData);
            
            $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'title' => 'Product with Special Chars: !@#$%^&*()'
                    ]
                ]);
        });
    });
});
