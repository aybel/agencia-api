<?php

namespace App\Http\Requests;

class FilterBranchRequest extends FilterRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // 1. Verificar que el usuario esté logueado
        if (! $this->user()) {
            return false;
        }

        // 2. Verificar si el usuario tiene el permiso para ver sucursales
        return $this->user()->can('branches.view_any');
    }

    /** @return array<string, 'string'|'integer'|'numeric'|'date'> */
    protected function filterableFields(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'city_id' => 'integer',
            'address' => 'string',
            'created_at' => 'date',
            'updated_at' => 'date',
        ];
    }
}
