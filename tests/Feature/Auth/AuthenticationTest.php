<?php

use App\Models\User;

const API_PREFIX = '/api/v1';

test('users can authenticate using the login request', function () {
    $user = User::factory()->create();

    $response = $this->post(API_PREFIX . '/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertNoContent();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(API_PREFIX . '/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(API_PREFIX . '/logout');

    $this->assertGuest();
    $response->assertNoContent();
});
