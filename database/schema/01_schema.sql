-- ==========================================================
-- ESQUEMA (DDL) DE LA BASE `agencia` - PostgreSQL 17
--
-- Equivale a las migraciones de backend/database/migrations y debe
-- mantenerse alineado con ellas. Sirve para crear el esquema sin Laravel:
--
--   psql -U agencia -d agencia -f database/schema/01_schema.sql
--
-- BORRA las 8 tablas si ya existen.
-- No lo ejecutes si ya usaste `php artisan migrate`, salvo que quieras
-- reemplazar las tablas (la tabla `migrations` de Laravel no se toca).
--
-- Convenciones de Laravel: tablas en inglés y plural, llave primaria `id`,
-- llaves foráneas `<singular>_id`, y `created_at`/`updated_at` en todas.
-- Los nombres de restricciones son los que genera Laravel, para que este
-- esquema y el de las migraciones sean idénticos.
-- ==========================================================

DROP TABLE IF EXISTS service_order_items, service_orders, service_types,
                     sales, cars, employees, customers, branches CASCADE;

CREATE TABLE branches (
    id         BIGSERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    city       VARCHAR(50)  NOT NULL,
    address    VARCHAR(150) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE customers (
    id                BIGSERIAL PRIMARY KEY,
    first_name        VARCHAR(50)  NOT NULL,
    last_name         VARCHAR(50)  NOT NULL,
    email             VARCHAR(100) NOT NULL,
    phone             VARCHAR(15),
    city              VARCHAR(50),
    registration_date DATE DEFAULT '2023-01-01',
    created_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT customers_email_unique UNIQUE (email)
);

CREATE TABLE employees (
    id         BIGSERIAL PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name  VARCHAR(50) NOT NULL,
    job_title  VARCHAR(50) NOT NULL,  -- seller, manager, service_advisor, mechanic
    branch_id  BIGINT,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT employees_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id)
);

CREATE TABLE cars (
    id         BIGSERIAL PRIMARY KEY,
    brand      VARCHAR(50)   NOT NULL,
    model      VARCHAR(50)   NOT NULL,
    model_year INTEGER       NOT NULL,
    mileage    INTEGER       NOT NULL,
    price_mxn  NUMERIC(12,2) NOT NULL,  -- precio de lista
    status     VARCHAR(10)   NOT NULL DEFAULT 'new',
    branch_id  BIGINT,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT cars_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT cars_status_check
        CHECK (status IN ('new', 'used', 'sold'))
);

CREATE TABLE sales (
    id              BIGSERIAL PRIMARY KEY,
    car_id          BIGINT,  -- UNIQUE: un auto se vende una sola vez
    customer_id     BIGINT,
    employee_id     BIGINT,
    sale_date       DATE          NOT NULL,
    final_price_mxn NUMERIC(12,2) NOT NULL,
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at      TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT sales_car_id_unique UNIQUE (car_id),
    CONSTRAINT sales_car_id_foreign
        FOREIGN KEY (car_id) REFERENCES cars (id),
    CONSTRAINT sales_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT sales_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id)
);

CREATE TABLE service_types (
    id             BIGSERIAL PRIMARY KEY,
    name           VARCHAR(100)  NOT NULL,
    base_price_mxn NUMERIC(10,2) NOT NULL,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE service_orders (
    id            BIGSERIAL PRIMARY KEY,
    car_id        BIGINT,
    customer_id   BIGINT,  -- NULL cuando el auto es de la agencia
    employee_id   BIGINT,
    check_in_date DATE          NOT NULL,
    delivery_date DATE,
    total_mxn     NUMERIC(10,2) NOT NULL,
    created_at    TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at    TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT service_orders_car_id_foreign
        FOREIGN KEY (car_id) REFERENCES cars (id),
    CONSTRAINT service_orders_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT service_orders_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id)
);

CREATE TABLE service_order_items (
    id               BIGSERIAL PRIMARY KEY,
    service_order_id BIGINT,
    service_type_id  BIGINT,
    applied_cost_mxn NUMERIC(10,2) NOT NULL,  -- puede diferir de base_price_mxn
    created_at       TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at       TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT service_order_items_service_order_id_foreign
        FOREIGN KEY (service_order_id) REFERENCES service_orders (id),
    CONSTRAINT service_order_items_service_type_id_foreign
        FOREIGN KEY (service_type_id) REFERENCES service_types (id)
);
