<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Solo roles y usuario administrador. Los datos del negocio (sucursales,
     * autos, ventas...) se cargan con database/schema/02_seed_manual.sql y
     * database/seeds-python/seed.py.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
