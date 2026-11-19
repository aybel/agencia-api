# Decisiones de arquitectura

Formato corto: contexto, decisión, consecuencia.

## 1. PostgreSQL como base de datos
- **Decisión:** PostgreSQL 17.
- **Por qué:** soporta `CHECK`, tipos estrictos y restricciones que mantienen la integridad en la base, no solo en la aplicación.
- **Consecuencia:** PHPUnit corre contra Postgres (`agencia_test`), no SQLite, para que las restricciones se prueben de verdad.

## 2. `CHECK` en columnas de estado
- **Decisión:** `cars.status` es `VARCHAR(10) NOT NULL DEFAULT 'new'` con `CHECK (status IN ('new', 'used', 'sold'))`, en lugar de un `ENUM` nativo (el modelo original en MySQL usaba `ENUM`).
- **Por qué:** agregar o quitar un valor es un `ALTER TABLE` simple; modificar un `ENUM` nativo es más rígido.
- **Cambio respecto a MySQL:** la columna pasa a `NOT NULL`. El `ENUM` original aceptaba `NULL`, pero un auto sin estado no tiene significado y ningún dato lo usa.

## 3. Redis para cache, sesiones y colas
- **Decisión:** `CACHE_STORE`, `SESSION_DRIVER` y `QUEUE_CONNECTION` usan Redis (extensión `phpredis`).
- **Consecuencia:** los tests usan drivers `array`/`sync`, así que no dependen de Redis para pasar.

## 4. Mailhog para correo en desarrollo
- **Decisión:** SMTP a `mailhog:1025`, bandeja en http://localhost:8025.
- **Nota:** Mailhog está sin mantenimiento activo. Si da problemas, Mailpit es un reemplazo compatible por SMTP.

## 5. Laravel en `backend/` dentro de un monorepo
- **Decisión:** el repo contiene backend, infraestructura Docker, esquema SQL y seeds en Python.
- **Consecuencia:** `database/schema/01_schema.sql` y las migraciones describen el mismo esquema y deben mantenerse alineados. Se comprobó comparando los volcados de `pg_dump -s` de ambos: son idénticos (los nombres de restricciones de `01_schema.sql` imitan los de Laravel para lograrlo).

## 6. Seeds masivos en Python, deterministas
- **Decisión:** `database/seeds-python/seed.py` no usa generador aleatorio: cada valor sale de un `CRC32` sobre un texto fijo. Misma ejecución, mismos datos.
- **Por qué:** los datos son reproducibles para pruebas y para el ETL posterior (`noise.py`).

## 7. Del modelo MySQL en español a PostgreSQL con convenciones de Laravel
- **Decisión:** el modelo original (8 tablas en español, llaves `id_<tabla>`) se migró a PostgreSQL y se renombró al inglés con las convenciones de Laravel: tablas en plural (`service_orders`), llave primaria `id`, llaves foráneas `<singular>_id`, y `created_at`/`updated_at` en todas. La tabla de equivalencias está en [`er.md`](er.md).
- **Por qué:** Eloquent deduce tabla, llave y relaciones por convención. Con los nombres en español cada modelo necesitaba `$table`, `$primaryKey` y `$timestamps = false`, y cada relación pedía indicar su llave a mano. El cambio se hizo antes de crear modelos, controladores y el ETL, que es cuando es más barato.
- **Valores traducidos:** los códigos pasaron a inglés (`cars.status`: `new`, `used`, `sold`; `employees.job_title`: `seller`, `manager`, `service_advisor`, `mechanic`). Los textos que son datos (nombres, ciudades, servicios) siguen en español. La interfaz traduce los códigos para mostrarlos.
- **Tipos:** `INT AUTO_INCREMENT` pasó a `BIGSERIAL` (lo que genera `$table->id()` en Laravel), `DECIMAL` a `NUMERIC`, `ENUM` a `VARCHAR` + `CHECK`. La moneda pasó de `mxp` a `mxn`, el código ISO vigente.
- **Verificación:** tras renombrar se recargaron los seeds y se compararon fila por fila contra los datos anteriores: las 16,336 filas son idénticas, salvo por los nombres y valores traducidos.
- **Consecuencia:** los seeds insertan ids explícitos, así que después de cargarlos se alinean las secuencias con `setval` para que un `INSERT` sin id no choque.

