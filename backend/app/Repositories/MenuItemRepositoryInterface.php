<?php

namespace App\Repositories;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;

interface MenuItemRepositoryInterface
{
    /**
     * Menú que el usuario puede ver, como árbol: cada ítem trae su relación `children` ya cargada.
     *
     * @return Collection<int, MenuItem>
     */
    public function getTreeForUser(User $user): Collection;
}
