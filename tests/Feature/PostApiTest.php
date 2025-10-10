<?php

use App\Models\User;
use App\Models\Post;


describe('Post model API CRUD Operations', function () {
    test('authenticated user can create post', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'body' => 'Test Body',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'body' => 'Test Body',
            'user_id' => $user->id,
        ]);
    });


    test('unauthenticated user cannot create post', function () {
        $response = $this->actingAsGuest()->postJson('/api/v1/posts', [
            'title' => 'Unauthenticated Post',
            'body' => 'Test Body',
        ]);

        $response->assertStatus(401);
    });
});


// test('user can`t update other user`s post', function () {
// });

// test('user can`t delete other user`s post', function () {
// });