## 8. Seed masivo portado de SQL a Python
- **Decisión:** `02_seed_masivo.sql` (CTEs recursivas y `CRC32` de MySQL) se reescribió como `seed.py`, manteniendo `CRC32` (`zlib.crc32`) y la misma aritmética decimal.
- **Por qué:** PostgreSQL no tiene `CRC32` ni los mismos operadores, y un port en SQL daría datos distintos. En Python el resultado es idéntico y verificable.
- **Detalle:** el `CRC32` se calcula sobre textos fijos que incluyen el puesto en español (`'Vendedor'`, `'Mecánico'`…). Esas etiquetas se conservan solo como semilla del hash, aunque en la base se guarde el código en inglés. Así los nombres generados no cambian.
- **Consecuencia:** el seed masivo necesita Python con `psycopg`; el seed manual solo necesita `psql`.

## 9. Puertos de servicios solo en localhost
- **Decisión:** Postgres, Redis y Mailhog se publican en `127.0.0.1` (variable `BIND_ADDRESS`).
- **Por qué:** Redis no tiene contraseña y Postgres usa una de desarrollo. Publicarlos en `0.0.0.0` los expondría a toda la red local.
- **Consecuencia:** para entrar desde otra máquina hay que poner `BIND_ADDRESS=0.0.0.0` y cambiar las contraseñas.

## 10. Autenticación JWT y roles con Spatie
- **Decisión:** `php-open-source-saver/jwt-auth` para los tokens y `spatie/laravel-permission` para roles y permisos. El guard `api` (driver `jwt`) es el guard por defecto, porque la aplicación no usa sesiones.
- **Por qué este paquete JWT:** `tymon/jwt-auth` 2.3.1 y el fork `php-open-source-saver/jwt-auth` 2.10 soportan Laravel 13. Se eligió el fork por sus releases más frecuentes y la misma API.
- **Roles:** `admin`, `manager`, `seller`, `service_advisor`, `mechanic`, creados con el guard `api`. `User` fija `$guard_name = 'api'` porque los guards `web` y `api` comparten el mismo provider y Spatie no podría elegir solo.
- **Roles fuera del token:** el JWT lleva solo el id del usuario. Si los roles fueran un claim, un cambio de rol no se reflejaría hasta que el token expirara.
- **Logout y refresh:** el token se invalida con una lista negra guardada en Redis (cache). `refresh` está fuera de `auth:api` para aceptar un token expirado dentro de `JWT_REFRESH_TTL` (14 días).
- **Sin registro público:** los usuarios los crea un administrador. No hay contraseña por defecto: `AdminUserSeeder` solo crea el administrador si `ADMIN_PASSWORD` está definido.
- **Pendiente:** un usuario todavía no se vincula a un `employee`, y no hay permisos finos ni endpoints para administrar usuarios.

## 11. Endpoint `/filter` estructurado por entidad
- **Decisión:** cada entidad expone `GET|POST /api/v1/auth/<entidades>/filter`, que recibe filtros como `{field, operator, value}`, orden y paginación opcional. La traducción de operadores a SQL vive en un trait compartido (`AppliesStructuredFilters`); cada entidad solo declara sus campos permitidos. El manual de implementación está en [`filtros-avanzados.md`](filtros-avanzados.md).
- **Origen:** modelo ya usado en otro proyecto. Se conservó el contrato y se ajustó: el trait lanza excepción en lugar de ignorar entradas inválidas, los valores de `LIKE` se escapan, y los campos llevan tipo para que un valor incorrecto dé `422` y no un `500` de PostgreSQL.
- **Por qué dos barreras:** el Request valida lo que llega del cliente y el trait valida lo que llega al SQL. Una lista de campos desalineada entre ambos falla de forma visible.
- **Consecuencia:** agregar una entidad son 6 archivos pequeños y dos líneas de configuración. No hay entidades implementadas todavía.

## 12. Versionado de la API por URL
- **Decisión:** todas las rutas van bajo `/api/v1`, incluida la comprobación de vida (`GET /api/v1/ping`). Una versión nueva (`v2`) se agrega como otro grupo de rutas, sin tocar `v1`.
- **Convención de recursos:** los recursos de negocio se publican en `/api/v1/auth/<recurso>` (por ejemplo `/api/v1/auth/branches`), dentro del grupo `auth:api`, y cada ruta lleva `permission:<recurso>.<acción>`. Sin token la respuesta es `401`; con token y sin el permiso, `403`.
- **Consecuencia:** `openapi.json` declara `/api/v1` como único servidor. Las rutas anteriores `/api/auth/*` y `/api/ping` ya no existen.
