<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * RoleSeeder
 *
 * Creates the foundational roles for the application.
 *
 * The "super-admin" role is granted ALL existing permissions automatically.
 * The Gate::before bypass in AppServiceProvider also ensures that any
 * future permission added to the system is immediately available to
 * super-admin without re-running this seeder.
 *
 * A default super-admin user is created here for initial access.
 * Change the credentials in your .env or after first login.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Create the super-admin role ────────────────────────────────────
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin'],
            ['guard_name' => 'web']
        );

        // Assign every permission to super-admin.
        // The Gate::before bypass already grants all abilities, but explicit
        // assignment ensures the role's permission list is accurate when
        // displayed in the UI (e.g., GET /api/me returns correct permissions).
        $superAdmin->syncPermissions(Permission::all());

        $this->command->info('Role "super-admin" created with all permissions.');

        // ── 2. Create the default super-admin user ────────────────────────────
        $adminEmail = env('ADMIN_EMAIL', 'admin@gestion-politica.local');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@12345!')),
            ]
        );

        $admin->assignRole($superAdmin);

        $this->command->info("Super-admin user ready: {$adminEmail}");
    }
}
