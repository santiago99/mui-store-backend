<?php

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs($this->user);
});

it('can view empty cart', function () {
    $response = $this->getJson('/api/v1/cart');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('can add item to cart', function () {
    $response = $this->postJson('/api/v1/cart', [
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'product_id',
                'quantity',
                'product' => [
                    'id',
                    'title',
                    'price',
                    'imageUrl',
                ],
            ],
        ]);

    $this->assertDatabaseHas('cart_items', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);
});

it('can add duplicate item to cart and updates quantity', function () {
    // Add item first time
    $this->postJson('/api/v1/cart', [
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    // Add same item again
    $response = $this->postJson('/api/v1/cart', [
        'product_id' => $this->product->id,
        'quantity' => 3,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.quantity', 5);

    $this->assertDatabaseHas('cart_items', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
    ]);

    // Should only have one cart item
    $this->assertDatabaseCount('cart_items', 1);
});

it('can update cart item quantity', function () {
    $cartItem = CartItem::factory()->create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    $response = $this->patchJson("/api/v1/cart/{$cartItem->id}", [
        'quantity' => 5,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.quantity', 5);

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => 5,
    ]);
});

it('can delete cart item', function () {
    $cartItem = CartItem::factory()->create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
    ]);

    $response = $this->deleteJson("/api/v1/cart/{$cartItem->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('cart_items', [
        'id' => $cartItem->id,
    ]);
});

it('cannot access other users cart items', function () {
    $otherUser = User::factory()->create();
    $cartItem = CartItem::factory()->create([
        'user_id' => $otherUser->id,
        'product_id' => $this->product->id,
    ]);

    $response = $this->patchJson("/api/v1/cart/{$cartItem->id}", [
        'quantity' => 5,
    ]);

    $response->assertForbidden();
});

it('cannot delete other users cart items', function () {
    $otherUser = User::factory()->create();
    $cartItem = CartItem::factory()->create([
        'user_id' => $otherUser->id,
        'product_id' => $this->product->id,
    ]);

    $response = $this->deleteJson("/api/v1/cart/{$cartItem->id}");

    $response->assertForbidden();
});

it('can merge empty localStorage cart', function () {
    $response = $this->postJson('/api/v1/cart/merge', [
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->assertDatabaseHas('cart_items', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);
});

it('can merge localStorage cart with existing items', function () {
    // Create existing cart item
    CartItem::factory()->create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 3,
    ]);

    // Create another product for merge
    $anotherProduct = Product::factory()->create();

    $response = $this->postJson('/api/v1/cart/merge', [
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $anotherProduct->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');

    // Check quantities were merged
    $this->assertDatabaseHas('cart_items', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 5, // 3 + 2
    ]);

    $this->assertDatabaseHas('cart_items', [
        'user_id' => $this->user->id,
        'product_id' => $anotherProduct->id,
        'quantity' => 1,
    ]);
});

it('validates merge request with invalid product_id', function () {
    $response = $this->postJson('/api/v1/cart/merge', [
        'items' => [
            [
                'product_id' => 99999, // Non-existent product
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_id']);
});

it('validates store request with invalid data', function () {
    $response = $this->postJson('/api/v1/cart', [
        'product_id' => 99999, // Non-existent product
        'quantity' => 0, // Invalid quantity
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['product_id', 'quantity']);
});

it('validates update request with invalid quantity', function () {
    $cartItem = CartItem::factory()->create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
    ]);

    $response = $this->patchJson("/api/v1/cart/{$cartItem->id}", [
        'quantity' => 0, // Invalid quantity
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity']);
});

it('requires authentication for cart endpoints', function () {
    // Test without authentication
    Auth::logout();

    $this->getJson('/api/v1/cart')->assertUnauthorized();
    $this->postJson('/api/v1/cart', [])->assertUnauthorized();
    $this->postJson('/api/v1/cart/merge', [])->assertUnauthorized();
    $this->patchJson('/api/v1/cart/1', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/cart/1')->assertUnauthorized();
});
