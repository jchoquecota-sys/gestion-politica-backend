<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\Sanctum;

test('unauthenticated users get 401 on roles list', function () {
    $response = $this->getJson('/api/roles');

    $response->assertStatus(401);
});

test('authenticated users without permission get 403 on roles list', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/roles');

    $response->assertStatus(403);
});

test('authenticated users with permission get 200 on roles list', function () {
    $permission = Permission::create(['name' => 'roles:view', 'guard_name' => 'web']);
    
    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/roles');

    $response->assertStatus(200);
});

test('super admin gets 200 on roles list regardless of specific permissions', function () {
    $superAdminRole = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    
    $user = User::factory()->create();
    $user->assignRole($superAdminRole);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/roles');

    $response->assertStatus(200);
});
