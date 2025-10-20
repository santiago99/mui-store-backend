<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Refresh database before each test
    $this->artisan('migrate:fresh');
});

describe('User Profile API', function () {

    describe('PUT /api/v1/user (update profile)', function () {
        test('can update user profile with valid data', function () {
            $user = User::factory()->create([
                'name' => 'Original Name',
                'email' => 'test@example.com',
            ]);

            $this->actingAs($user);

            $updateData = [
                'name' => 'Updated Name',
            ];

            $response = $this->putJson('/api/v1/user', $updateData);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                ])
                ->assertJson([
                    'data' => [
                        'id' => $user->id,
                        'name' => 'Updated Name',
                        'email' => 'test@example.com',
                    ],
                ]);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => 'Updated Name',
            ]);
        });

        test('validates name is required', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $response = $this->putJson('/api/v1/user', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        test('validates name is string and max length', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $response = $this->putJson('/api/v1/user', [
                'name' => str_repeat('a', 256), // Too long
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        test('requires authentication', function () {
            $response = $this->putJson('/api/v1/user', [
                'name' => 'Test Name',
            ]);

            $response->assertStatus(401);
        });
    });

    describe('PUT /api/v1/user/password (change password)', function () {
        test('can change password with valid current password', function () {
            $user = User::factory()->create([
                'password' => Hash::make('currentpassword'),
            ]);

            $this->actingAs($user);

            $passwordData = [
                'current_password' => 'currentpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Password updated successfully.',
                ]);

            // Verify password was actually changed
            $user->refresh();
            expect(Hash::check('newpassword123', $user->password))->toBeTrue();
            expect(Hash::check('currentpassword', $user->password))->toBeFalse();
        });

        test('rejects incorrect current password', function () {
            $user = User::factory()->create([
                'password' => Hash::make('currentpassword'),
            ]);

            $this->actingAs($user);

            $passwordData = [
                'current_password' => 'wrongpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(422)
                ->assertJson([
                    'message' => 'The current password is incorrect.',
                ]);

            // Verify password was not changed
            $user->refresh();
            expect(Hash::check('currentpassword', $user->password))->toBeTrue();
        });

        test('validates required fields', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $response = $this->putJson('/api/v1/user/password', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password', 'new_password', 'new_password_confirmation']);
        });

        test('validates new password minimum length', function () {
            $user = User::factory()->create([
                'password' => Hash::make('currentpassword'),
            ]);

            $this->actingAs($user);

            $passwordData = [
                'current_password' => 'currentpassword',
                'new_password' => '123', // Too short
                'new_password_confirmation' => '123',
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['new_password']);
        });

        test('validates password confirmation matches', function () {
            $user = User::factory()->create([
                'password' => Hash::make('currentpassword'),
            ]);

            $this->actingAs($user);

            $passwordData = [
                'current_password' => 'currentpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'differentpassword',
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['new_password']);
        });

        test('requires authentication', function () {
            $passwordData = [
                'current_password' => 'currentpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(401);
        });
    });

    describe('Edge Cases and Error Handling', function () {
        test('handles special characters in name', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $response = $this->putJson('/api/v1/user', [
                'name' => 'Name with Special Chars: !@#$%^&*()',
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'name' => 'Name with Special Chars: !@#$%^&*()',
                    ],
                ]);
        });

        test('handles very long valid password', function () {
            $user = User::factory()->create([
                'password' => Hash::make('currentpassword'),
            ]);

            $this->actingAs($user);

            $longPassword = str_repeat('a', 100);
            $passwordData = [
                'current_password' => 'currentpassword',
                'new_password' => $longPassword,
                'new_password_confirmation' => $longPassword,
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Password updated successfully.',
                ]);
        });

        test('handles empty current password', function () {
            $user = User::factory()->create([
                'password' => Hash::make('currentpassword'),
            ]);

            $this->actingAs($user);

            $passwordData = [
                'current_password' => '',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ];

            $response = $this->putJson('/api/v1/user/password', $passwordData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
        });
    });
});
