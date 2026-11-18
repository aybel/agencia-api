<?php

namespace App\Repositories\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Traduce el lenguaje de filtros del endpoint `/filter` (field, operator, value)
 * a consultas del query builder.
 *
 * Lo común vive aquí (cómo se interpreta `eq`, `between`, `in`...). Lo específico
 * se queda en cada repository: qué campos se pueden filtrar y ordenar.
 *
 * Es estricto a propósito: un campo, operador u orden no permitido lanza
 * InvalidArgumentException en lugar de ignorarse, porque ignorarlo devolvería
 * más datos de los que el cliente pidió filtrar.
 */
trait AppliesStructuredFilters
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Campos por los que esta entidad permite filtrar.
     *
     * @return list<string>
     */
    abstract protected function getAllowedFilterFields(): array;

    /**
     * Campos por los que esta entidad permite ordenar. Por defecto, los mismos que filtra.
     *
     * @return list<string>
     */
    protected function getAllowedSortFields(): array
    {
        return $this->getAllowedFilterFields();
    }

    /**
     * Orden cuando el cliente no envía `order_by`.
     *
     * @return array{column: string, direction: string}
     */
    protected function getDefaultOrderBy(): array
    {
        return ['column' => 'id', 'direction' => 'asc'];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<array{field?: string, operator?: string, value?: mixed}>  $filters
     * @return Builder<Model>
     *
     * @throws InvalidArgumentException
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $allowedFields = $this->getAllowedFilterFields();

        foreach ($filters as $filter) {
            if (! isset($filter['field'], $filter['operator'])) {
                throw new InvalidArgumentException('Cada filtro requiere "field" y "operator".');
            }

            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;

            if (! in_array($field, $allowedFields, true)) {
                throw new InvalidArgumentException("El campo [{$field}] no está permitido para filtrar.");
            }

            match ($operator) {
                'eq' => $query->where($field, '=', $value),
                'ne' => $query->where($field, '!=', $value),
                'gt' => $query->where($field, '>', $value),
                'gte' => $query->where($field, '>=', $value),
                'lt' => $query->where($field, '<', $value),
                'lte' => $query->where($field, '<=', $value),
                'like' => $query->whereLike($field, '%'.$this->escapeLike($value).'%', caseSensitive: true),
                'ilike' => $query->whereLike($field, '%'.$this->escapeLike($value).'%'),
                'startsWith' => $query->whereLike($field, $this->escapeLike($value).'%', caseSensitive: true),
                'endsWith' => $query->whereLike($field, '%'.$this->escapeLike($value), caseSensitive: true),
                'in' => $query->whereIn($field, (array) $value),
                'nin' => $query->whereNotIn($field, (array) $value),
                'null' => $query->whereNull($field),
                'notnull' => $query->whereNotNull($field),
                'between' => $this->applyBetween($query, $field, $value),
                default => throw new InvalidArgumentException("El operador [{$operator}] no es válido."),
            };
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array{column?: string, direction?: string}|null  $orderBy
     * @return Builder<Model>
     *
     * @throws InvalidArgumentException
     */
    protected function applyOrderBy(Builder $query, ?array $orderBy): Builder
    {
        $orderBy ??= $this->getDefaultOrderBy();

        $column = $orderBy['column'] ?? null;
        $direction = strtolower((string) ($orderBy['direction'] ?? 'asc'));

        if (! in_array($column, $this->getAllowedSortFields(), true)) {
            throw new InvalidArgumentException('La columna ['.(string) $column.'] no está permitida para ordenar.');
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("La dirección [{$direction}] no es válida.");
        }

        $query->orderBy($column, $direction);

        // Desempate por llave primaria: sin él, ordenar por una columna con valores repetidos
        // puede duplicar u omitir filas entre páginas.
        $key = $query->getModel()->getKeyName();

        if ($column !== $key) {
            $query->orderBy($key);
        }

        return $query;
    }

    /**
     * Sin `pagination` devuelve todos los registros; con ella, una página.
     *
     * @param  Builder<Model>  $query
     * @param  array{page?: int|string, limit?: int|string}|null  $pagination
     * @return LengthAwarePaginator<int, Model>|Collection<int, Model>
     */
    protected function paginateOrGet(Builder $query, ?array $pagination): LengthAwarePaginator|Collection
    {
        if ($pagination === null) {
            return $query->get();
        }

        $perPage = min(max((int) ($pagination['limit'] ?? self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
        $page = max((int) ($pagination['page'] ?? 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyBetween(Builder $query, string $field, mixed $value): Builder
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException('El operador [between] requiere un arreglo de dos valores.');
        }

        return $query->whereBetween($field, array_values($value));
    }

    /** Escapa `%`, `_` y `\` para que el valor del cliente se busque literalmente. */
    private function escapeLike(mixed $value): string
    {
        return addcslashes((string) $value, '%_\\');
    }
}
