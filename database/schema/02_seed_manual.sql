-- ==========================================================
-- SEED MANUAL: conjunto pequeño y legible (3 sucursales, 12 ventas, ...)
-- Útil para entender el modelo y probar consultas a mano.
--
--   psql -U agencia -d agencia -f database/schema/02_seed_manual.sql
--
-- Requiere el esquema creado (php artisan migrate o 01_schema.sql) y el catálogo de entidades y municipios
-- cargado (php artisan db:seed o database/schema/01_catalogos_mx.sql).
-- VACÍA las tablas del negocio (y, por CASCADE, las facturas, pagos, bitácora y las tablas de la
-- venta: accesorios, autos a cuenta, financiamiento, transferencias y comisiones, y las especializaciones
-- de los empleados, que dependan de ellas) y reinicia sus ids antes de insertar, porque las ventas y órdenes se refieren a los
-- ids 1..N de este archivo. No toca los catálogos (`states`, `cities`, `job_titles`, `vehicle_types`, `service_order_statuses`), `parts` ni `users`.
--
-- Las ventas históricas se cargan como cerradas (completed), sin accesorios, financiamiento, pagos ni
-- comisiones. Tampoco se cargan reglas de comisión: las tasas las define el negocio (commission_rules).
-- Las órdenes y partidas históricas se cargan como entregadas, con sucursal, y con la descripción
-- y el precio unitario de cada partida. No tienen kilometraje ni notas (columnas NULL).
-- Después puedes cargar el volumen con database/seeds-python/seed.py.
--
-- Los textos (nombres, ciudades, servicios) están en español porque son
-- datos. Los códigos de estado (status) están en inglés:
--   cars.status:        new | used | sold
--   vehicle_types.code: auto | suv | pickup | van | truck
--   job_titles.code:     01 vendedor | 02 gerente | 03 asesor de servicio | 04 jefe mecánico | 05 mecánico
-- ==========================================================

BEGIN;

TRUNCATE service_order_items, service_orders, service_types,
         sales, cars, employees, customers, branches
    RESTART IDENTITY CASCADE;

-- Requiere los puestos, los tipos de vehículo y los estatus que crean las migraciones (o el final de 01_schema.sql),
-- y las entidades y municipios (StateSeeder y CitySeeder, o 01_catalogos_mx.sql).
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM service_order_statuses WHERE code = 'delivered') THEN
        RAISE EXCEPTION 'Faltan los estatus de service_order_statuses: ejecuta php artisan migrate o 01_schema.sql';
    END IF;
    IF (SELECT count(*) FROM cities) <> 2478 OR (SELECT count(*) FROM states) <> 32 THEN
        RAISE EXCEPTION 'Faltan las entidades y los municipios: ejecuta php artisan db:seed o 01_catalogos_mx.sql';
    END IF;
    IF (SELECT count(*) FROM vehicle_types WHERE code IN ('auto', 'suv', 'pickup', 'van', 'truck')) <> 5 THEN
        RAISE EXCEPTION 'Faltan los tipos de vehículo de vehicle_types: ejecuta php artisan migrate o 01_schema.sql';
    END IF;
    IF (SELECT count(*) FROM job_titles WHERE code IN ('01', '02', '03', '05')) <> 4 THEN
        RAISE EXCEPTION 'Faltan los puestos de job_titles: ejecuta php artisan migrate o 01_schema.sql';
    END IF;
END $$;

-- Municipio por clave del INEGI (entidad, municipio). Función temporal: solo existe en esta sesión.
CREATE FUNCTION pg_temp.city_id(p_state_code text, p_city_code text) RETURNS bigint
LANGUAGE sql STABLE AS $$
    SELECT c.id FROM cities c JOIN states s ON s.id = c.state_id
    WHERE s.code = p_state_code AND c.code = p_city_code
$$;

-- Branches. Municipios: Ciudad de México = Cuauhtémoc (09-015, donde está Av. Juárez), Guadalajara = 14-039,
-- Monterrey = 19-039.
INSERT INTO branches (name, city_id, address) VALUES
('Sucursal Centro', pg_temp.city_id('09', '015'), 'Av. Juárez 123'),
('Sucursal Sur', pg_temp.city_id('14', '039'), 'Av. López Mateos 456'),
('Sucursal Norte', pg_temp.city_id('19', '039'), 'Calle Hidalgo 789');

