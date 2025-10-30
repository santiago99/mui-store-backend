<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->artisan('migrate:fresh');

    // Ensure required roles exist for tests
    Role::query()->firstOrCreate(['name' => Role::CUSTOMER]);
    Role::query()->firstOrCreate(['name' => Role::ADMIN]);
});

describe('Admin-only access', function () {
    test('admin can access /api/v1/admin/ping', function () {
        $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');
        $admin = User::factory()->create([
            'role_id' => $adminRoleId,
        ]);

        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/admin/ping');

        $response->assertSuccessful();
    });

    test('customer cannot access /api/v1/admin/ping', function () {
        $customerRoleId = Role::query()->where('name', Role::CUSTOMER)->value('id');
        $customer = User::factory()->create([
            'role_id' => $customerRoleId,
        ]);

        $this->actingAs($customer);

        $response = $this->getJson('/api/v1/admin/ping');

        $response->assertForbidden();
    });
});


