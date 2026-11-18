<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Base del request del endpoint `/filter`. Cada entidad solo declara qué campos
 * se pueden filtrar y de qué tipo (ver `filterableFields()`).
 *
 * Valida la forma de `filters`, `order_by` y `pagination`, y que cada valor sea
 * compatible con el tipo del campo. PostgreSQL es estricto con los tipos: sin esta
 * validación, `id = 'abc'` o un `LIKE` sobre un entero terminan en error 500.
 */
abstract class FilterRequest extends FormRequest
{
    private const TEXT_OPERATORS = [
        'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'like', 'ilike',
        'in', 'nin', 'null', 'notnull', 'between', 'startsWith', 'endsWith',
    ];

    private const ORDERED_OPERATORS = [
        'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'nin', 'null', 'notnull', 'between',
    ];

    /** Operadores que aplican a cada tipo de campo. */
    private const OPERATORS_BY_TYPE = [
        'string' => self::TEXT_OPERATORS,
        'integer' => self::ORDERED_OPERATORS,
        'numeric' => self::ORDERED_OPERATORS,
        'date' => self::ORDERED_OPERATORS,
    ];

    /** Reglas de Laravel con las que se valida cada valor según el tipo del campo. */
    private const VALUE_RULES_BY_TYPE = [
        'string' => ['string', 'max:255'],
        'integer' => ['integer'],
        'numeric' => ['numeric'],
        'date' => ['date'],
    ];

    private const MAX_FILTERS = 20;

    private const MAX_LIST_ITEMS = 100;

    /**
     * Campos filtrables de la entidad y su tipo: `string`, `integer`, `numeric` o `date`.
     * Debe coincidir con `getAllowedFilterFields()` del repository.
     *
     * @return array<string, 'string'|'integer'|'numeric'|'date'>
     */
    abstract protected function filterableFields(): array;

    /**
     * Columnas por las que se puede ordenar. Por defecto, las mismas que se filtran.
     *
     * @return list<string>
     */
    protected function sortableFields(): array
    {
        return array_keys($this->filterableFields());
    }

    /** El acceso se controla con middleware en la ruta (`auth:api`, `role:...`). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'filters' => ['sometimes', 'array', 'max:'.self::MAX_FILTERS],
            'filters.*' => ['array'],
            'filters.*.field' => ['required', 'string', Rule::in(array_keys($this->filterableFields()))],
            'filters.*.operator' => ['required', 'string', Rule::in(self::TEXT_OPERATORS)],
            // El valor se valida en after(), porque depende del campo y del operador.
            'filters.*.value' => ['nullable'],

            'order_by' => ['sometimes', 'array'],
            'order_by.column' => ['required_with:order_by', 'string', Rule::in($this->sortableFields())],
            'order_by.direction' => ['required_with:order_by', 'string', Rule::in(['asc', 'desc'])],

            // nullable: sin paginación se devuelven todos los registros.
            'pagination' => ['sometimes', 'nullable', 'array'],
            'pagination.page' => ['sometimes', 'integer', 'min:1'],
            'pagination.limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Filtros ya validados, listos para el repository.
     *
     * @return list<array{field: string, operator: string, value?: mixed}>
     */
    public function filters(): array
    {
        return $this->validated()['filters'] ?? [];
    }

    /** @return array{column: string, direction: string}|null */
    public function orderBy(): ?array
    {
        return $this->validated()['order_by'] ?? null;
    }

    /**
     * `null` (o sin la clave) devuelve todos los registros. Un objeto vacío (`{}`) pagina con los
     * valores por defecto. Se lee aparte porque Laravel descarta de validated() los arreglos vacíos.
     *
     * @return array{page?: int, limit?: int}|null
     */
    public function pagination(): ?array
    {
        if ($this->input('pagination') === null) {
            return null;
        }

        return $this->validated()['pagination'] ?? [];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'filters.max' => 'No se pueden aplicar más de '.self::MAX_FILTERS.' filtros a la vez.',
            'filters.*.field.in' => 'El campo :input no está permitido para filtrado.',
            'filters.*.operator.in' => 'El operador :input no es válido.',
            'order_by.column.in' => 'La columna :input no está permitida para ordenamiento.',
            'pagination.limit.max' => 'El límite no puede ser mayor a 100 registros.',
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fields = $this->filterableFields();

            foreach ((array) $this->input('filters', []) as $index => $filter) {
                $field = $filter['field'] ?? null;
                $operator = $filter['operator'] ?? null;

                // Si el campo u operador ya falló en rules(), no hay nada más que revisar.
                if (! is_string($field) || ! isset($fields[$field]) || ! is_string($operator)) {
                    continue;
                }

                if (! in_array($operator, self::TEXT_OPERATORS, true)) {
                    continue;
                }

                $this->validateFilterValue($validator, $index, $fields[$field], $operator, $filter['value'] ?? null);
            }
        }];
    }

    private function validateFilterValue(Validator $validator, int|string $index, string $type, string $operator, mixed $value): void
    {
        $key = "filters.{$index}.value";

        if (! in_array($operator, self::OPERATORS_BY_TYPE[$type], true)) {
            $validator->errors()->add("filters.{$index}.operator", "El operador {$operator} no aplica a campos de tipo {$type}.");

            return;
        }

        if (in_array($operator, ['null', 'notnull'], true)) {
            return;
        }

        if ($value === null || $value === '' || $value === []) {
            $validator->errors()->add($key, 'El valor es requerido para este operador.');

            return;
        }

        if (in_array($operator, ['in', 'nin', 'between'], true)) {
            $this->validateListValue($validator, $key, $type, $operator, $value);

            return;
        }

        if (is_array($value)) {
            $validator->errors()->add($key, "El operador {$operator} requiere un solo valor.");

            return;
        }

        $this->validateScalar($validator, $key, $type, $value);
    }

    private function validateListValue(Validator $validator, string $key, string $type, string $operator, mixed $value): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $validator->errors()->add($key, "El operador {$operator} requiere un arreglo de valores.");

            return;
        }

        if ($operator === 'between' && count($value) !== 2) {
            $validator->errors()->add($key, 'El operador between requiere exactamente dos valores.');

            return;
        }

        if (count($value) > self::MAX_LIST_ITEMS) {
            $validator->errors()->add($key, 'La lista no puede tener más de '.self::MAX_LIST_ITEMS.' valores.');

            return;
        }

        foreach ($value as $position => $item) {
            $this->validateScalar($validator, "{$key}.{$position}", $type, $item);
        }
    }

    private function validateScalar(Validator $validator, string $key, string $type, mixed $value): void
    {
        if (! is_scalar($value)) {
            $validator->errors()->add($key, 'El valor debe ser un dato simple, no un arreglo.');

            return;
        }

        $check = validator(['value' => $value], ['value' => ['required', ...self::VALUE_RULES_BY_TYPE[$type]]]);

        if ($check->fails()) {
            $validator->errors()->add($key, "El valor {$value} no es válido para un campo de tipo {$type}.");
        }
    }
}
