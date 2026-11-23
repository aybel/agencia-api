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
- **Valores traducidos:** los códigos pasaron a inglés (`cars.status`: `new`, `used`, `sold`; el puesto de los empleados es un catálogo, ver la decisión 16). Los textos que son datos (nombres, ciudades, servicios) siguen en español. La interfaz traduce los códigos para mostrarlos.
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

## 13. Diseño del módulo de servicio (órdenes, facturas y pagos)
- **Decisión:** el taller se modela con estatus en catálogo (`service_order_statuses`), bitácora (`service_order_histories`), refacciones (`parts`), facturas (`invoices`) y pagos (`payments`). El diseño y las reglas están en [`arquitectura_servicios.md`](arquitectura_servicios.md).
- **Facturas sin relación polimórfica:** `invoices` tiene dos llaves foráneas reales (`sale_id`, `service_order_id`) y un `CHECK` que exige exactamente una. PostgreSQL no valida llaves polimórficas, así que una factura podía apuntar a un registro inexistente.
- **Quien actúa es el usuario:** bitácora, partidas y pagos guardan `user_id` y no `employee_id`. El principal autenticado es un usuario, y el admin no es un empleado. Los empleados se relacionan con `users` por `employees.user_id` (1 a 1, opcional).
- **Aprobación de extras con tres estados:** `approval_status` (`approved`, `pending`, `rejected`) en lugar de un booleano, que no distingue "espera respuesta" de "rechazado".
- **Refacciones aparte:** `parts` no reemplaza a `service_types`. Renombrar rompía las llaves foráneas y los seeds, y mano de obra y refacciones tienen ciclos de vida distintos (existencias, costo).
- **Copia de datos al momento de la orden:** `description`, `unit_price_mxn` y los datos de contacto se copian en la orden, para que cambiar el catálogo o el cliente no altere órdenes ya cobradas.
- **Integridad en la base:** `CHECK`, `UNIQUE` parciales y `ON DELETE RESTRICT` en lo que forma historial. Lo que no puede garantizar la base (transiciones de estatus, total de la orden, suma de pagos) lo hace la aplicación dentro de una transacción con bloqueo.
- **Catálogo de estatus en la migración:** las migraciones insertan los 7 estatus porque el código depende de ellos y los tests no ejecutan seeders.
- **Datos históricos:** las columnas obligatorias solo para órdenes nuevas (`mileage_in`) son nullable en la base y se exigen en la aplicación, porque las 4,008 órdenes históricas no las tienen.
- **Migraciones editadas en su lugar:** el proyecto aún no está en producción, así que se modificaron las migraciones existentes en lugar de agregar migraciones `alter`. Aplicarlas a una base ya migrada requiere `php artisan migrate:fresh` y recargar los seeds. Cuando haya datos reales, los cambios de esquema deben ser migraciones nuevas.
- **Alcance:** el taller solo atiende autos de las sucursales, todos en `cars` (sección 4.8 de `arquitectura_servicios.md`). No hay autos externos, así que no se necesita una tabla de vehículos de clientes. Una orden a nombre de un cliente corresponde al comprador del auto; una sin cliente, a un auto de inventario o a una revisión previa a la venta.
- **Pendiente:** garantía, devoluciones y notas de crédito. La garantía va por medio del seguro y todavía no se sabe cómo entra al sistema, así que no se diseñó nada para ella.

