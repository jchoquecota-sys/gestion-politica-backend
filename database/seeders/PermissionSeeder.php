<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * PermissionSeeder
 *
 * This is the single source of truth for ALL permissions in the system.
 * Permissions are managed exclusively by developers — they are never
 * created, edited, or deleted through the API.
 *
 * Convention: "module:action"
 * Examples:   "roles:list", "members:create", "reports:export"
 *
 * To add new permissions in the future:
 *  1. Add the string to the relevant section below.
 *  2. Run: php artisan db:seed --class=PermissionSeeder
 */
class PermissionSeeder extends Seeder
{
    /**
     * All application permissions grouped by module.
     * Keep this list alphabetically sorted within each group.
     */
    private array $permissions = [
        // ── Roles module ──────────────────────────────────────────────────────
        'roles:assign-permissions', // Assign or revoke permissions from a role
        'roles:create',
        'roles:delete',
        'roles:edit',
        'roles:list',
        'roles:view',

        // ── Users module ─────────────────────────────────────────────────────
        'users:create',
        'users:delete',
        'users:edit',
        'users:list',
        'users:view',

        // ── Reports module ────────────────────────────────────────────────────
        // 'reports:export',
        // 'reports:view',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        $this->command->info('Permissions seeded: '.count($this->permissions).' permissions.');
    }
}
