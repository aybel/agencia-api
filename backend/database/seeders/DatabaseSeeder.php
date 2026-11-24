<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Catálogo geográfico, roles y usuario administrador. Los datos del negocio (sucursales,
     * autos, ventas...) se cargan con database/schema/02_seed_manual.sql y
     * database/seeds-python/seed.py.
     */
    public function run(): void
    {
        $this->call([
            // Catálogo de entidades y municipios: sucursales y clientes lo referencian (city_id).
            StateSeeder::class,
            CitySeeder::class,
            RolesSeeder::class,
            MenuItemSeeder::class,
            // Después del menú: asigna a `admin` todos los permisos, incluidos los que crea el menú.
            PermissionsSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