## 14. Diseño del módulo de ventas
- **Decisión:** la venta pasa de un registro de hecho a un documento con ciclo de vida (`draft`, `completed`, `cancelled`), con accesorios (`sale_addons`), autos a cuenta (`sale_trade_ins`), financiamiento (`sale_financings`), transferencias de vendedor (`sale_transfers`) y comisiones (`sale_commissions`). El diseño y las reglas están en [`arquitectura-ventas.md`](arquitectura-ventas.md).
- **Una venta vigente por auto, no una venta por auto:** `UNIQUE(car_id)` se reemplazó por un índice único parcial (`WHERE status <> 'cancelled' AND superseded_at IS NULL`). Con el índice anterior una venta cancelada bloqueaba el auto para siempre, y un auto recibido a cuenta no podía revenderse.
- **El auto a cuenta no se inserta en `cars` mientras la venta es borrador.** `sale_trade_ins` guarda una copia y `car_id` se asigna al cerrar. Insertarlo al capturar dejaba inventario fantasma si la venta se cancelaba.
- **Los pagos de ventas usan `payments`**, no una tabla propia. `payments.method` suma `financing` y `trade_in`. El saldo de cualquier factura es total menos la suma de sus pagos.
- **Baja de empleados con `is_active`**, no con `SoftDeletes`: este último oculta al vendedor dado de baja en las ventas históricas que lo muestran.
- **Reglas en la base:** `final = lista − descuento`, descuento mayor al 10 % con quien lo autorizó, venta cancelada con motivo, un financiamiento por venta, un vendedor por comisión. Lo que la base no puede (transiciones, suma de porcentajes, que el autorizador sea gerente) lo hace la aplicación dentro de una transacción con bloqueo.
- **IVA:** los precios de venta incluyen IVA y el desglose se calcula hacia atrás (`tax_mxn`). Los del taller y las refacciones no lo incluyen. Cada columna lo dice en su comentario.
- **Datos históricos:** las 2,012 ventas se cargan como `completed`, con valores derivados de lo que ya existía (sucursal y precio de lista del auto, descuento como diferencia, IVA contenido, precio neto). Ninguna supera el 10 % de descuento.
- **Base de la comisión:** el precio neto de la venta antes de impuestos, es decir el auto más los accesorios sin IVA, que es el subtotal de la factura de la venta. No se resta el valor del auto a cuenta (es una forma de pago) ni entran los intereses del crédito. Se copia a `sale_commissions.base_amount_mxn` al cerrar la venta, y un `CHECK` impide que la comisión supere su base.
- **Alcance:** solo autos de las sucursales (sección 4.12 de `arquitectura-ventas.md`). Un auto recibido a cuenta entra a `cars` al cerrar la venta.
- **Reglas de comisión por sucursal y condición del auto:** la tasa vive en `commission_rules` (`branch_id` nulo = global, condición `new`, `used` o `all`, fecha de vigencia, activa). Al cerrar la venta el sistema elige la regla más específica (sucursal y condición exacta, sucursal y `all`, global y condición exacta, global y `all`) y aplica su tasa sobre `sales.net_sale_price_mxn`. Sin regla aplicable, la venta no se cierra.
- **Una regla no se edita:** se agrega otra con nueva vigencia. Cada comisión guarda la regla aplicada y **una copia de la tasa**, y la base de datos verifica `importe = base × tasa × porcentaje / 100` (con un centavo de tolerancia para el redondeo del reparto).
- **Ajustes a la propuesta:** la columna se llama `rate` y es una fracción (0.0150), para no confundirla con `sale_commissions.percentage` (0 a 100, reparto). El índice único usa `NULLS NOT DISTINCT` (PostgreSQL 15 o superior): un `UNIQUE` normal permitiría dos reglas globales iguales porque los `NULL` no se consideran repetidos. `net_sale_price_mxn` se agregó a `sales`, y es `NULL` en borrador porque los accesorios pueden cambiar.
- **Pendiente:** los valores iniciales de las reglas (no hay ninguna cargada), la garantía por medio del seguro, con sus devoluciones y notas de crédito (entrada todavía desconocida) y la vigencia de un borrador que reserva un auto (decisiones abiertas 1, 3 y 6 del documento).

## 15. Documentar la base de datos con comentarios
- **Decisión:** cada tabla y cada columna del proyecto lleva un comentario en PostgreSQL. En las migraciones se escribe con `->comment()` en la columna y `$table->comment()` en la tabla. En `01_schema.sql` aparece como `COMMENT ON`.
- **Formato:** una frase que dice qué es, y para columnas con valores fijos, la lista `valor: significado` (por ejemplo `1: activo, 0: dado de baja`). Las llaves foráneas indican a qué apuntan (`branches.id`) y las unidades se explican (pesos, km, con o sin IVA).
- **Por qué en la base y no solo en los documentos:** el comentario viaja con el esquema. Se ve con `\d+ <tabla>` en `psql`, en HeidiSQL (columna "Comentario") y en cualquier herramienta que lea `information_schema`, y no se desfasa del código como un documento aparte.
- **Llaves foráneas:** el `->comment()` debe ir **antes** de `->constrained()`. Después se aplicaría a la restricción y no a la columna.
- **Alcance:** las 25 tablas del proyecto (negocio, ventas, taller, `users` y `menu_items`). Las tablas internas de Laravel (`cache`, `jobs`, `sessions`...) y las de Spatie (`roles`, `permissions`...) no se documentaron porque son de los paquetes.
- **Verificación:** una consulta a `pg_description` sobre las tablas del proyecto debe dar cero columnas sin comentario. `01_schema.sql` y las migraciones se comparan con `pg_dump`, que incluye los comentarios.
- **Mantenimiento:** una columna nueva sin `->comment()` incumple esta convención. Cuando una columna cambia de significado, se actualiza su comentario en la migración y en `01_schema.sql`.

