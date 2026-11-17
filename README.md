# Agencia API

API REST de una agencia de autos con venta de vehículos y taller de servicio. Laravel 13 + PostgreSQL 17, con Nginx, Redis y Mailhog en Docker.

## Estructura

| Ruta | Contenido |
|---|---|
| `backend/` | Aplicación Laravel (código, migraciones, rutas, tests) |
| `docker/` | Dockerfile de PHP-FPM, config de Nginx, init de Postgres |
| `database/schema/` | DDL puro (`01_schema.sql`) y seed manual (`02_seed_manual.sql`) |
| `database/seeds-python/` | Seed masivo determinista (`seed.py`) y generador de ruido para el ETL |
| `docs/` | Modelo de datos y diagrama ER (`er.md`), decisiones de arquitectura (`decisiones.md`) |
| `openapi.json` | Especificación OpenAPI, se commitea |

## Servicios

Postgres, Redis y Mailhog solo escuchan en `127.0.0.1` (ver [`decisiones.md`](docs/decisiones.md), decisión 9).

| Servicio | URL / puerto en el host |
|---|---|
| API (Nginx) | http://localhost:8000/api/ping |
| PostgreSQL | `127.0.0.1:5432` (db `agencia`, user `agencia`, pass `secret`) |
| Redis | `127.0.0.1:6379` |
| Mailhog (bandeja web) | http://localhost:8025 |
| Mailhog (SMTP) | `127.0.0.1:1025` |

## Puesta en marcha

```bash
cp .env.example .env          # opcional: ajusta UID/GID (id -u, id -g) y puertos
docker compose up -d --build
docker compose exec app php artisan migrate
curl http://localhost:8000/api/ping
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

Autenticación con JWT ([`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth)) y roles con [`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission).

| Método y ruta | Auth | Descripción |
|---|---|---|
| `POST /api/auth/login` | No | Recibe `email` y `password`, devuelve el token. Límite de 5 intentos por minuto por email e IP |
| `GET /api/auth/me` | Bearer | Usuario autenticado con sus roles y permisos |
| `POST /api/auth/refresh` | Bearer | Emite un token nuevo e invalida el anterior. Acepta un token expirado dentro de 14 días |
| `POST /api/auth/logout` | Bearer | Invalida el token actual |

**Roles:** `admin`, `manager`, `seller`, `service_advisor` y `mechanic` (los cuatro últimos coinciden con `employees.job_title`). Por ahora no hay permisos finos: se agregan cuando existan los endpoints que los necesiten. Para proteger una ruta: `->middleware(['auth:api', 'role:admin|manager'])`.

**Primer administrador:** define `ADMIN_EMAIL` y `ADMIN_PASSWORD` en `backend/.env` y ejecuta el seeder. Sin `ADMIN_PASSWORD` no se crea ningún usuario.

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed      # crea los roles y el administrador

TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -d email=admin@agencia.test --data-urlencode "password=$ADMIN_PASSWORD" | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
curl http://localhost:8000/api/auth/me -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

El token dura 60 minutos (`JWT_TTL`). `JWT_SECRET` se genera con `php artisan jwt:secret`; el contenedor lo hace solo al crear `backend/.env`. Nunca lo subas a git. En producción usa `APP_DEBUG=false`.

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
