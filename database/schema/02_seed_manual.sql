-- ==========================================================
-- SEED MANUAL: conjunto pequeño y legible (3 sucursales, 12 ventas, ...)
-- Útil para entender el modelo y probar consultas a mano.
--
--   psql -U agencia -d agencia -f database/schema/02_seed_manual.sql
--
-- Requiere el esquema creado (php artisan migrate o 01_schema.sql).
-- VACÍA las 8 tablas y reinicia sus ids antes de insertar, porque las
-- ventas y órdenes se refieren a los ids 1..N de este archivo.
-- Después puedes cargar el volumen con database/seeds-python/seed.py.
--
-- Los textos (nombres, ciudades, servicios) están en español porque son
-- datos. Los códigos (status, job_title) están en inglés:
--   cars.status:        new | used | sold
--   employees.job_title: seller | manager | service_advisor | mechanic
-- ==========================================================

BEGIN;

TRUNCATE service_order_items, service_orders, service_types,
         sales, cars, employees, customers, branches
    RESTART IDENTITY CASCADE;

-- Branches
INSERT INTO branches (name, city, address) VALUES
('Sucursal Centro', 'Ciudad de México', 'Av. Juárez 123'),
('Sucursal Sur', 'Guadalajara', 'Av. López Mateos 456'),
('Sucursal Norte', 'Monterrey', 'Calle Hidalgo 789');

-- Customers
INSERT INTO customers (first_name, last_name, email, phone, city, registration_date) VALUES
('Jesús', 'Pérez', 'jesus.perez@email.com', '555-1234', 'Ciudad de México', '2023-11-01'),
('Ana', 'García', 'ana.garcia@email.com', '333-5678', 'Guadalajara', '2023-11-15'),
('Pedro', 'López', 'pedro.lopez@email.com', '818-8765', 'Monterrey', '2023-12-05'),
('María', 'Martínez', 'maria.mtz@email.com', '555-4321', 'Ciudad de México', '2024-01-10'),
('Luis', 'Hernández', 'luis.hdz@email.com', '333-1122', 'Guadalajara', '2024-01-20'),
('Laura', 'Gómez', 'laura.gomez@email.com', '555-9876', 'Ciudad de México', '2024-02-15'),
('Roberto', 'Díaz', 'roberto.diaz@email.com', '818-5432', 'Monterrey', '2024-02-20'),
('Carmen', 'Ruiz', 'carmen.ruiz@email.com', '333-8765', 'Guadalajara', '2024-03-05'),
('Javier', 'Mendoza', 'javier.mtz@email.com', '555-3344', 'Ciudad de México', '2024-03-15'),
('Elena', 'Torres', 'elena.torres@email.com', '818-2233', 'Monterrey', '2024-04-01'),
('Diego', 'Silva', 'diego.silva@email.com', '333-9988', 'Guadalajara', '2024-04-10'),
('Patricia', 'Rojas', 'patricia.rojas@email.com', '555-7766', 'Ciudad de México', '2024-05-05');

-- Employees
INSERT INTO employees (first_name, last_name, job_title, branch_id) VALUES
('Carlos', 'Sánchez', 'seller', 1),
('Sofía', 'Ramírez', 'manager', 2),
('Jorge', 'Torres', 'seller', 3),
('Mónica', 'Vargas', 'service_advisor', 1),
('Ricardo', 'Castro', 'mechanic', 1);

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
INSERT INTO cars (brand, model, model_year, mileage, price_mxn, status, branch_id) VALUES
('Nissan', 'Versa', 2024, 0, 325000.00, 'sold', 1),
('Toyota', 'Corolla', 2022, 45000, 290000.00, 'sold', 1),
('Honda', 'Civic', 2021, 62000, 340000.00, 'sold', 2),
('Mazda', 'Mazda 3', 2023, 0, 380000.00, 'sold', 2),
('Volkswagen', 'Jetta', 2020, 80000, 250000.00, 'sold', 3),
('Ford', 'Mustang', 2022, 15000, 850000.00, 'sold', 1),
('Chevrolet', 'Aveo', 2024, 0, 260000.00, 'sold', 3),
('Kia', 'Rio', 2019, 95000, 190000.00, 'sold', 2),
('Nissan', 'Sentra', 2021, 35000, 320000.00, 'sold', 1),
('Hyundai', 'Elantra', 2024, 0, 360000.00, 'sold', 3),
('Toyota', 'RAV4', 2022, 30000, 480000.00, 'sold', 2),
('Mazda', 'CX-5', 2021, 40000, 450000.00, 'sold', 1),
('Peugeot', '208', 2024, 0, 310000.00, 'new', 1),
('Renault', 'Kwid', 2023, 0, 210000.00, 'new', 3),
('Nissan', 'Kicks', 2023, 12000, 350000.00, 'used', 2),
('Honda', 'CR-V', 2022, 25000, 520000.00, 'used', 1),
('Volkswagen', 'Virtus', 2024, 0, 340000.00, 'new', 2);

-- Sales (enero a junio de 2024)
INSERT INTO sales (car_id, customer_id, employee_id, sale_date, final_price_mxn) VALUES
(1, 1, 1, '2024-01-15', 320000.00),
(2, 4, 1, '2024-01-22', 285000.00),
(3, 2, 2, '2024-02-05', 335000.00),
(4, 6, 2, '2024-02-18', 375000.00),
(5, 3, 3, '2024-03-02', 245000.00),
(6, 7, 1, '2024-03-15', 840000.00),
(7, 5, 3, '2024-04-10', 255000.00),
(8, 8, 2, '2024-04-25', 185000.00),
(9, 9, 1, '2024-05-12', 315000.00),
(10, 10, 3, '2024-05-28', 355000.00),
(11, 11, 2, '2024-06-05', 475000.00),
(12, 12, 1, '2024-06-20', 445000.00);

-- Service orders
INSERT INTO service_orders (car_id, customer_id, employee_id, check_in_date, delivery_date, total_mxn) VALUES
(1, NULL, 5, '2024-01-12', '2024-01-14', 2000.00),
(1, 1, 4, '2024-03-10', '2024-03-10', 1500.00),
(2, 4, 5, '2024-04-05', '2024-04-06', 3300.00),
(5, NULL, 5, '2024-02-28', '2024-03-01', 7000.00),
(3, 2, 4, '2024-05-15', '2024-05-15', 800.00),
(6, 7, 5, '2024-05-20', '2024-05-22', 4500.00),
(13, NULL, 5, '2024-06-01', '2024-06-02', 500.00),
(5, 3, 4, '2024-06-10', '2024-06-11', 3500.00);

-- Service order items
INSERT INTO service_order_items (service_order_id, service_type_id, applied_cost_mxn) VALUES
(1, 6, 500.00),
(1, 1, 1500.00),
(2, 1, 1500.00),
(3, 2, 2500.00),
(3, 3, 800.00),
(4, 2, 2500.00),
(4, 5, 4500.00),
(5, 3, 800.00),
(6, 5, 4500.00),
(7, 6, 500.00),
(8, 4, 3500.00);

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