## 16. Catálogo de puestos (`job_titles`)
- **Decisión:** el puesto de un empleado deja de ser un texto libre (`employees.job_title`) y pasa a ser una llave foránea (`employees.job_title_id`) a un catálogo, `job_titles`, con `code`, `name` e `is_active`. El `code` es un número de dos dígitos y los puestos actuales son `01` Vendedor, `02` Gerente, `03` Asesor de servicio, `04` Jefe Mecánico y `05` Mecánico.
- **Por qué un catálogo:** un texto libre admite errores de captura y valores distintos para el mismo puesto. Con la llave foránea el motor rechaza un puesto inexistente, no se puede borrar un puesto con empleados y se puede dar de baja sin perder historial.
- **Nombre:** `job_titles` y no `jobs`, porque `jobs` ya es la tabla de la cola de trabajos de Laravel.
- **Datos de referencia en la migración:** los puestos los inserta la migración (igual que los estatus de las órdenes), porque los empleados y los seeds dependen de ellos y los tests no ejecutan seeders.
- **Códigos numéricos, no nombres de rol:** el `CHECK` exige dos dígitos. Eso desacopla el puesto del rol del sistema (`admin`, `manager`, `seller`...). La correspondencia puesto → rol se guarda aparte, en `job_title_role` (decisión 17).
- **Seeds:** `seed.py` conserva sus claves internas (`seller`, `mechanic`...) porque los nombres de los empleados se calculan con ellas, y las traduce al código del catálogo. El seed no genera empleados `04 Jefe Mecánico`.
- **Datos existentes:** los 40 empleados conservan el puesto que tenían (14 vendedores, 6 gerentes, 7 asesores y 13 mecánicos).

## 17. Especialización de los empleados (`employee_specializations`)
- **Problema:** un empleado puede trabajar solo con ciertos autos: un mecánico solo de camionetas o camiones, un vendedor solo de autos usados. Mapear el puesto al rol por nombre no lo permite, porque mezcla tres cosas distintas.
- **Decisión:** se separan tres conceptos. El **puesto** (`job_titles`) es lo que el empleado es en la empresa. El **rol** (Spatie) es lo que el usuario puede hacer en el sistema. La **especialización** (`employee_specializations`) es con qué autos trabaja.
- **Tipos de vehículo:** el catálogo `vehicle_types` (`auto`, `suv`, `pickup`, `van`, `truck`) y `cars.vehicle_type_id` NOT NULL. "Camioneta" no es un tipo: según el caso es `suv` o `pickup`, y la especialización puede incluir varios.
- **Modelo:** cada fila de `employee_specializations` es una combinación permitida (`vehicle_type_id` y `car_condition`, que es `new`, `used` o `all`). Una fila exige las dos condiciones (Y), varias filas se suman (O), y un empleado **sin filas atiende cualquier auto**. `vehicle_type_id` `NULL` significa cualquier tipo. La combinación no se repite (`NULLS NOT DISTINCT`).
- **Dónde se aplica:** al asignar (crear o transferir una venta, asignar una orden de servicio), no al ver registros. Un gerente puede saltarse la restricción dejando una nota. La base solo guarda los datos y la validación está **pendiente de programar**, porque todavía no existen los flujos de ventas y órdenes.
- **Rol sugerido por puesto:** `job_title_role` (puesto, rol) con llaves foráneas reales, que `RolesSeeder` llena. Solo precarga el rol al crear el usuario de un empleado y no otorga permisos. Es una tabla y no una columna con el nombre del rol, para que un cambio de nombre se detecte con la llave foránea. Va en una migración posterior a la de Spatie porque referencia `roles`. Un empleado tiene un solo puesto.
- **Descartado:**
  - **Un permiso por combinación** (por ejemplo `sales.used.create`): multiplica los permisos con cada tipo nuevo y mezcla lo que el usuario puede hacer con los autos con los que trabaja.
  - **Un puesto por especialidad** (`Mecánico de camiones`): multiplica el catálogo de puestos y los reportes por puesto dejan de ser comparables.
- **Datos:** el seed clasifica los 30 modelos del catálogo: 16 `auto`, 11 `suv` y 3 `pickup` (Ranger, Hilux y Frontier). Es una clasificación por revisar. No se cargan especializaciones: sin filas, todos atienden todo.
- **Pendiente:** el rol del Jefe Mecánico (`04`) es provisional (`mechanic`) hasta que el negocio defina si necesita uno propio.
