# Manual: filtros avanzados (`POST /filter`)

Guía para agregar a cualquier entidad el endpoint de consulta estructurada: filtros múltiples, ordenamiento controlado, paginación opcional y validación estricta. La infraestructura común **ya está creada**; para cada entidad nueva solo se agregan 6 archivos pequeños y dos líneas de configuración (sección 4).

## Contenido

1. [Qué resuelve y contrato](#1-qué-resuelve-y-contrato)
2. [Arquitectura](#2-arquitectura)
3. [Cambios respecto al modelo original](#3-cambios-respecto-al-modelo-original)
4. [Implementar una entidad paso a paso](#4-implementar-una-entidad-paso-a-paso)
5. [Operadores y tipos de campo](#5-operadores-y-tipos-de-campo)
6. [Ejemplos de uso](#6-ejemplos-de-uso)
7. [Errores](#7-errores)
8. [Checklist y campos sugeridos por entidad](#8-checklist-y-campos-sugeridos-por-entidad)
9. [Consideraciones](#9-consideraciones)

## 1. Qué resuelve y contrato

La idea central **no es el trait**, sino este contrato:

- El cliente describe filtros con `field`, `operator` y `value`.
- El backend traduce ese lenguaje a consultas SQL con parámetros (sin concatenar texto).
- Cada entidad decide qué campos expone.

Lo común (cómo se interpreta `eq`, `between`, `in`...) se centraliza en un trait. Lo específico (qué campos se pueden filtrar) se queda en cada entidad.

**Request**

```http
POST /api/v1/auth/cars/filter
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "filters": [
    { "field": "status", "operator": "eq", "value": "sold" },
    { "field": "brand", "operator": "ilike", "value": "toy" },
    { "field": "price_mxn", "operator": "between", "value": [200000, 400000] }
  ],
  "order_by": { "column": "price_mxn", "direction": "desc" },
  "pagination": { "page": 1, "limit": 20 }
}
```

Todas las claves son opcionales. Los filtros se combinan con `AND`.

**Respuesta con paginación**

```json
{
  "success": true,
  "data": [ { "id": 6, "brand": "Ford", "...": "..." } ],
  "meta": {
    "total": 2417, "per_page": 20, "current_page": 1, "last_page": 121,
    "from": 1, "to": 20, "is_paginated": true
  }
}
```

**Sin `pagination`** (o con `"pagination": null`) devuelve todos los registros y `meta` solo trae `total` e `is_paginated: false`. Un objeto vacío (`"pagination": {}`) pagina con los valores por defecto (página 1, 15 por página).

## 2. Arquitectura

| Capa | Archivo | Responsabilidad |
| --- | --- | --- |
| Request base | `app/Http/Requests/FilterRequest.php` | Valida la forma de `filters`, `order_by` y `pagination`, y que cada valor sea del tipo del campo |
| Request de la entidad | `app/Http/Requests/Filter[Entity]Request.php` | Declara qué campos se filtran y de qué tipo |
| Trait | `app/Repositories/Concerns/AppliesStructuredFilters.php` | Traduce operadores a query builder, aplica orden y paginación |
| Repository | `app/Repositories/[Entity]Repository.php` | Declara los campos permitidos y arma la consulta |
| Interface | `app/Repositories/[Entity]RepositoryInterface.php` | Contrato del repository |
| Resource | `app/Http/Resources/[Entity]Resource.php` | Decide qué atributos salen en el JSON |
| Respuesta | `app/Http/Responses/FilterResponse.php` | Formato único `success` / `data` / `meta` |
| Controller | `app/Http/Controllers/[Entity]Controller.php` | Une las piezas, sin lógica propia |

`FilterRequest`, el trait y `FilterResponse` son compartidos y **no se tocan** al agregar una entidad.

```text
app/
├── Http/
│   ├── Controllers/[Entity]Controller.php
│   ├── Requests/
│   │   ├── FilterRequest.php               ← compartido (base abstracta)
│   │   └── Filter[Entity]Request.php
│   ├── Resources/[Entity]Resource.php
│   └── Responses/FilterResponse.php        ← compartido
├── Models/[Entity].php
└── Repositories/
    ├── Concerns/AppliesStructuredFilters.php   ← compartido
    ├── [Entity]Repository.php
    └── [Entity]RepositoryInterface.php
```

**Dos barreras de seguridad.** El Request rechaza con `422` lo que no está permitido. Como segunda barrera, el trait lanza `InvalidArgumentException` si algo inválido llega al repository (por ejemplo, un campo que está en el Request y falta en el repository). Un nombre de columna nunca llega al SQL sin haber pasado por la lista blanca.

## 3. Cambios respecto al modelo original

Se mantiene el contrato (`field`/`operator`/`value`), los 15 operadores, `GET` y `POST`, el formato `success`/`data`/`meta` y la paginación opcional. Estos son los ajustes que se hicieron al revisarlo:

| Modelo original | Aquí | Por qué |
| --- | --- | --- |
| El trait ignoraba en silencio campos y operadores inválidos (`continue`) | Lanza `InvalidArgumentException` | Ignorar un filtro devuelve **más** datos de los pedidos y esconde errores de configuración |
| `like` y variantes interpolaban el valor sin escapar | Se escapan `%`, `_` y `\`, y se usa `whereLike()` | Con `%` en el valor, `like` coincidía con todo. `whereLike()` además es portable entre motores |
| El Request repetía todas las reglas por entidad, con los campos como texto (`in:id,name,...`) | `FilterRequest` base; cada entidad solo implementa `filterableFields()` | Menos duplicación y un solo lugar para las reglas |
| Los valores no se validaban según el campo | Cada campo tiene tipo (`integer`, `numeric`, `string`, `date`) y los operadores se restringen por tipo | PostgreSQL es estricto: `id = 'abc'` o `LIKE` sobre un entero devolvían **500**. Ahora devuelven **422** |
| `between`, `in` y `nin` no se validaban en el Request | Se valida forma (arreglo, 2 valores, máximo 100) y tipo de cada elemento | Un `between` mal formado se ignoraba |
| `order_by` solo se validaba en el Request | También se valida en el repository, y se agrega desempate por llave primaria | Sin desempate, ordenar por una columna con valores repetidos duplica u omite filas entre páginas |
| Orden por defecto: `name` | Orden por defecto: `id` (sobrescribible con `getDefaultOrderBy()`) | No todas las tablas tienen `name` |
| Orden y paginación se repetían en cada repository | `applyOrderBy()` y `paginateOrGet()` en el trait; `filter()` queda en 3 líneas | Es la misma razón por la que existe el trait |
| Rama `$perPage === 0` y helper `shouldPaginate()` | Se eliminaron | `limit` mínimo es 1, así que la rama no se alcanzaba, y el controller no usaba el helper |
| `try/catch` en cada controller, devolviendo `$e->getMessage()` | Sin `try/catch` | El `ValidationException` ya lo convierte Laravel en 422, y `getMessage()` en un 500 puede exponer SQL y nombres de tablas |
| El controller leía `$request->input(...)` | El Request expone `filters()`, `orderBy()` y `pagination()` sobre `validated()` | Solo viaja lo validado |
| `FilterResponse` serializaba los modelos completos | `FilterResponse::from($result, Resource::class)` | El Resource decide qué atributos se exponen (convención de la API de este proyecto) |
| `GET /{id}` podía capturar `/filter` | Rutas de `filter` antes de `/{id}` y `whereNumber('id')` | Doble protección contra el conflicto de rutas |

## 4. Implementar una entidad paso a paso

Ejemplo con `Car` (tabla `cars`). Este código se probó contra la base con 2,417 autos. Para otra entidad, sustituye `Car`/`cars` y la lista de campos.

> Usa `php artisan make:*` para crear cada archivo y luego reemplaza su contenido. Crear un modelo nuevo es una decisión de diseño: confírmala antes.

### Paso 1. Modelo

```bash
php artisan make:model Car --no-interaction
```

`app/Models/Car.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model {}
```

Las tablas siguen las convenciones de Laravel (`cars`, `id`, `created_at`/`updated_at`), así que el modelo no necesita `$table` ni `$primaryKey`. Para consultas de lectura no hace falta nada más. Cuando agregues escritura, define `$fillable` con los campos permitidos (no uses `$guarded = []`).

### Paso 2. Interface del repository

```bash
php artisan make:interface Repositories/CarRepositoryInterface --no-interaction
```

`app/Repositories/CarRepositoryInterface.php`

```php
<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CarRepositoryInterface
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
```

### Paso 3. Repository

```bash
php artisan make:class Repositories/CarRepository --no-interaction
```

`app/Repositories/CarRepository.php`

```php
<?php

namespace App\Repositories;

use App\Models\Car;
use App\Repositories\Concerns\AppliesStructuredFilters;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CarRepository implements CarRepositoryInterface
{
    use AppliesStructuredFilters;

    public function __construct(private readonly Car $model) {}

    public function filter(array $filters = [], ?array $orderBy = null, ?array $pagination = null): LengthAwarePaginator|Collection
    {
        $query = $this->applyFilters($this->model->newQuery(), $filters);
        $query = $this->applyOrderBy($query, $orderBy);

        return $this->paginateOrGet($query, $pagination);
    }

    /** @return list<string> */
    protected function getAllowedFilterFields(): array
    {
        return ['id', 'brand', 'model', 'model_year', 'mileage', 'price_mxn', 'status', 'branch_id', 'created_at', 'updated_at'];
    }
}
```

- `getAllowedFilterFields()` es la lista blanca de columnas. **No incluyas** columnas sensibles ni de texto largo.
- Para restringir el orden a menos columnas que el filtro, sobrescribe `getAllowedSortFields()`.
- Para cambiar el orden por defecto, sobrescribe `getDefaultOrderBy()`.

### Paso 4. Request de la entidad

```bash
php artisan make:request FilterCarRequest --no-interaction
```

`app/Http/Requests/FilterCarRequest.php`

```php
<?php

namespace App\Http\Requests;

class FilterCarRequest extends FilterRequest
{
    /** @return array<string, 'string'|'integer'|'numeric'|'date'> */
    protected function filterableFields(): array
    {
        return [
            'id' => 'integer',
            'brand' => 'string',
            'model' => 'string',
            'model_year' => 'integer',
            'mileage' => 'integer',
            'price_mxn' => 'numeric',
            'status' => 'string',
            'branch_id' => 'integer',
            'created_at' => 'date',
            'updated_at' => 'date',
        ];
    }
}
```

Los tipos válidos son `string`, `integer`, `numeric` y `date`. Las claves deben ser las mismas que `getAllowedFilterFields()`. Si un campo está en un lado y no en el otro, el trait lanzará `InvalidArgumentException` en lugar de ignorarlo.

### Paso 5. Resource

```bash
php artisan make:resource CarResource --no-interaction
```

`app/Http/Resources/CarResource.php`

```php
<?php

namespace App\Http\Resources;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Car */
class CarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'model_year' => $this->model_year,
            'mileage' => $this->mileage,
            'price_mxn' => $this->price_mxn,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### Paso 6. Controller

```bash
php artisan make:controller CarController --no-interaction
```

`app/Http/Controllers/CarController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterCarRequest;
use App\Http\Resources\CarResource;
use App\Http\Responses\FilterResponse;
use App\Repositories\CarRepositoryInterface;
use Illuminate\Http\JsonResponse;

class CarController extends Controller
{
    public function __construct(private readonly CarRepositoryInterface $cars) {}

    /** Filtrado avanzado de autos. */
    public function filter(FilterCarRequest $request): JsonResponse
    {
        $result = $this->cars->filter($request->filters(), $request->orderBy(), $request->pagination());

        return response()->json(FilterResponse::from($result, CarResource::class)->toResponse());
    }
}
```

Si la entidad ya tiene controller, solo agrega el método `filter()` y el repository al constructor.

### Paso 7. Vincular interface e implementación

En `app/Providers/AppServiceProvider.php`, una línea por entidad:

```php
public array $bindings = [
    \App\Repositories\CarRepositoryInterface::class => \App\Repositories\CarRepository::class,
];
```

### Paso 8. Rutas

En `routes/api.php`, dentro del grupo `auth:api` que ya existe (las URL quedan `/api/v1/auth/cars/filter`). Es la misma convención de `branches`: cada ruta lleva el permiso de su acción.

```php
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::middleware('auth:api')->group(function () {
            // ... rutas existentes (me, menu, logout, branches...)

            Route::prefix('cars')->group(function () {
                // Las rutas de filtro van antes de cualquier /{id}.
                Route::get('/filter', [CarController::class, 'filter'])->middleware('permission:cars.view_any');
                Route::post('/filter', [CarController::class, 'filter'])->middleware('permission:cars.view_any');

                // Cuando existan las rutas con id, restringe el parámetro:
                // Route::get('/{id}', [CarController::class, 'show'])->whereNumber('id')->middleware('permission:cars.view');
            });
        });
    });
});
```

Filtrar es una consulta de listado, así que usa el permiso `view_any`. Ese permiso debe existir: agrega `'cars' => ['view_any', 'view', 'create', 'update', 'delete']` a `RESOURCES` en `database/seeders/PermissionsSeeder.php` y ejecuta `php artisan db:seed --class=PermissionsSeeder` (el rol `admin` lo recibe solo).

### Paso 9. Formato y verificación

```bash
vendor/bin/pint
php artisan route:list --path=cars
```

Prueba los casos de la sección 6.

## 5. Operadores y tipos de campo

| Operador | Significado | `value` | Ejemplo |
| --- | --- | --- | --- |
| `eq` | Igual | escalar | `{"field":"id","operator":"eq","value":5}` |
| `ne` | Distinto | escalar | `{"field":"status","operator":"ne","value":"sold"}` |
| `gt` | Mayor que | escalar | `{"field":"mileage","operator":"gt","value":50000}` |
| `gte` | Mayor o igual | escalar | `{"field":"created_at","operator":"gte","value":"2024-01-01"}` |
| `lt` | Menor que | escalar | `{"field":"price_mxn","operator":"lt","value":200000}` |
| `lte` | Menor o igual | escalar | `{"field":"model_year","operator":"lte","value":2020}` |
| `like` | Contiene, distingue mayúsculas | texto | `{"field":"brand","operator":"like","value":"ssa"}` |
| `ilike` | Contiene, ignora mayúsculas | texto | `{"field":"brand","operator":"ilike","value":"NISSAN"}` |
| `startsWith` | Empieza con (distingue mayúsculas) | texto | `{"field":"model","operator":"startsWith","value":"C"}` |
| `endsWith` | Termina con (distingue mayúsculas) | texto | `{"field":"model","operator":"endsWith","value":"a"}` |
| `in` | Está en la lista (máx. 100) | arreglo | `{"field":"brand","operator":"in","value":["Kia","Mazda"]}` |
| `nin` | No está en la lista (máx. 100) | arreglo | `{"field":"brand","operator":"nin","value":["Kia"]}` |
| `between` | Entre dos valores, inclusivo | arreglo de 2 | `{"field":"price_mxn","operator":"between","value":[300000,400000]}` |
| `null` | Es nulo | no lleva | `{"field":"branch_id","operator":"null"}` |
| `notnull` | No es nulo | no lleva | `{"field":"branch_id","operator":"notnull"}` |

**Qué operadores acepta cada tipo de campo**

| Tipo | Validación del valor | Operadores |
| --- | --- | --- |
| `string` | texto, máximo 255 caracteres | Todos |
| `integer` | entero | `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `in`, `nin`, `null`, `notnull`, `between` |
| `numeric` | número | los mismos que `integer` |
| `date` | fecha válida (`2024-01-01` o con hora) | los mismos que `integer` |

## 6. Ejemplos de uso

Obtén un token (ver README, sección "Autenticación y roles"):

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -d email=admin@agencia.test --data-urlencode "password=$ADMIN_PASSWORD" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
```

**Con paginación**

```bash
curl -s -X POST http://localhost:8000/api/v1/auth/cars/filter \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"filters":[{"field":"status","operator":"eq","value":"new"}],
       "order_by":{"column":"price_mxn","direction":"desc"},
       "pagination":{"page":1,"limit":10}}'
```

**Sin paginación (todos los registros)**: omite `pagination`.

```bash
curl -s -X POST http://localhost:8000/api/v1/auth/cars/filter \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"filters":[{"field":"brand","operator":"in","value":["Kia","Mazda"]}]}'
```

**Por `GET`** (útil para enlaces y pruebas rápidas; los filtros van en la URL como arreglos):

```bash
curl -s -G http://localhost:8000/api/v1/auth/cars/filter \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  --data-urlencode "filters[0][field]=status" --data-urlencode "filters[0][operator]=eq" \
  --data-urlencode "filters[0][value]=new" \
  --data-urlencode "order_by[column]=id" --data-urlencode "order_by[direction]=desc"
```

## 7. Errores

| HTTP | Cuándo | Ejemplo de causa |
| --- | --- | --- |
| `401` | Falta el token o no es válido | Sin cabecera `Authorization` |
| `403` | El usuario no tiene el permiso de la ruta | Usuario sin `cars.view_any` |
| `422` | La petición no cumple el contrato | Campo u operador no permitido, valor del tipo equivocado, `between` sin dos valores, `limit` mayor a 100, más de 20 filtros |
| `500` | Error de configuración | El repository recibió algo que el Request dejó pasar (campos desalineados) |

Ejemplo de `422`:

```json
{
  "message": "El valor abc no es válido para un campo de tipo integer.",
  "errors": { "filters.0.value": ["El valor abc no es válido para un campo de tipo integer."] }
}
```

## 8. Checklist y campos sugeridos por entidad

### Checklist (copiar por entidad)

- [ ] Modelo (confirmado)
- [ ] `[Entity]RepositoryInterface` con `filter()`
- [ ] `[Entity]Repository` con el trait y `getAllowedFilterFields()`
- [ ] `Filter[Entity]Request extends FilterRequest` con `filterableFields()` (mismas claves que el repository)
- [ ] `[Entity]Resource`
- [ ] Método `filter()` en el controller
- [ ] Binding en `AppServiceProvider::$bindings`
- [ ] Permisos del recurso en `PermissionsSeeder::RESOURCES`
- [ ] Rutas `GET` y `POST` de `filter` **antes** de `/{id}`, dentro del grupo `auth:api` y con `permission:<recurso>.view_any`
- [ ] Probado sin `pagination` (devuelve todos)
- [ ] Probado con `pagination` (pagina)
- [ ] Probado un valor inválido (devuelve 422)
- [ ] Probado un usuario sin el permiso (devuelve 403) y sin token (devuelve 401)
- [ ] `/filter` no es capturado por `show`
- [ ] `vendor/bin/pint` sin cambios pendientes

### Estado por entidad

| Entidad | Tabla | Estado |
| --- | --- | --- |
| Car | `cars` | Plantilla probada (no está instalada en el proyecto) |
| Branch | `branches` | Pendiente |
| Customer | `customers` | Pendiente |
| Employee | `employees` | Pendiente |
| Sale | `sales` | Pendiente |
| ServiceType | `service_types` | Pendiente |
| ServiceOrder | `service_orders` | Pendiente |
| ServiceOrderItem | `service_order_items` | Pendiente |

### Campos sugeridos (`filterableFields()`)

| Tabla | Campos y tipos |
| --- | --- |
| `branches` | `id` integer, `name` string, `city` string, `address` string, `created_at` date, `updated_at` date |
| `customers` | `id` integer, `first_name` string, `last_name` string, `email` string, `phone` string, `city` string, `registration_date` date, `created_at` date, `updated_at` date |
| `employees` | `id` integer, `first_name` string, `last_name` string, `job_title` string, `branch_id` integer, `created_at` date, `updated_at` date |
| `cars` | `id` integer, `brand` string, `model` string, `model_year` integer, `mileage` integer, `price_mxn` numeric, `status` string, `branch_id` integer, `created_at` date, `updated_at` date |
| `sales` | `id` integer, `car_id` integer, `customer_id` integer, `employee_id` integer, `sale_date` date, `final_price_mxn` numeric, `created_at` date, `updated_at` date |
| `service_types` | `id` integer, `name` string, `base_price_mxn` numeric, `created_at` date, `updated_at` date |
| `service_orders` | `id` integer, `car_id` integer, `customer_id` integer, `employee_id` integer, `check_in_date` date, `delivery_date` date, `total_mxn` numeric, `created_at` date, `updated_at` date |
| `service_order_items` | `id` integer, `service_order_id` integer, `service_type_id` integer, `applied_cost_mxn` numeric, `created_at` date, `updated_at` date |
| `users` | Solo `id`, `name`, `email`, `created_at`. Nunca `password`, `remember_token` ni columnas internas. Exponerlo requiere decidir antes qué roles pueden listar usuarios |

## 9. Consideraciones

- **Seguridad.** Los nombres de columna solo salen de listas blancas, y los valores siempre van como parámetros. No agregues columnas sensibles a `getAllowedFilterFields()`.
- **Dos listas que mantener alineadas.** `getAllowedFilterFields()` (repository) y `filterableFields()` (Request) deben tener las mismas claves. Si se desalinean, el trait lanza una excepción en lugar de ignorar el filtro, así que el error se ve en la primera prueba.
- **Sin paginación se devuelve todo.** Es útil para catálogos pequeños (`branches`, `service_types`). En tablas grandes (`sales`, `service_order_items`) conviene exigir `pagination` en el Request de esa entidad.
- **Límites.** Máximo 100 registros por página, 20 filtros por petición y 100 valores por lista.
- **Solo columnas propias.** No se filtra por campos de tablas relacionadas (por ejemplo, ventas por ciudad del cliente). Se filtra por la llave foránea (`customer_id`). Si se necesita filtrar por relación, hay que extender el trait de forma deliberada.
- **Índices.** Crea índices en las columnas que se filtren con frecuencia (por ejemplo `cars(brand)`, `sales(sale_date)`). `ilike`, `like` y `endsWith` con comodín al inicio no aprovechan un índice normal.
- **Mayúsculas y acentos.** `ilike` ignora mayúsculas pero no acentos (`Mexico` no encuentra `México`).
- **Operadores y `like`.** `like`, `startsWith` y `endsWith` distinguen mayúsculas, como en el modelo original. Usa `ilike` para búsquedas de texto libre.
- **Endpoints legacy.** No elimines `/all` ni `/search` hasta completar la migración de cada entidad.
- **Pruebas.** Cuando agregues tests, cubre por entidad: filtro simple, combinación de filtros, paginación, valor inválido (422) y acceso sin token (401).
