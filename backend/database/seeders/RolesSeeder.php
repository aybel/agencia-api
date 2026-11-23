<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Roles del sistema. Los cuatro últimos corresponden a los puestos de job_titles (vendedor, gerente, asesor de servicio y mecánico).
     * Los permisos se agregan cuando existan los endpoints que los usen.
     */
    public const ROLES = ['admin', 'manager', 'seller', 'service_advisor', 'mechanic'];

    /**
     * Rol que se sugiere para cada puesto (job_titles.code => rol). Se guarda en job_title_role, con llaves
     * foráneas reales, y solo precarga el rol al crear el usuario de un empleado.
     * El Jefe Mecánico (04) queda con el rol mechanic hasta que el negocio defina si necesita otro.
     */
    public const SUGGESTED_ROLE_BY_JOB_TITLE = [
        '01' => 'seller',
        '02' => 'manager',
        '03' => 'service_advisor',
        '04' => 'mechanic',
        '05' => 'mechanic',
    ];

    public function run(): void
    {
        // Spatie guarda roles y permisos en caché: limpiarla evita leer datos viejos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name) {
            Role::findOrCreate($name, 'api');
        }

        $this->suggestRolesForJobTitles();
    }

    private function suggestRolesForJobTitles(): void
    {
        $jobTitleIds = DB::table('job_titles')->pluck('id', 'code');
        $roleIds = Role::where('guard_name', 'api')->pluck('id', 'name');
        $now = now();

        foreach (self::SUGGESTED_ROLE_BY_JOB_TITLE as $code => $roleName) {
            DB::table('job_title_role')->updateOrInsert(
                ['job_title_id' => $jobTitleIds[$code], 'role_id' => $roleIds[$roleName]],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
