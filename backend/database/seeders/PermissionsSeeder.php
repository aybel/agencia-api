<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Guard con el que se crean los permisos (el mismo que usan los roles).
     */
    private const GUARD = 'api';

    /**
     * Permisos por recurso. El nombre final es "<recurso>.<acción>" (por ejemplo `branches.view_any`)
     * y es el que usa el middleware de las rutas: ->middleware('permission:branches.view_any').
     *
     * Acciones: view_any (listar y buscar), view (ver uno), create, update, delete.
     *
     * Por ahora solo `branches`. Para agregar un recurso, añade una línea a este arreglo.
     *
     * @var array<string, list<string>>
     */
    private const RESOURCES = [
        'branches' => ['view_any', 'view', 'create', 'update', 'delete'],
    ];

    /**
     * Crea los permisos y se los asigna al rol `admin`.
     *
     * Es idempotente: ejecutarlo otra vez no duplica nada. Debe correr después de RolesSeeder
     * (que crea el rol `admin`) y de MenuItemSeeder (que también crea permisos), para que el
     * administrador reciba todos.
     */
    public function run(): void
    {
        // Spatie guarda los permisos en caché: limpiarla evita leer datos viejos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::RESOURCES as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}", self::GUARD);
            }
        }

        // Crear roles adicionales para el sistema ERP SE USARA DESPUÉS PARA CUANDO SE TENGAN LOS MODULOS
        // $this->createERPRoles(self::GUARD);
    }

    /**
     * Crear roles específicos del ERP todavia no se definen
     */
    private function createERPRoles(string $guardName): void
    {
        // Rol: Jefe de Compras - Gestiona todo el proceso de compras
        $jefeComprasRole = Role::firstOrCreate(['name' => 'Jefe de Compras', 'guard_name' => $guardName]);
        $jefeComprasRole->syncPermissions([
            'supplier.view_any',
            'supplier.view',
            'supplier.create',
            'supplier.update',
            'materials.view_any',
            'materials.view',
            'materials.create',
            'materials.update',
            'budget_requests.view_any',
            'budget_requests.view',
            'budget_requests.create',
            'budget_requests.update',
            'budget_requests.delete',
            'budget_assignments.view_any',
            'budget_assignments.view',
            'departments.view_any',
            'departments.view',
        ]);

        // Rol: Comprador - Ejecuta órdenes de compra
        $compradorRole = Role::firstOrCreate(['name' => 'Comprador', 'guard_name' => $guardName]);
        $compradorRole->syncPermissions([
            'supplier.view_any',
            'supplier.view',
            'materials.view_any',
            'materials.view',
            'budget_requests.view_any',
            'budget_requests.view',
            'budget_requests.create',
            'budget_assignments.view_any',
            'budget_assignments.view',
        ]);

        // Rol: Jefe de Departamento - Gestiona su departamento
        $jefeDepartamentoRole = Role::firstOrCreate(['name' => 'Jefe de Departamento', 'guard_name' => $guardName]);
        $jefeDepartamentoRole->syncPermissions([
            'budget_requests.view_any',
            'budget_requests.view',
            'budget_requests.create',
            'budget_requests.update',
            'budget_assignments.view_any',
            'budget_assignments.view',
            'materials.view_any',
            'materials.view',
            'departments.view_any',
            'departments.view',
        ]);

        // Rol: Empleado - Solo lectura básica
        $empleadoRole = Role::firstOrCreate(['name' => 'Empleado', 'guard_name' => $guardName]);
        $empleadoRole->syncPermissions([
            'budget_requests.view',
            'materials.view',
        ]);

        // Rol: Operativo - Ejecuta operaciones básicas
        $operativoRole = Role::firstOrCreate(['name' => 'Operativo', 'guard_name' => $guardName]);
        $operativoRole->syncPermissions([
            'materials.view_any',
            'budget_requests.view_any',
            'budget_requests.view',
            'budget_requests.create',
            'budget_requests.update',
            'budget_requests.delete',
            'departments.view_any',
            'departments.view',
        ]);

        // Rol: Aprobador - Aprueba solicitudes
        $aprobadorRole = Role::firstOrCreate(['name' => 'Aprobador', 'guard_name' => $guardName]);
        $aprobadorRole->syncPermissions([
            'budget_requests.view_any',
            'budget_requests.view',
            'budget_requests.update',
            'budget_assignments.view_any',
            'budget_assignments.view',
            'departments.view_any',
            'departments.view',
        ]);

        $this->command->info('✓ Roles del ERP creados: Jefe de Compras, Comprador, Jefe de Departamento, Empleado, Operativo, Aprobador');
    }
}