-- Customers. Cada cliente vive en el municipio de la sucursal donde compra.
INSERT INTO customers (first_name, last_name, email, phone, city_id, registration_date) VALUES
('Jesús', 'Pérez', 'jesus.perez@email.com', '555-1234', pg_temp.city_id('09', '015'), '2023-11-01'),
('Ana', 'García', 'ana.garcia@email.com', '333-5678', pg_temp.city_id('14', '039'), '2023-11-15'),
('Pedro', 'López', 'pedro.lopez@email.com', '818-8765', pg_temp.city_id('19', '039'), '2023-12-05'),
('María', 'Martínez', 'maria.mtz@email.com', '555-4321', pg_temp.city_id('09', '015'), '2024-01-10'),
('Luis', 'Hernández', 'luis.hdz@email.com', '333-1122', pg_temp.city_id('14', '039'), '2024-01-20'),
('Laura', 'Gómez', 'laura.gomez@email.com', '555-9876', pg_temp.city_id('09', '015'), '2024-02-15'),
('Roberto', 'Díaz', 'roberto.diaz@email.com', '818-5432', pg_temp.city_id('19', '039'), '2024-02-20'),
('Carmen', 'Ruiz', 'carmen.ruiz@email.com', '333-8765', pg_temp.city_id('14', '039'), '2024-03-05'),
('Javier', 'Mendoza', 'javier.mtz@email.com', '555-3344', pg_temp.city_id('09', '015'), '2024-03-15'),
('Elena', 'Torres', 'elena.torres@email.com', '818-2233', pg_temp.city_id('19', '039'), '2024-04-01'),
('Diego', 'Silva', 'diego.silva@email.com', '333-9988', pg_temp.city_id('14', '039'), '2024-04-10'),
('Patricia', 'Rojas', 'patricia.rojas@email.com', '555-7766', pg_temp.city_id('09', '015'), '2024-05-05');

-- Employees
INSERT INTO employees (first_name, last_name, job_title_id, branch_id) VALUES
('Carlos', 'Sánchez', (SELECT id FROM job_titles WHERE code = '01'), 1),
('Sofía', 'Ramírez', (SELECT id FROM job_titles WHERE code = '02'), 2),
('Jorge', 'Torres', (SELECT id FROM job_titles WHERE code = '01'), 3),
('Mónica', 'Vargas', (SELECT id FROM job_titles WHERE code = '03'), 1),
('Ricardo', 'Castro', (SELECT id FROM job_titles WHERE code = '05'), 1);

-- Service types
INSERT INTO service_types (name, base_price_mxn) VALUES
('Cambio de aceite y filtro', 1500.00),
('Revisión y ajuste de frenos', 2500.00),
('Alineación y balanceo', 800.00),
('Cambio de batería', 3500.00),
('Servicio de suspensión', 4500.00),
('Lavado y detallado', 500.00),
('Diagnóstico computarizado', 1000.00);

-- Cars
INSERT INTO cars (brand, model, vehicle_type_id, model_year, mileage, price_mxn, status, branch_id) VALUES
('Nissan', 'Versa', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2024, 0, 325000.00, 'sold', 1),
('Toyota', 'Corolla', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2022, 45000, 290000.00, 'sold', 1),
('Honda', 'Civic', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2021, 62000, 340000.00, 'sold', 2),
('Mazda', 'Mazda 3', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2023, 0, 380000.00, 'sold', 2),
('Volkswagen', 'Jetta', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2020, 80000, 250000.00, 'sold', 3),
('Ford', 'Mustang', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2022, 15000, 850000.00, 'sold', 1),
('Chevrolet', 'Aveo', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2024, 0, 260000.00, 'sold', 3),
('Kia', 'Rio', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2019, 95000, 190000.00, 'sold', 2),
('Nissan', 'Sentra', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2021, 35000, 320000.00, 'sold', 1),
('Hyundai', 'Elantra', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2024, 0, 360000.00, 'sold', 3),
('Toyota', 'RAV4', (SELECT id FROM vehicle_types WHERE code = 'suv'), 2022, 30000, 480000.00, 'sold', 2),
('Mazda', 'CX-5', (SELECT id FROM vehicle_types WHERE code = 'suv'), 2021, 40000, 450000.00, 'sold', 1),
('Peugeot', '208', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2024, 0, 310000.00, 'new', 1),
('Renault', 'Kwid', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2023, 0, 210000.00, 'new', 3),
('Nissan', 'Kicks', (SELECT id FROM vehicle_types WHERE code = 'suv'), 2023, 12000, 350000.00, 'used', 2),
('Honda', 'CR-V', (SELECT id FROM vehicle_types WHERE code = 'suv'), 2022, 25000, 520000.00, 'used', 1),
('Volkswagen', 'Virtus', (SELECT id FROM vehicle_types WHERE code = 'auto'), 2024, 0, 340000.00, 'new', 2);

-- Sales (enero a junio de 2024). Todas cerradas (completed). El resto de columnas se deriva del auto:
-- precio de lista y sucursal (cars), descuento = lista menos precio final, IVA contenido en el precio
-- final (16 %), precio neto antes de impuestos (sin accesorios: final menos IVA) y estatus que tenía el
-- auto (nuevo si no tenía kilometraje).
INSERT INTO sales (car_id, customer_id, employee_id, branch_id, status, sale_date, car_status_at_sale,
                   list_price_mxn, discount_mxn, final_price_mxn, tax_mxn, net_sale_price_mxn)
