<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Formato único de respuesta del endpoint `/filter`, con o sin paginación.
 */
class FilterResponse
{
    /**
     * @param  array<int, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $data,
        public readonly array $meta,
    ) {}

    /**
     * Elige el formato según lo que devolvió el repository.
     *
     * @param  class-string<JsonResource>|null  $resource  Resource que define qué atributos se exponen.
     */
    public static function from(LengthAwarePaginator|Collection $result, ?string $resource = null): self
    {
        return $result instanceof LengthAwarePaginator
            ? self::fromPaginator($result, $resource)
            : self::fromCollection($result, $resource);
    }

    /** @param  class-string<JsonResource>|null  $resource */
    public static function fromPaginator(LengthAwarePaginator $paginator, ?string $resource = null): self
    {
        return new self(
            data: self::transform($paginator->items(), $resource),
            meta: [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'is_paginated' => true,
            ],
        );
    }

    /** @param  class-string<JsonResource>|null  $resource */
    public static function fromCollection(Collection $collection, ?string $resource = null): self
    {
        return new self(
            data: self::transform($collection->values()->all(), $resource),
            meta: [
                'total' => $collection->count(),
                'is_paginated' => false,
            ],
        );
    }

    /** @return array{success: true, data: array<int, mixed>, meta: array<string, mixed>} */
    public function toResponse(): array
    {
        return [
            'success' => true,
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  class-string<JsonResource>|null  $resource
     * @return array<int, mixed>
     */
    private static function transform(array $items, ?string $resource): array
    {
        if ($resource === null) {
            return $items;
        }

        return array_map(fn ($item) => (new $resource($item))->resolve(), $items);
    }
}
