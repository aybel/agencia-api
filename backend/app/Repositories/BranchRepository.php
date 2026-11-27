<?php

namespace App\Repositories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\Concerns\AppliesStructuredFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchRepository implements BranchRepositoryInterface
{

    use AppliesStructuredFilters;
    public function __construct(private readonly Branch $model) {}
    public function filter(array $filters = [], ?array $orderBy = null, ?array $pagination = null): LengthAwarePaginator|Collection
    {
        $query = $this->applyFilters($this->model->newQuery(), $filters);
        $query = $this->applyOrderBy($query, $orderBy);

        return $this->paginateOrGet($query, $pagination);
    }
    /** @return list<string> */
    protected function getAllowedFilterFields(): array
    {
        return [
            'id',
            'name',
            'city_id',
            'address',
            'created_at',
            'updated_at'
        ];
    }
}
