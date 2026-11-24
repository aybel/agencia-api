# Seeds de la base `agencia`

Antes, el catálogo de entidades y municipios (`php artisan db:seed`, o `../schema/01_catalogos_mx.sql` si no usas Laravel). Después, dos pasos, en este orden. El segundo continúa los ids del primero y aborta si las tablas no están como las deja el primero.

| Orden | Archivo | Qué hace |
| --- | --- | --- |
| 0 | `php artisan migrate` y `db:seed` (o `../schema/01_schema.sql` y `../schema/01_catalogos_mx.sql`) | Crea las tablas y carga entidades, municipios y los demás catálogos |
| 1 | [`../schema/02_seed_manual.sql`](../schema/02_seed_manual.sql) | Vacía las tablas y carga un conjunto pequeño y legible, escrito a mano |
| 2 | [`seed.py`](seed.py) | Agrega volumen: 2,000 ventas y 4,000 órdenes de servicio, más datos en las demás tablas |

> **Antes de cargar:** las migraciones (o `01_schema.sql`) crean y llenan `service_order_statuses`. Los dos seeds lo necesitan, porque cada orden se carga con el estatus `delivered`. Si el estatus no existe, `02_seed_manual.sql` y `seed.py` abortan con un mensaje claro.

## Cargar los datos

Con el stack de Docker levantado (`docker compose up -d`) y las migraciones aplicadas:

```bash
# 0. Entidades y municipios (si no los cargó ya `migrate --seed`)
docker compose exec app php artisan db:seed --class=StateSeeder
docker compose exec app php artisan db:seed --class=CitySeeder

# 1. Seed manual (vacía las tablas y reinicia los ids)
docker compose exec -T postgres psql -U agencia -d agencia -v ON_ERROR_STOP=1 < database/schema/02_seed_manual.sql

# 2. Seed masivo
cd database/seeds-python
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
.venv/bin/python seed.py                # dry-run: muestra conteos y no escribe nada
.venv/bin/python seed.py --dsn postgresql://agencia:secret@localhost:5432/agencia
```

El seed masivo se aplica en una transacción. Si lo ejecutas dos veces seguidas, la segunda aborta sin modificar nada; para repetirlo, ejecuta antes el seed manual. Los parámetros `--n-ventas`, `--n-ordenes` y `--n-inventario` cambian el volumen.

Para verificar el resultado:

```bash
docker compose exec -T postgres psql -U agencia -d agencia -c "select (select count(*) from sales) sales, (select count(*) from service_orders) service_orders"
```

Debe mostrar 2,012 ventas y 4,008 órdenes.

## Volumen de datos

| Tabla | Seed manual | `seed.py` agrega | Total |
| --- | ---: | ---: | ---: |
| `branches` | 3 | 3 | 6 |
| `employees` | 5 | 35 | 40 |
| `customers` | 12 | 1,451 | 1,463 |
| `cars` | 17 | 2,400 (2,000 vendidos y 400 en inventario) | 2,417 |
| `sales` | 12 | 2,000 | 2,012 |
| `service_types` | 7 | 13 | 20 |
| `service_orders` | 8 | 4,000 | 4,008 |
| `service_order_items` | 11 | 6,359 | 6,370 |

Los rangos de ids son contiguos: por ejemplo, las ventas del seed manual van del 1 al 12 y las del masivo del 13 al 2012.

## Qué contiene el seed manual

- 3 sucursales (Ciudad de México, Guadalajara, Monterrey).
- 12 ventas cerradas entre enero y junio de 2024, y 8 órdenes de servicio en el mismo periodo.
- Clientes registrados entre noviembre de 2023 y mayo de 2024.
- Tres órdenes sin cliente (`customer_id` en `NULL`).

