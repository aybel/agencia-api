<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Roles del sistema. Los cuatro últimos coinciden con employees.job_title.
     * Los permisos se agregan cuando existan los endpoints que los usen.
     */
    public const ROLES = ['admin', 'manager', 'seller', 'service_advisor', 'mechanic'];

    public function run(): void
    {
        // Spatie guarda roles y permisos en caché: limpiarla evita leer datos viejos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name) {
            Role::findOrCreate($name, 'api');
        }
    }
}
