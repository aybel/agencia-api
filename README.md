# Agencia API

API REST de una agencia de autos con venta de vehículos y taller de servicio. Laravel 13 + PostgreSQL 17, con Nginx, Redis y Mailhog en Docker.

## Estructura

| Ruta | Contenido |
|---|---|
| `backend/` | Aplicación Laravel (código, migraciones, rutas, tests) |
| `docker/` | Dockerfile de PHP-FPM, config de Nginx, init de Postgres |
| `database/schema/` | DDL puro (`01_schema.sql`) y seed manual (`02_seed_manual.sql`) |
| `database/seeds-python/` | Seed masivo determinista (`seed.py`) y generador de ruido para el ETL |
| `docs/` | Diagrama ER completo (`diagrama-er.md`), modelo de datos del negocio (`er.md`), decisiones de arquitectura (`decisiones.md`), manual de filtros avanzados (`filtros-avanzados.md`) |
| `openapi.json` | Especificación OpenAPI, se commitea |

## Servicios

Postgres, Redis y Mailhog solo escuchan en `127.0.0.1` (ver [`decisiones.md`](docs/decisiones.md), decisión 9).

| Servicio | URL / puerto en el host |
|---|---|
| API (Nginx) | http://localhost:8000/api/v1/ping |
| PostgreSQL | `127.0.0.1:5432` (db `agencia`, user `agencia`, pass `secret`) |
| Redis | `127.0.0.1:6379` |
| Mailhog (bandeja web) | http://localhost:8025 |
| Mailhog (SMTP) | `127.0.0.1:1025` |

## Puesta en marcha

```bash
cp .env.example .env          # opcional: ajusta UID/GID (id -u, id -g) y puertos
docker compose up -d --build
docker compose exec app php artisan migrate
curl http://localhost:8000/api/v1/ping
```

En el primer arranque, el contenedor `app` ejecuta `composer install` y crea `backend/.env` si faltan.

## Cargar los datos

El modelo y las reglas de los datos están en [`docs/er.md`](docs/er.md). Con las migraciones aplicadas:

```bash
# 1. Seed manual: conjunto pequeño (vacía las tablas y reinicia los ids)
docker compose exec -T postgres psql -U agencia -d agencia -v ON_ERROR_STOP=1 < database/schema/02_seed_manual.sql

# 2. Seed masivo: 2,012 ventas y 4,008 órdenes de servicio en total
cd database/seeds-python
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
.venv/bin/python seed.py --dsn postgresql://agencia:secret@localhost:5432/agencia
```

Detalles, parámetros y reglas de generación en [`database/seeds-python/README.md`](database/seeds-python/README.md).

## Autenticación y roles

Todas las rutas van versionadas bajo `/api/v1`, incluida la comprobación de vida (`GET /api/v1/ping`). Los recursos de negocio protegidos se publican en `/api/v1/auth/<recurso>`: dentro del grupo `auth:api`, con el middleware `permission:` de cada ruta.

