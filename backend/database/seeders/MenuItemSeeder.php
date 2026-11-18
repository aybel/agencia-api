<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class MenuItemSeeder extends Seeder
{
    /**
     * Guard con el que se crean los permisos (el mismo que usan los roles).
     */
    private const GUARD = 'api';

    /**
     * Crea o actualiza el menú y los permisos que lo controlan.
     *
     * Es idempotente: cada ítem se identifica por `key` (semantic_key), así que ejecutarlo
     * otra vez actualiza en lugar de duplicar. No borra ítems que quites del arreglo.
     */
    public function run(): void
    {
        DB::transaction(fn () => $this->seedItems($this->menu()));

        // Spatie guarda los permisos en caché: limpiarla evita leer datos viejos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Estructura del menú. EDITA ESTE ARREGLO: los ítems actuales son un ejemplo.
     *
     * Claves de cada ítem:
     *  - key        (obligatoria) semantic_key único, por ejemplo "sales.list"
     *  - name       (obligatoria) display_name, texto que se muestra en el menú
     *  - type       header | module | group | link (por defecto: link)
     *  - route      route_name: nombre de la ruta del frontend, no la URL
     *  - icon       semantic_icon, por ejemplo "dashboard"
     *  - permission nombre del permiso que da acceso; si se omite, el ítem es visible para todos
     *  - order      por defecto 10, 20, 30... según su posición entre hermanos
     *  - metadata   arreglo libre que se guarda como JSON
     *  - active     false para ocultarlo sin borrarlo (por defecto: true)
     *  - children   ítems hijos, con las mismas claves
     *
     * @return list<array<string, mixed>>
     */
    private function menu(): array
    {
        return [
            [
                'key' => 'dashboard',
                'name' => 'Dashboard',
                'type' => 'link',
                'route' => 'dashboard',
                'icon' => 'dashboard',
            ],
            [
                'key' => 'section.operation',
                'name' => 'Operación',
                'type' => 'header',
            ],
            [
                'key' => 'sales',
                'name' => 'Ventas',
                'type' => 'module',
                'icon' => 'sale',
                'permission' => 'sales.view',
                'children' => [
                    ['key' => 'sales.list', 'name' => 'Listado de ventas', 'route' => 'sales.index', 'permission' => 'sales.view'],
                ],
            ],
            [
                'key' => 'workshop',
                'name' => 'Taller',
                'type' => 'module',
                'icon' => 'workshop',
                'permission' => 'service_orders.view',
                'children' => [
                    ['key' => 'workshop.orders', 'name' => 'Órdenes de servicio', 'route' => 'service-orders.index', 'permission' => 'service_orders.view'],
                    ['key' => 'workshop.types', 'name' => 'Tipos de servicio', 'route' => 'service-types.index', 'permission' => 'service_types.view'],
                ],
            ],
            [
                'key' => 'section.admin',
                'name' => 'Administración',
                'type' => 'header',
            ],
            [
                'key' => 'admin',
                'name' => 'Administración',
                'type' => 'module',
                'icon' => 'settings',
                'permission' => 'users.view',
                'children' => [
                    ['key' => 'admin.users', 'name' => 'Usuarios', 'route' => 'users.index', 'permission' => 'users.view'],
                    ['key' => 'admin.roles', 'name' => 'Roles', 'route' => 'roles.index', 'permission' => 'roles.view'],
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function seedItems(array $items, ?int $parentId = null): void
    {
        foreach ($items as $position => $item) {
            $permission = isset($item['permission'])
                ? Permission::findOrCreate($item['permission'], self::GUARD)
                : null;

            $menuItem = MenuItem::updateOrCreate(
                ['semantic_key' => $item['key']],
                [
                    'display_name' => $item['name'],
                    'route_name' => $item['route'] ?? null,
                    'semantic_icon' => $item['icon'] ?? null,
                    'semantic_type' => $item['type'] ?? 'link',
                    'permission_id' => $permission?->id,
                    'parent_id' => $parentId,
                    'order' => $item['order'] ?? ($position + 1) * 10,
                    'metadata' => $item['metadata'] ?? null,
                    'is_active' => $item['active'] ?? true,
                ],
            );

            $this->seedItems($item['children'] ?? [], $menuItem->id);
        }
    }
}
