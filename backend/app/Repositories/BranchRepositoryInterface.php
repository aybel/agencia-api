<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BranchRepositoryInterface
{
    /**
     * Filtrado avanzado. Devuelve un paginador si se envía `$pagination`; si no, todos los registros.
     *
     * @param  list<array{field: string, operator: string, value?: mixed}>  $filters
     * @param  array{column: string, direction: string}|null  $orderBy
     * @param  array{page?: int, limit?: int}|null  $pagination
     */
    public function filter(array $filters = [], ?array $orderBy = null, ?array $pagination = null): LengthAwarePaginator|Collection;
}
