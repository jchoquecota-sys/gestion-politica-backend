<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters:
     *  1. Permissions must exist before roles can reference them.
     *  2. Roles must exist before users can be assigned to them.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            CargoSeeder::class,
            TipoActividadSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(MockDataSeeder::class);
        }
    }
}