> **Reglas de comisión:** ningún seed carga filas en `commission_rules`, porque las tasas las define el negocio. Las ventas históricas no tienen comisiones. Para cerrar una venta nueva hay que cargar antes al menos una regla global (ver [`arquitectura-ventas.md`](../../docs/arquitectura-ventas.md#49-comisiones)).

## Qué genera `seed.py`

Los datos son pseudoaleatorios pero **deterministas**: se calculan con `CRC32` sobre un texto fijo (`zlib.crc32`) en lugar de un generador aleatorio, así que cada ejecución produce exactamente los mismos datos. Es un port de `02_seed_masivo.sql` (MySQL), y reproduce sus conteos.

Parámetros por defecto (en la clase `Parametros`):

| Parámetro | Valor | Efecto |
| --- | --- | --- |
| `n_ventas` | 2000 | Ventas nuevas (cada una con su auto vendido) |
| `n_ordenes` | 4000 | Órdenes de servicio nuevas |
| `n_inventario` | 400 | Autos en inventario, sin vender |
| `fecha_inicio` | 2022-01-01 | Inicio del periodo de ventas |
| `fecha_corte` | 2024-06-30 | Ningún dato posterior a esta fecha |

### Reglas de generación

| Tabla | Regla |
| --- | --- |
| `branches` | Agrega Puebla, Querétaro y Tijuana (ids 4 a 6), cada una con su municipio del catálogo `cities` (`city_id`). La Ciudad de México usa la alcaldía Cuauhtémoc |
| `employees` | Cada empleado tiene un puesto del catálogo `job_titles` (el seed no genera `04 Jefe Mecánico`). Por sucursal: 2 vendedores, 1 asesor de servicio y 2 mecánicos. Agrega un gerente donde no hay |
| `customers` | Un cliente por cada primera compra. Se registra de 0 a 20 días antes de comprar. Email `clienteNNNNN@example.com`. El municipio (`city_id`) es el de la sucursal donde compra. El teléfono lleva lada de la ciudad |
| `cars` | Catálogo de 30 modelos con precio base y tipo de vehículo (`vehicle_types`: 16 `auto`, 11 `suv` y 3 `pickup`); los primeros se eligen con más frecuencia. En los vendidos, 60 % son nuevos (0 km) y 40 % usados de 1 a 8 años. El precio base (referencia 2024) baja 4 % por cada año que la venta es anterior a 2024, y otro 8 % por cada año de uso del auto. Inventario: 55 % nuevos y el resto usados de 1 a 6 años |
| `sales` | Todas se cargan `completed`, con la sucursal y el precio de lista del auto, el descuento como diferencia con el precio final, el IVA del 16 % contenido y el precio neto (final menos IVA, sin accesorios). Fechas distribuidas en 912 días (2022-01-01 a 2024-06-30), con un auto por venta. El vendedor es de la sucursal del auto. El precio final es de 94 % a 100 % del precio de lista. Cerca del 72 % de las ventas son de clientes nuevos y el resto son clientes recurrentes (381 clientes compran más de una vez) |
| `service_orders` | Todas se cargan `delivered` y con la sucursal del auto, sin kilometraje ni notas. 8 % son revisiones previas a la venta (1 a 10 días antes, sin cliente), 85 % son servicios posteriores a la venta a nombre del comprador, y 7 % son servicios a autos del inventario (sin cliente, en los últimos 182 días). Los autos vendidos hace más tiempo reciben más servicios. La entrega es de 0 a 3 días después del ingreso. La atiende un asesor o mecánico de la sucursal del auto |
| `service_order_items` | Cantidad 1, precio unitario igual al costo aplicado y descripción copiada del servicio. 1 servicio por orden (55 %), 2 (30 %) o 3 (15 %), sin repetir tipo en la misma orden. La elección favorece los tipos con id bajo (el cambio de aceite es el más común). El segundo y tercer servicio de una orden se desplazan 7 tipos en el catálogo, por eso la "Afinación mayor" (id 8) también es frecuente. Cerca del 10 % de las líneas tiene 10 % de descuento sobre `precio_base` |
| `service_orders.total_mxn` | Se calcula como la suma de `applied_cost_mxn` de sus detalles |
| `created_at`, `updated_at` | Se derivan de las fechas de negocio (ver [`docs/er.md`](../../docs/er.md#timestamps-en-los-seeds)) |

## Reglas que se cumplen

Se comprobaron con consultas sobre la base cargada con ambos seeds en PostgreSQL:

- Cada orden tiene al menos un detalle, y `total_mxn` es igual a la suma de sus detalles (incluidas las 8 órdenes del seed manual).
- Ningún cliente compra antes de su `registration_date`.
- Todo auto con `status = 'sold'` tiene una venta, y toda venta es de un auto `sold`.
- No hay fechas posteriores a `2024-06-30`, ni `delivery_date` anterior a `check_in_date`.
- No hay emails duplicados ni precios en cero o negativos.
- Los 20 tipos de servicio se usan al menos una vez.

## `noise.py`

Ensucia datos limpios de forma determinista, para practicar el ETL de limpieza. Es un esqueleto: aún no está conectado a la base.