SELECT v.car_id, v.customer_id, v.employee_id, c.branch_id, 'completed', v.sale_date,
       CASE WHEN c.mileage = 0 THEN 'new' ELSE 'used' END,
       c.price_mxn, c.price_mxn - v.final_price_mxn, v.final_price_mxn,
       round(v.final_price_mxn - v.final_price_mxn / 1.16, 2),
       v.final_price_mxn - round(v.final_price_mxn - v.final_price_mxn / 1.16, 2)
FROM (VALUES
    (1, 1, 1, DATE '2024-01-15', 320000.00),
    (2, 4, 1, DATE '2024-01-22', 285000.00),
    (3, 2, 2, DATE '2024-02-05', 335000.00),
    (4, 6, 2, DATE '2024-02-18', 375000.00),
    (5, 3, 3, DATE '2024-03-02', 245000.00),
    (6, 7, 1, DATE '2024-03-15', 840000.00),
    (7, 5, 3, DATE '2024-04-10', 255000.00),
    (8, 8, 2, DATE '2024-04-25', 185000.00),
    (9, 9, 1, DATE '2024-05-12', 315000.00),
    (10, 10, 3, DATE '2024-05-28', 355000.00),
    (11, 11, 2, DATE '2024-06-05', 475000.00),
    (12, 12, 1, DATE '2024-06-20', 445000.00)
) AS v(car_id, customer_id, employee_id, sale_date, final_price_mxn)
JOIN cars c ON c.id = v.car_id
ORDER BY v.car_id;

-- Service orders (todas entregadas; branch_id = sucursal del auto)
INSERT INTO service_orders (car_id, customer_id, employee_id, branch_id, status_id, check_in_date, delivery_date, total_mxn) VALUES
(1, NULL, 5, 1, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-01-12', '2024-01-14', 2000.00),
(1, 1, 4, 1, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-03-10', '2024-03-10', 1500.00),
(2, 4, 5, 1, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-04-05', '2024-04-06', 3300.00),
(5, NULL, 5, 3, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-02-28', '2024-03-01', 7000.00),
(3, 2, 4, 2, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-05-15', '2024-05-15', 800.00),
(6, 7, 5, 1, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-05-20', '2024-05-22', 4500.00),
(13, NULL, 5, 1, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-06-01', '2024-06-02', 500.00),
(5, 3, 4, 3, (SELECT id FROM service_order_statuses WHERE code = 'delivered'), '2024-06-10', '2024-06-11', 3500.00);

-- Service order items (cantidad 1; precio unitario = costo aplicado; descripción = nombre del servicio)
INSERT INTO service_order_items (service_order_id, service_type_id, description, unit_price_mxn, applied_cost_mxn) VALUES
(1, 6, 'Lavado y detallado', 500.00, 500.00),
(1, 1, 'Cambio de aceite y filtro', 1500.00, 1500.00),
(2, 1, 'Cambio de aceite y filtro', 1500.00, 1500.00),
(3, 2, 'Revisión y ajuste de frenos', 2500.00, 2500.00),
(3, 3, 'Alineación y balanceo', 800.00, 800.00),
(4, 2, 'Revisión y ajuste de frenos', 2500.00, 2500.00),
(4, 5, 'Servicio de suspensión', 4500.00, 4500.00),
(5, 3, 'Alineación y balanceo', 800.00, 800.00),
(6, 5, 'Servicio de suspensión', 4500.00, 4500.00),
(7, 6, 'Lavado y detallado', 500.00, 500.00),
(8, 4, 'Cambio de batería', 3500.00, 3500.00);

-- ----------------------------------------------------------
-- Timestamps determinísticos, derivados de las fechas de negocio
-- (Eloquent los llenará solos en filas nuevas; aquí se fijan para
-- que el seed sea reproducible).
-- ----------------------------------------------------------
UPDATE branches      SET created_at = '2021-01-01', updated_at = '2021-01-01';
UPDATE employees     SET created_at = '2021-01-01', updated_at = '2021-01-01';
UPDATE service_types SET created_at = '2021-01-01', updated_at = '2021-01-01';
UPDATE customers     SET created_at = registration_date, updated_at = registration_date;
UPDATE cars          SET created_at = '2021-01-01', updated_at = '2021-01-01';
UPDATE sales         SET created_at = sale_date, updated_at = sale_date;
-- Un auto vendido se actualiza el día de su venta (cambió su status)
UPDATE cars c SET updated_at = s.sale_date FROM sales s WHERE s.car_id = c.id;
UPDATE service_orders SET created_at = check_in_date,
                          updated_at = COALESCE(delivery_date, check_in_date);
UPDATE service_order_items i SET created_at = o.created_at, updated_at = o.created_at
    FROM service_orders o WHERE o.id = i.service_order_id;

COMMIT;
