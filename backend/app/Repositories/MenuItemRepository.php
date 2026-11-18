<?php

namespace App\Repositories;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;

class MenuItemRepository implements MenuItemRepositoryInterface
{
    /** Tipos que solo agrupan a otros ítems: sin hijos visibles ni ruta propia no sirven de nada. */
    private const CONTAINER_TYPES = ['module', 'group'];

    /**
     * Reglas del menú de un usuario:
     *  1. Un ítem se ve si está activo y no tiene permiso, o el usuario tiene ese permiso.
     *  2. Si un ítem no se ve, tampoco se ven sus descendientes.
     *  3. Un `module` o `group` sin hijos visibles y sin `route_name` se omite.
     *  4. Un `header` se omite si no tiene ningún ítem debajo antes del siguiente `header`.
     *
     * Hace una sola consulta y arma el árbol en memoria, así que admite cualquier profundidad.
     */
    public function getTreeForUser(User $user): Collection
    {
        $byParent = MenuItem::query()
            ->active()
            ->forUser($user)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MenuItem $item) => $item->parent_id ?? 0);

        return $this->buildLevel($byParent, 0);
    }

    /**
     * @param  Collection<int|string, Collection<int, MenuItem>>  $byParent  Ítems visibles agrupados por parent_id (0 = raíz).
     * @return Collection<int, MenuItem>
     */
    private function buildLevel(Collection $byParent, int $parentId): Collection
    {
        $level = ($byParent->get($parentId) ?? collect())
            ->each(fn (MenuItem $item) => $item->setRelation('children', $this->buildLevel($byParent, $item->id)))
            ->reject(fn (MenuItem $item) => $this->isEmptyContainer($item));

        return $this->dropEmptyHeaders($level);
    }

    private function isEmptyContainer(MenuItem $item): bool
    {
        return in_array($item->semantic_type, self::CONTAINER_TYPES, true)
            && $item->children->isEmpty()
            && $item->route_name === null;
    }

    /**
     * @param  Collection<int, MenuItem>  $siblings
     * @return Collection<int, MenuItem>
     */
    private function dropEmptyHeaders(Collection $siblings): Collection
    {
        $kept = collect();
        $pendingHeader = null;

        foreach ($siblings as $item) {
            if ($item->semantic_type === 'header') {
                // Un encabezado seguido de otro encabezado (o del final) no tiene nada debajo.
                $pendingHeader = $item;

                continue;
            }

            if ($pendingHeader !== null) {
                $kept->push($pendingHeader);
                $pendingHeader = null;
            }

            $kept->push($item);
        }

        return $kept->values();
    }
}