Autenticación con JWT ([`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth)) y roles con [`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission).

| Método y ruta | Auth | Descripción |
|---|---|---|
| `POST /api/v1/auth/login` | No | Recibe `email` y `password`, devuelve el token. Límite de 5 intentos por minuto por email e IP |
| `GET /api/v1/auth/me` | Bearer | Usuario autenticado con sus roles y permisos |
| `POST /api/v1/auth/refresh` | Bearer | Emite un token nuevo e invalida el anterior. Acepta un token expirado dentro de 14 días |
| `GET /api/v1/auth/menu` | Bearer | Menú del usuario, filtrado por sus permisos (ver [Menú del usuario](#menú-del-usuario)) |
| `POST /api/v1/auth/logout` | Bearer | Invalida el token actual |

**Roles:** `admin`, `manager`, `seller`, `service_advisor` y `mechanic` (los cuatro últimos coinciden con `employees.job_title`). Los permisos de cada recurso y el menú se describen en [Permisos y menú](#permisos-y-menú).

**Primer administrador:** define `ADMIN_EMAIL` y `ADMIN_PASSWORD` en `backend/.env` y ejecuta el seeder. Sin `ADMIN_PASSWORD` no se crea ningún usuario.

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed      # crea roles, menú, permisos y el administrador

TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -d email=admin@agencia.test --data-urlencode "password=$ADMIN_PASSWORD" | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
curl http://localhost:8000/api/v1/auth/me -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

El token dura 60 minutos (`JWT_TTL`). `JWT_SECRET` se genera con `php artisan jwt:secret`; el contenedor lo hace solo al crear `backend/.env`. Nunca lo subas a git. En producción usa `APP_DEBUG=false`.

## Permisos y menú

La base de datos responde dos preguntas: **qué puede hacer** un usuario (roles y permisos) y **qué opciones ve en el menú** (`menu_items`). Ambas se unen por la tabla `permissions`: una misma cadena de permiso protege la ruta de la API y decide si aparece el ítem del menú.

### Cómo se unen

```mermaid
erDiagram
    USERS ||--o{ MODEL_HAS_ROLES : "tiene"
    ROLES ||--o{ MODEL_HAS_ROLES : "se asigna"
    ROLES ||--o{ ROLE_HAS_PERMISSIONS : "agrupa"
    PERMISSIONS ||--o{ ROLE_HAS_PERMISSIONS : "pertenece a"
    USERS ||--o{ MODEL_HAS_PERMISSIONS : "permiso directo"
    PERMISSIONS ||--o{ MODEL_HAS_PERMISSIONS : "se asigna"
    PERMISSIONS |o--o{ MENU_ITEMS : "controla"
    MENU_ITEMS |o--o{ MENU_ITEMS : "padre de"

    PERMISSIONS {
        bigint id PK
        varchar name "branches.view_any"
        varchar guard_name "api"
    }
    MENU_ITEMS {
        bigint id PK
        varchar semantic_key UK
        varchar display_name
        varchar route_name
        varchar semantic_type "header, module, group, link"
        bigint permission_id FK
        bigint parent_id FK
        int order
        boolean is_active
    }
```

1. Un usuario tiene **roles** (`model_has_roles`) y puede tener **permisos directos** (`model_has_permissions`).
2. Un rol agrupa **permisos** (`role_has_permissions`).
3. Los permisos efectivos de un usuario son los de sus roles más los directos (`$user->getAllPermissions()`).
4. Un ítem del menú apunta a un permiso con `menu_items.permission_id`. Sin permiso, el ítem es visible para cualquier usuario.
5. La ruta de la API usa **el mismo nombre** de permiso: `->middleware('permission:branches.view_any')`. Un usuario sin ese permiso no ve el ítem y, si llama a la ruta, recibe `403`.

> El menú es una comodidad de la interfaz. La seguridad real está en el middleware de cada ruta: ocultar un ítem no protege el endpoint.

### Permisos

Los permisos se crean con el guard `api` y se nombran `<recurso>.<acción>`:

| Acción | Para qué | Ruta que protege (relativa al prefijo `branches`) |
|---|---|---|
| `view_any` | Listar, buscar y filtrar | `GET /`, `GET /search`, `GET /filter` |
| `view` | Ver un registro | `GET /{id}` |
| `create` | Crear | `POST /` |
| `update` | Actualizar | `PUT /{id}` |
| `delete` | Borrar | `DELETE /{id}` |

Por ahora solo existen los de `branches`. `PermissionsSeeder` los crea y le asigna **todos** los permisos existentes al rol `admin`. Los demás roles (`manager`, `seller`, `service_advisor`, `mechanic`) aún no tienen permisos.

### Tabla `menu_items`

| Columna | Qué guarda |
|---|---|
| `semantic_key` | Identificador único y estable (por ejemplo `sales.list`). El seeder lo usa para actualizar sin duplicar |
| `display_name` | Texto que se muestra en el menú |
| `route_name` | Nombre de la ruta **del frontend**, no una URL |
| `semantic_icon` | Icono semántico (`dashboard`, `sale`...). El frontend decide cómo dibujarlo |
| `semantic_type` | `header` (separador de sección), `module`, `group` o `link`. Un `CHECK` impide otros valores |
| `permission_id` | Permiso que da acceso. Si se borra el permiso, queda en `NULL` (`ON DELETE SET NULL`) y el ítem pasa a ser visible para todos |
| `parent_id` | Ítem padre. Si se borra el padre, se borran sus hijos (`ON DELETE CASCADE`) |
| `order` | Posición entre hermanos |
| `metadata` | JSON libre para datos extra del frontend |
| `is_active` | `false` oculta el ítem sin borrarlo |

### Seeders

`php artisan db:seed` los ejecuta en este orden. Todos son idempotentes: se pueden repetir sin duplicar datos.

| Orden | Seeder | Qué hace |
|---|---|---|
| 1 | `RolesSeeder` | Crea los 5 roles |
| 2 | `MenuItemSeeder` | Crea o actualiza el menú desde el arreglo `menu()` y **crea los permisos** que ese menú menciona |
| 3 | `PermissionsSeeder` | Crea los permisos de `RESOURCES` y le asigna a `admin` todos los permisos existentes |
| 4 | `AdminUserSeeder` | Crea el administrador desde `ADMIN_EMAIL` y `ADMIN_PASSWORD` |

`PermissionsSeeder` va después del menú para que `admin` reciba también los permisos que crea el menú. Para ejecutar uno solo: `php artisan db:seed --class=PermissionsSeeder`.

Los seeders **no borran**: si quitas un ítem del arreglo o un permiso de `RESOURCES`, la fila ya creada sigue en la base y hay que eliminarla a mano. Los ítems del menú que trae `MenuItemSeeder` hoy (Ventas, Taller, Administración) son ejemplos: reemplázalos con tus opciones reales.

### Agregar un recurso al sistema

Ejemplo para `customers`:

1. **Permisos.** En `database/seeders/PermissionsSeeder.php`, agrega una línea a `RESOURCES`:

   ```php
   'customers' => ['view_any', 'view', 'create', 'update', 'delete'],
   ```

2. **Rutas.** Protege cada ruta con su permiso:

   ```php
   Route::get('/', [CustomerController::class, 'index'])->middleware('permission:customers.view_any');
   ```

3. **Menú.** En `MenuItemSeeder::menu()`, agrega el ítem con el mismo permiso. Un ítem puede ir dentro de `children` de un módulo:

   ```php
   ['key' => 'customers.list', 'name' => 'Clientes', 'route' => 'customers.index', 'permission' => 'customers.view_any'],
   ```

4. **Aplicar.** `docker compose exec app php artisan db:seed`

Claves que acepta cada ítem del menú: `key` y `name` (obligatorias), `type`, `route`, `icon`, `permission`, `order`, `metadata`, `active` y `children`.

### Menú del usuario

`GET /api/v1/auth/menu` (requiere `Authorization: Bearer <token>`) devuelve el menú que el usuario puede ver, como árbol:

```json
{
  "data": [
    { "id": 1, "key": "dashboard", "name": "Dashboard", "type": "link", "route": "dashboard",
      "icon": "dashboard", "order": 10, "metadata": null, "children": [] },
    { "id": 2, "key": "section.operation", "name": "Operación", "type": "header", "route": null,
      "icon": null, "order": 20, "metadata": null, "children": [] },
    { "id": 3, "key": "sales", "name": "Ventas", "type": "module", "route": null,
      "icon": "sale", "order": 30, "metadata": null,
      "children": [
        { "id": 4, "key": "sales.list", "name": "Listado de ventas", "type": "link",
          "route": "sales.index", "icon": null, "order": 10, "metadata": null, "children": [] }
      ] }
  ]
}
```

El token se envía en cada petición, y sin él la respuesta es `401`. El permiso de cada ítem **no** va en la respuesta: el frontend solo recibe lo que puede mostrar.

**Reglas que aplica el backend** (`MenuItemRepository::getTreeForUser()`):

1. Un ítem se muestra si está activo (`is_active`) y no tiene permiso, o el usuario tiene ese permiso.
2. Si un ítem no se muestra, tampoco se muestran sus hijos.
3. Un `module` o `group` sin hijos visibles y sin `route_name` se omite.
4. Un `header` se omite si no tiene ningún ítem debajo antes del siguiente `header`.
5. Los hijos vienen ordenados por `order`, con cualquier nivel de anidado.

Ejemplo con los ítems de muestra: un usuario sin permisos solo ve `dashboard`; con `sales.view` ve además `Operación → Ventas → Listado de ventas`; con `users.view`, `Administración → Administración → Usuarios`.

Cada ítem de menú es independiente del frontend: `route` es el nombre de una ruta del frontend, no una URL, e `icon` es un nombre semántico que el frontend traduce a un icono.

Para armar el menú desde código, `MenuItem` también trae los scopes `active()`, `root()` y `forUser($user)`. Si los usas directamente, filtra también los hijos con `forUser` y poda los encabezados vacíos, porque el scope por sí solo no lo hace.

## Acceder a la base de datos con HeidiSQL

Crea una sesión nueva (**Nuevo** en el Administrador de sesiones) con estos datos:

| Campo | Valor |
|---|---|
| Tipo de red | `PostgreSQL (TCP/IP)` |
| Librería | La que HeidiSQL propone por defecto (`libpq-*.dll`) |
| Nombre de host / IP | `127.0.0.1` |
| Usuario | `agencia` |
| Contraseña | `secret` |
| Puerto | `5432` |
| Base de datos | `agencia` |

Pulsa **Abrir**. Las tablas están en el esquema `public` de la base `agencia`.

Notas:

- **Si `public` aparece con 0 tablas,** la sesión está conectada a la base `postgres` (la vacía por defecto) y no a `agencia`. Revisa el campo **Base de datos** en la configuración de la sesión.
- **Una base por sesión.** HeidiSQL se conecta a una sola base de Postgres a la vez. Para ver la base de pruebas de PHPUnit, crea otra sesión con la base `agencia_test`.
- **Si cambiaste las credenciales o el puerto** en `.env` (`DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`, `DB_FORWARD_PORT`), usa esos valores.
- **HeidiSQL en otra máquina, VM o contenedor:** `127.0.0.1` solo acepta conexiones desde este equipo. Pon `BIND_ADDRESS=0.0.0.0` en `.env`, ejecuta `docker compose up -d`, usa la IP de este equipo como host y **cambia la contraseña** de `DB_PASSWORD` antes, porque `secret` es solo para desarrollo.
- **Error de autenticación:** Postgres 17 usa `scram-sha-256`. Si HeidiSQL dice que el método no está soportado, elige en el campo **Librería** la versión de `libpq` más reciente de la lista.
- **Si no conecta,** comprueba que el contenedor esté sano con `docker compose ps` (`postgres` debe aparecer `healthy`).

## Comandos útiles

```bash
docker compose exec app php artisan <comando>
docker compose exec app composer <comando>
docker compose exec app php artisan test        # usa la base agencia_test
docker compose exec app vendor/bin/pint         # formato de código
docker compose exec postgres psql -U agencia -d agencia   # consola SQL
docker compose logs -f app nginx
docker compose down -v                          # borra también los volúmenes (datos de Postgres y Redis)
```

La base `agencia_test` se crea solo al inicializar el volumen de Postgres. Si el volumen ya existía, créala con:

```bash
docker compose exec postgres createdb -U agencia agencia_test
```
