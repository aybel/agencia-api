-- ==========================================================
-- ESQUEMA (DDL) DE LA BASE `agencia` - PostgreSQL 17
--
-- Equivale a las migraciones de backend/database/migrations (negocio, ventas y taller) y debe
-- mantenerse alineado con ellas. Sirve para crear el esquema sin Laravel:
--
--   psql -U agencia -d agencia -f database/schema/01_schema.sql
--
-- BORRA las 23 tablas del negocio, las ventas y el taller si ya existen.
-- No lo ejecutes si ya usaste `php artisan migrate`, salvo que quieras
-- reemplazar las tablas (la tabla `migrations` de Laravel no se toca).
--
-- No incluye las tablas de roles, permisos y menú: las crea Laravel. Como `employees`, las ventas,
-- las partidas, la bitácora y los pagos se relacionan con `users`, este script crea una tabla
-- `users` mínima solo si no existe (IF NOT EXISTS), idéntica a la de Laravel. Lo mismo con `roles`,
-- que referencia `job_title_role`.
--
-- Convenciones de Laravel: tablas en inglés y plural, llave primaria `id`,
-- llaves foráneas `<singular>_id`, y `created_at`/`updated_at` en todas.
-- Los nombres de restricciones e índices son los que genera Laravel, para que este
-- esquema y el de las migraciones sean idénticos.
--
-- Cada tabla y cada columna tiene un comentario (COMMENT ON, al final de este archivo): son el
-- diccionario de datos de la base. Se ven con \d+ <tabla> en psql, o en HeidiSQL.
--
-- Al final también se insertan los 5 puestos de los empleados, los 5 tipos de vehículo y los 7 estatus de las órdenes de
-- servicio: son datos de referencia que el código necesita y que las migraciones también insertan.
-- ==========================================================

DROP TABLE IF EXISTS sale_commissions, commission_rules, sale_transfers, sale_financings, sale_trade_ins, sale_addons,
                     payments, invoices, service_order_histories, service_order_items, service_orders,
                     parts, service_order_statuses, service_types,
                     sales, employee_specializations, cars, vehicle_types, employees, job_title_role, job_titles,
                     customers, branches CASCADE;

CREATE TABLE IF NOT EXISTS users (
    id                BIGSERIAL PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE,
    password          VARCHAR(255) NOT NULL,
    remember_token    VARCHAR(100),
    created_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT users_email_unique UNIQUE (email)
);

-- Roles de Spatie, mínima y solo si no existe (IF NOT EXISTS): job_title_role la referencia.
CREATE TABLE IF NOT EXISTS roles (
    id         BIGSERIAL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name)
);

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

CREATE TABLE job_titles (
    id         BIGSERIAL PRIMARY KEY,
    code       VARCHAR(30) NOT NULL,
    name       VARCHAR(80) NOT NULL,
    is_active  BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT job_titles_code_unique UNIQUE (code),
    CONSTRAINT job_titles_code_check CHECK (code ~ '^[0-9]{2}$')
);

CREATE TABLE job_title_role (
    job_title_id BIGINT NOT NULL,
    role_id      BIGINT NOT NULL,
    created_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT job_title_role_pkey PRIMARY KEY (job_title_id, role_id),
    CONSTRAINT job_title_role_job_title_id_foreign
        FOREIGN KEY (job_title_id) REFERENCES job_titles (id) ON DELETE CASCADE,
    CONSTRAINT job_title_role_role_id_foreign
        FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
);

CREATE TABLE employees (
    id           BIGSERIAL PRIMARY KEY,
    first_name   VARCHAR(50) NOT NULL,
    last_name    VARCHAR(50) NOT NULL,
    job_title_id BIGINT NOT NULL,
    branch_id    BIGINT,
    user_id      BIGINT,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT employees_user_id_unique UNIQUE (user_id),
    CONSTRAINT employees_job_title_id_foreign
        FOREIGN KEY (job_title_id) REFERENCES job_titles (id) ON DELETE RESTRICT,
    CONSTRAINT employees_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT employees_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE vehicle_types (
    id         BIGSERIAL PRIMARY KEY,
    code       VARCHAR(20) NOT NULL,
    name       VARCHAR(50) NOT NULL,
    is_active  BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT vehicle_types_code_unique UNIQUE (code)
);

CREATE TABLE cars (
    id            BIGSERIAL PRIMARY KEY,
    brand         VARCHAR(50)   NOT NULL,
    model         VARCHAR(50)   NOT NULL,
    vehicle_type_id BIGINT      NOT NULL,
    model_year    INTEGER       NOT NULL,
    mileage       INTEGER       NOT NULL,
    price_mxn     NUMERIC(12,2) NOT NULL,
    status        VARCHAR(10)   NOT NULL DEFAULT 'new',
    branch_id     BIGINT,
    vin           VARCHAR(17),
    license_plate VARCHAR(15),
    color         VARCHAR(30),
    created_at    TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at    TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT cars_vin_unique UNIQUE (vin),
    CONSTRAINT cars_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT cars_vehicle_type_id_foreign
        FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id) ON DELETE RESTRICT,
    CONSTRAINT cars_status_check
        CHECK (status IN ('new', 'used', 'sold')),
    CONSTRAINT cars_vin_check
        CHECK (vin ~ '^[A-HJ-NPR-Z0-9]{17}$')
);
CREATE INDEX cars_license_plate_index ON cars (license_plate);

CREATE TABLE sales (
    id                           BIGSERIAL PRIMARY KEY,
    car_id                       BIGINT NOT NULL,
    customer_id                  BIGINT NOT NULL,
    employee_id                  BIGINT NOT NULL,
    branch_id                    BIGINT NOT NULL,
    status                       VARCHAR(10) NOT NULL DEFAULT 'draft',
    sale_date                    DATE NOT NULL,
    car_status_at_sale           VARCHAR(10),
    list_price_mxn               NUMERIC(12,2) NOT NULL,
    discount_mxn                 NUMERIC(12,2) NOT NULL DEFAULT '0',
    discount_approved_by_user_id BIGINT,
    final_price_mxn              NUMERIC(12,2) NOT NULL,
    tax_mxn                      NUMERIC(12,2) NOT NULL,
    net_sale_price_mxn           NUMERIC(12,2),
    notes                        TEXT,
    superseded_at                TIMESTAMP(0) WITHOUT TIME ZONE,
    cancelled_at                 TIMESTAMP(0) WITHOUT TIME ZONE,
    cancellation_reason          TEXT,
    created_at                   TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at                   TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT sales_car_id_foreign
        FOREIGN KEY (car_id) REFERENCES cars (id) ON DELETE RESTRICT,
    CONSTRAINT sales_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT sales_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE RESTRICT,
    CONSTRAINT sales_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE RESTRICT,
    CONSTRAINT sales_discount_approved_by_user_id_foreign
        FOREIGN KEY (discount_approved_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT sales_status_check
        CHECK (status IN ('draft', 'completed', 'cancelled')),
    CONSTRAINT sales_car_status_at_sale_check
        CHECK (car_status_at_sale IS NULL OR car_status_at_sale IN ('new', 'used')),
    CONSTRAINT sales_completed_car_status_check
        CHECK (status <> 'completed' OR car_status_at_sale IS NOT NULL),
    CONSTRAINT sales_amounts_check
        CHECK (list_price_mxn >= 0 AND discount_mxn >= 0 AND discount_mxn <= list_price_mxn AND final_price_mxn = list_price_mxn - discount_mxn AND tax_mxn >= 0 AND tax_mxn <= final_price_mxn),
    CONSTRAINT sales_net_sale_price_check
        CHECK (net_sale_price_mxn IS NULL OR net_sale_price_mxn >= final_price_mxn - tax_mxn),
    CONSTRAINT sales_completed_net_price_check
        CHECK (status <> 'completed' OR net_sale_price_mxn IS NOT NULL),
    CONSTRAINT sales_discount_approval_check
        CHECK (discount_mxn * 10 <= list_price_mxn OR discount_approved_by_user_id IS NOT NULL),
    CONSTRAINT sales_cancelled_check
        CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL)),
    CONSTRAINT sales_cancellation_reason_check
        CHECK (status <> 'cancelled' OR cancellation_reason IS NOT NULL),
    CONSTRAINT sales_superseded_check
        CHECK (superseded_at IS NULL OR status = 'completed')
);
CREATE INDEX sales_car_id_index      ON sales (car_id);
CREATE INDEX sales_customer_id_index ON sales (customer_id);
CREATE INDEX sales_employee_id_index ON sales (employee_id);
CREATE INDEX sales_sale_date_index   ON sales (sale_date);
CREATE INDEX sales_branch_id_status_index ON sales (branch_id, status);
-- Un auto tiene a lo mucho una venta vigente (borrador o cerrada). Una cancelada o reemplazada no lo bloquea.
CREATE UNIQUE INDEX sales_car_id_active_unique
    ON sales (car_id) WHERE status <> 'cancelled' AND superseded_at IS NULL;

CREATE TABLE service_types (
    id             BIGSERIAL PRIMARY KEY,
    name           VARCHAR(100)  NOT NULL,
    base_price_mxn NUMERIC(10,2) NOT NULL,
    is_active      BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE service_order_statuses (
    id           BIGSERIAL PRIMARY KEY,
    code         VARCHAR(30) NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    color        VARCHAR(7),
    sort_order   SMALLINT    NOT NULL DEFAULT '0',
    is_final     BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT service_order_statuses_code_unique UNIQUE (code),
    CONSTRAINT service_order_statuses_color_check
        CHECK (color IS NULL OR color ~ '^#[0-9A-Fa-f]{6}$')
);

CREATE TABLE parts (
    id         BIGSERIAL PRIMARY KEY,
    sku        VARCHAR(50)   NOT NULL,
    name       VARCHAR(150)  NOT NULL,
    unit       VARCHAR(10)   NOT NULL DEFAULT 'pza',
    cost_mxn   NUMERIC(10,2) NOT NULL,
    price_mxn  NUMERIC(10,2) NOT NULL,
    stock      NUMERIC(12,2) NOT NULL DEFAULT '0',
    is_active  BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT parts_sku_unique UNIQUE (sku),
    CONSTRAINT parts_cost_check  CHECK (cost_mxn >= 0),
    CONSTRAINT parts_price_check CHECK (price_mxn >= 0),
    CONSTRAINT parts_stock_check CHECK (stock >= 0)
);
CREATE INDEX parts_name_index ON parts (name);

CREATE TABLE service_orders (
    id                       BIGSERIAL PRIMARY KEY,
    car_id                   BIGINT NOT NULL,
    customer_id              BIGINT,
    employee_id              BIGINT,
    branch_id                BIGINT NOT NULL,
    status_id                BIGINT NOT NULL,
    check_in_date            DATE   NOT NULL,
    estimated_delivery_date  DATE,
    delivery_date            DATE,
    mileage_in               INTEGER,
    mileage_out              INTEGER,
    customer_notes           TEXT,
    internal_notes           TEXT,
    contact_phone            VARCHAR(15),
    contact_email            VARCHAR(100),
    total_mxn                NUMERIC(10,2) NOT NULL DEFAULT '0',
    created_at               TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at               TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT service_orders_car_id_foreign
        FOREIGN KEY (car_id) REFERENCES cars (id) ON DELETE RESTRICT,
    CONSTRAINT service_orders_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT service_orders_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE RESTRICT,
    CONSTRAINT service_orders_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE RESTRICT,
    CONSTRAINT service_orders_status_id_foreign
        FOREIGN KEY (status_id) REFERENCES service_order_statuses (id) ON DELETE RESTRICT,
    CONSTRAINT service_orders_estimated_delivery_check
        CHECK (estimated_delivery_date IS NULL OR estimated_delivery_date >= check_in_date),
    CONSTRAINT service_orders_delivery_check
        CHECK (delivery_date IS NULL OR delivery_date >= check_in_date),
    CONSTRAINT service_orders_mileage_check
        CHECK ((mileage_in IS NULL OR mileage_in >= 0) AND (mileage_out IS NULL OR mileage_out >= 0) AND (mileage_in IS NULL OR mileage_out IS NULL OR mileage_out >= mileage_in)),
    CONSTRAINT service_orders_total_check
        CHECK (total_mxn >= 0)
);
CREATE INDEX service_orders_car_id_index        ON service_orders (car_id);
CREATE INDEX service_orders_customer_id_index   ON service_orders (customer_id);
CREATE INDEX service_orders_employee_id_index   ON service_orders (employee_id);
CREATE INDEX service_orders_status_id_index     ON service_orders (status_id);
CREATE INDEX service_orders_check_in_date_index ON service_orders (check_in_date);
CREATE INDEX service_orders_branch_id_status_id_index ON service_orders (branch_id, status_id);

CREATE TABLE service_order_items (
    id                  BIGSERIAL PRIMARY KEY,
    service_order_id    BIGINT NOT NULL,
    service_type_id     BIGINT,
    part_id             BIGINT,
    description         VARCHAR(255)  NOT NULL,
    quantity            NUMERIC(10,2) NOT NULL DEFAULT '1',
    unit_price_mxn      NUMERIC(10,2) NOT NULL,
    applied_cost_mxn    NUMERIC(10,2) NOT NULL,
    approval_status     VARCHAR(10)   NOT NULL DEFAULT 'approved',
    approved_at         TIMESTAMP(0) WITHOUT TIME ZONE,
    approved_by_user_id BIGINT,
    added_by_user_id    BIGINT,
    created_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT service_order_items_service_order_id_foreign
        FOREIGN KEY (service_order_id) REFERENCES service_orders (id) ON DELETE RESTRICT,
    CONSTRAINT service_order_items_service_type_id_foreign
        FOREIGN KEY (service_type_id) REFERENCES service_types (id) ON DELETE RESTRICT,
    CONSTRAINT service_order_items_part_id_foreign
        FOREIGN KEY (part_id) REFERENCES parts (id) ON DELETE RESTRICT,
    CONSTRAINT service_order_items_approved_by_user_id_foreign
        FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT service_order_items_added_by_user_id_foreign
        FOREIGN KEY (added_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT service_order_items_source_check
        CHECK (num_nonnulls(service_type_id, part_id) = 1),
    CONSTRAINT service_order_items_quantity_check
        CHECK (quantity > 0),
    CONSTRAINT service_order_items_unit_price_check
        CHECK (unit_price_mxn >= 0),
    CONSTRAINT service_order_items_total_check
        CHECK (applied_cost_mxn = round(quantity * unit_price_mxn, 2)),
    CONSTRAINT service_order_items_approval_status_check
        CHECK (approval_status IN ('approved', 'pending', 'rejected'))
);
CREATE INDEX service_order_items_service_order_id_index ON service_order_items (service_order_id);
CREATE INDEX service_order_items_service_type_id_index  ON service_order_items (service_type_id);
CREATE INDEX service_order_items_part_id_index          ON service_order_items (part_id);
CREATE INDEX service_order_items_service_order_id_approval_status_index ON service_order_items (service_order_id, approval_status);

CREATE TABLE service_order_histories (
    id               BIGSERIAL PRIMARY KEY,
    service_order_id BIGINT NOT NULL,
    from_status_id   BIGINT,
    to_status_id     BIGINT NOT NULL,
    user_id          BIGINT,
    notes            TEXT,
    created_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT service_order_histories_service_order_id_foreign
        FOREIGN KEY (service_order_id) REFERENCES service_orders (id) ON DELETE RESTRICT,
    CONSTRAINT service_order_histories_from_status_id_foreign
        FOREIGN KEY (from_status_id) REFERENCES service_order_statuses (id) ON DELETE RESTRICT,
    CONSTRAINT service_order_histories_to_status_id_foreign
        FOREIGN KEY (to_status_id) REFERENCES service_order_statuses (id) ON DELETE RESTRICT,
    CONSTRAINT service_order_histories_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT service_order_histories_transition_check
        CHECK (from_status_id IS NULL OR from_status_id <> to_status_id)
);
CREATE INDEX service_order_histories_service_order_id_created_at_index ON service_order_histories (service_order_id, created_at);
CREATE INDEX service_order_histories_user_id_index ON service_order_histories (user_id);

CREATE TABLE invoices (
    id               BIGSERIAL PRIMARY KEY,
    sale_id          BIGINT,
    service_order_id BIGINT,
    customer_id      BIGINT NOT NULL,
    fiscal_uuid      UUID,
    subtotal_mxn     NUMERIC(12,2) NOT NULL,
    tax_mxn          NUMERIC(12,2) NOT NULL,
    total_mxn        NUMERIC(12,2) NOT NULL,
    status           VARCHAR(15)   NOT NULL DEFAULT 'pending',
    pdf_path         VARCHAR(255),
    xml_path         VARCHAR(255),
    issued_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    cancelled_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    created_at       TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at       TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT invoices_fiscal_uuid_unique UNIQUE (fiscal_uuid),
    CONSTRAINT invoices_sale_id_foreign
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
    CONSTRAINT invoices_service_order_id_foreign
        FOREIGN KEY (service_order_id) REFERENCES service_orders (id) ON DELETE RESTRICT,
    CONSTRAINT invoices_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT invoices_source_check
        CHECK (num_nonnulls(sale_id, service_order_id) = 1),
    CONSTRAINT invoices_amounts_check
        CHECK (subtotal_mxn >= 0 AND tax_mxn >= 0 AND total_mxn = subtotal_mxn + tax_mxn),
    CONSTRAINT invoices_status_check
        CHECK (status IN ('pending', 'partially_paid', 'paid', 'cancelled')),
    CONSTRAINT invoices_cancelled_check
        CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL))
);
CREATE INDEX invoices_customer_id_index ON invoices (customer_id);
CREATE INDEX invoices_status_index      ON invoices (status);
-- Solo una factura vigente por venta y por orden; una cancelada permite emitir otra.
CREATE UNIQUE INDEX invoices_sale_id_active_unique
    ON invoices (sale_id) WHERE sale_id IS NOT NULL AND status <> 'cancelled';
CREATE UNIQUE INDEX invoices_service_order_id_active_unique
    ON invoices (service_order_id) WHERE service_order_id IS NOT NULL AND status <> 'cancelled';

CREATE TABLE payments (
    id                  BIGSERIAL PRIMARY KEY,
    invoice_id          BIGINT NOT NULL,
    amount_mxn          NUMERIC(12,2) NOT NULL,
    method              VARCHAR(15)   NOT NULL,
    reference           VARCHAR(100),
    paid_at             TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    received_by_user_id BIGINT,
    created_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT payments_invoice_id_foreign
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
    CONSTRAINT payments_received_by_user_id_foreign
        FOREIGN KEY (received_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT payments_amount_check
        CHECK (amount_mxn > 0),
    CONSTRAINT payments_method_check
        CHECK (method IN ('cash', 'card', 'transfer', 'check', 'financing', 'trade_in'))
);
CREATE INDEX payments_invoice_id_index ON payments (invoice_id);
CREATE INDEX payments_paid_at_index    ON payments (paid_at);

CREATE TABLE sale_addons (
    id             BIGSERIAL PRIMARY KEY,
    sale_id        BIGINT NOT NULL,
    part_id        BIGINT,
    description    VARCHAR(255)  NOT NULL,
    quantity       NUMERIC(10,2) NOT NULL DEFAULT '1',
    unit_price_mxn NUMERIC(10,2) NOT NULL,
    total_mxn      NUMERIC(12,2) NOT NULL,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT sale_addons_sale_id_foreign
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
    CONSTRAINT sale_addons_part_id_foreign
        FOREIGN KEY (part_id) REFERENCES parts (id) ON DELETE RESTRICT,
    CONSTRAINT sale_addons_quantity_check   CHECK (quantity > 0),
    CONSTRAINT sale_addons_unit_price_check CHECK (unit_price_mxn >= 0),
    CONSTRAINT sale_addons_total_check      CHECK (total_mxn = round(quantity * unit_price_mxn, 2))
);
CREATE INDEX sale_addons_sale_id_index ON sale_addons (sale_id);
CREATE INDEX sale_addons_part_id_index ON sale_addons (part_id);

CREATE TABLE sale_trade_ins (
    id                  BIGSERIAL PRIMARY KEY,
    sale_id             BIGINT NOT NULL,
    car_id              BIGINT,
    vin                 VARCHAR(17)   NOT NULL,
    brand               VARCHAR(50)   NOT NULL,
    model               VARCHAR(50)   NOT NULL,
    model_year          INTEGER       NOT NULL,
    mileage             INTEGER       NOT NULL,
    license_plate       VARCHAR(15),
    color               VARCHAR(30),
    valuation_price_mxn NUMERIC(12,2) NOT NULL,
    condition_notes     TEXT,
    created_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT sale_trade_ins_sale_id_foreign
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
    CONSTRAINT sale_trade_ins_car_id_foreign
        FOREIGN KEY (car_id) REFERENCES cars (id) ON DELETE RESTRICT,
    CONSTRAINT sale_trade_ins_vin_check       CHECK (vin ~ '^[A-HJ-NPR-Z0-9]{17}$'),
    CONSTRAINT sale_trade_ins_mileage_check   CHECK (mileage >= 0),
    CONSTRAINT sale_trade_ins_valuation_check CHECK (valuation_price_mxn >= 0)
);
CREATE INDEX sale_trade_ins_sale_id_index ON sale_trade_ins (sale_id);
CREATE INDEX sale_trade_ins_car_id_index  ON sale_trade_ins (car_id);
CREATE INDEX sale_trade_ins_vin_index     ON sale_trade_ins (vin);

CREATE TABLE sale_financings (
    id                  BIGSERIAL PRIMARY KEY,
    sale_id             BIGINT NOT NULL,
    lender_type         VARCHAR(10)   NOT NULL,
    bank_name           VARCHAR(100),
    status              VARCHAR(10)   NOT NULL DEFAULT 'pending',
    approved_at         TIMESTAMP(0) WITHOUT TIME ZONE,
    down_payment_mxn    NUMERIC(12,2) NOT NULL,
    financed_amount_mxn NUMERIC(12,2) NOT NULL,
    months              SMALLINT      NOT NULL,
    interest_rate       NUMERIC(5,2)  NOT NULL,
    created_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT sale_financings_sale_id_unique UNIQUE (sale_id),
    CONSTRAINT sale_financings_sale_id_foreign
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
    CONSTRAINT sale_financings_lender_type_check
        CHECK (lender_type IN ('bank', 'internal')),
    CONSTRAINT sale_financings_bank_name_check
        CHECK (lender_type <> 'bank' OR bank_name IS NOT NULL),
    CONSTRAINT sale_financings_status_check
        CHECK (status IN ('pending', 'approved', 'rejected', 'disbursed')),
    CONSTRAINT sale_financings_approved_check
        CHECK ((status IN ('approved', 'disbursed')) = (approved_at IS NOT NULL)),
    CONSTRAINT sale_financings_down_payment_check
        CHECK (down_payment_mxn >= 0),
    CONSTRAINT sale_financings_financed_amount_check
        CHECK (financed_amount_mxn > 0),
    CONSTRAINT sale_financings_months_check
        CHECK (months BETWEEN 1 AND 120),
    CONSTRAINT sale_financings_interest_rate_check
        CHECK (interest_rate >= 0 AND interest_rate <= 100)
);

CREATE TABLE sale_transfers (
    id                    BIGSERIAL PRIMARY KEY,
    sale_id               BIGINT NOT NULL,
    from_employee_id      BIGINT NOT NULL,
    to_employee_id        BIGINT NOT NULL,
    reason                TEXT   NOT NULL,
    authorized_by_user_id BIGINT,
    created_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT sale_transfers_sale_id_foreign
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
    CONSTRAINT sale_transfers_from_employee_id_foreign
        FOREIGN KEY (from_employee_id) REFERENCES employees (id) ON DELETE RESTRICT,
    CONSTRAINT sale_transfers_to_employee_id_foreign
        FOREIGN KEY (to_employee_id) REFERENCES employees (id) ON DELETE RESTRICT,
    CONSTRAINT sale_transfers_authorized_by_user_id_foreign
        FOREIGN KEY (authorized_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT sale_transfers_distinct_employees_check
        CHECK (from_employee_id <> to_employee_id)
);
CREATE INDEX sale_transfers_sale_id_created_at_index ON sale_transfers (sale_id, created_at);

CREATE TABLE commission_rules (
    id             BIGSERIAL PRIMARY KEY,
    branch_id      BIGINT,
    car_condition  VARCHAR(4)   NOT NULL DEFAULT 'all',
    rate           NUMERIC(5,4) NOT NULL,
    effective_from DATE         NOT NULL,
    is_active      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT commission_rules_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE RESTRICT,
    CONSTRAINT commission_rules_car_condition_check
        CHECK (car_condition IN ('new', 'used', 'all')),
    CONSTRAINT commission_rules_rate_check
        CHECK (rate >= 0 AND rate <= 1)
);
-- Una sola regla por sucursal, condición y fecha. NULLS NOT DISTINCT rechaza también dos reglas globales iguales.
CREATE UNIQUE INDEX commission_rules_branch_condition_from_unique
    ON commission_rules (branch_id, car_condition, effective_from) NULLS NOT DISTINCT;

CREATE TABLE sale_commissions (
    id                    BIGSERIAL PRIMARY KEY,
    sale_id               BIGINT NOT NULL,
    employee_id           BIGINT NOT NULL,
    commission_rule_id    BIGINT NOT NULL,
    role                  VARCHAR(10)   NOT NULL,
    percentage            NUMERIC(5,2)  NOT NULL,
    base_amount_mxn       NUMERIC(12,2) NOT NULL,
    commission_rate       NUMERIC(5,4)  NOT NULL,
    commission_amount_mxn NUMERIC(12,2) NOT NULL,
    created_at            TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at            TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT sale_commissions_sale_id_employee_id_unique UNIQUE (sale_id, employee_id),
    CONSTRAINT sale_commissions_sale_id_foreign
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
    CONSTRAINT sale_commissions_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE RESTRICT,
    CONSTRAINT sale_commissions_commission_rule_id_foreign
        FOREIGN KEY (commission_rule_id) REFERENCES commission_rules (id) ON DELETE RESTRICT,
    CONSTRAINT sale_commissions_role_check
        CHECK (role IN ('sole', 'capturer', 'closer')),
    CONSTRAINT sale_commissions_percentage_check
        CHECK (percentage > 0 AND percentage <= 100),
    CONSTRAINT sale_commissions_base_check
        CHECK (base_amount_mxn >= 0),
    CONSTRAINT sale_commissions_rate_check
        CHECK (commission_rate >= 0 AND commission_rate <= 1),
    CONSTRAINT sale_commissions_amount_check
        CHECK (commission_amount_mxn >= 0 AND commission_amount_mxn <= base_amount_mxn),
    CONSTRAINT sale_commissions_formula_check
        CHECK (abs(commission_amount_mxn - base_amount_mxn * commission_rate * percentage / 100) <= 0.01)
);
CREATE INDEX sale_commissions_employee_id_index ON sale_commissions (employee_id);
CREATE INDEX sale_commissions_commission_rule_id_index ON sale_commissions (commission_rule_id);

CREATE TABLE employee_specializations (
    id              BIGSERIAL PRIMARY KEY,
    employee_id     BIGINT      NOT NULL,
    vehicle_type_id BIGINT,
    car_condition   VARCHAR(5)  NOT NULL DEFAULT 'all',
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at      TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT employee_specializations_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
    CONSTRAINT employee_specializations_vehicle_type_id_foreign
        FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id) ON DELETE RESTRICT,
    CONSTRAINT employee_specializations_car_condition_check
        CHECK (car_condition IN ('new', 'used', 'all'))
);
-- Una combinación no se repite por empleado. NULLS NOT DISTINCT rechaza también dos filas iguales con tipo NULL.
CREATE UNIQUE INDEX employee_specializations_employee_type_condition_unique
    ON employee_specializations (employee_id, vehicle_type_id, car_condition) NULLS NOT DISTINCT;

-- ==========================================================
-- DICCIONARIO DE DATOS: comentario de cada tabla y de cada columna.
-- Generado desde las migraciones (->comment()). Consultar: \d+ <tabla>
-- ==========================================================

COMMENT ON TABLE users IS 'Cuentas de acceso al sistema';
COMMENT ON COLUMN users.id IS 'Identificador único del usuario';
COMMENT ON COLUMN users.name IS 'Nombre para mostrar';
COMMENT ON COLUMN users.email IS 'Correo electrónico que se usa para iniciar sesión. No se repite';
COMMENT ON COLUMN users.email_verified_at IS 'Fecha y hora en que se verificó el correo. NULL si no se ha verificado';
COMMENT ON COLUMN users.password IS 'Contraseña con hash. Nunca se guarda en texto plano';
COMMENT ON COLUMN users.remember_token IS 'Token de sesión recordada';
COMMENT ON COLUMN users.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN users.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE branches IS 'Sucursales de la agencia';
COMMENT ON COLUMN branches.id IS 'Identificador único de la sucursal';
COMMENT ON COLUMN branches.name IS 'Nombre de la sucursal';
COMMENT ON COLUMN branches.city IS 'Ciudad donde se ubica';
COMMENT ON COLUMN branches.address IS 'Dirección completa';
COMMENT ON COLUMN branches.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN branches.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE customers IS 'Clientes que compran autos o ingresan vehículos al taller';
COMMENT ON COLUMN customers.id IS 'Identificador único del cliente';
COMMENT ON COLUMN customers.first_name IS 'Nombre(s) del cliente';
COMMENT ON COLUMN customers.last_name IS 'Apellido(s) del cliente';
COMMENT ON COLUMN customers.email IS 'Correo electrónico. No se repite entre clientes';
COMMENT ON COLUMN customers.phone IS 'Teléfono de contacto';
COMMENT ON COLUMN customers.city IS 'Ciudad de residencia';
COMMENT ON COLUMN customers.registration_date IS 'Fecha de alta del cliente. Por defecto 2023-01-01';
COMMENT ON COLUMN customers.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN customers.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE job_titles IS 'Catálogo de puestos de los empleados';
COMMENT ON COLUMN job_titles.id IS 'Identificador único del puesto';
COMMENT ON COLUMN job_titles.code IS 'Código del puesto, de dos dígitos y estable. 01: vendedor, 02: gerente, 03: asesor de servicio, 04: jefe mecánico, 05: mecánico';
COMMENT ON COLUMN job_titles.name IS 'Nombre del puesto para mostrar en la interfaz. Por ejemplo Vendedor';
COMMENT ON COLUMN job_titles.is_active IS '1: puesto en uso, 0: dado de baja. Se desactiva en lugar de borrarse porque los empleados lo referencian';
COMMENT ON COLUMN job_titles.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN job_titles.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE job_title_role IS 'Roles sugeridos para cada puesto. No otorga permisos: solo precarga el rol al crear el usuario de un empleado';
COMMENT ON COLUMN job_title_role.job_title_id IS 'Puesto (job_titles.id)';
COMMENT ON COLUMN job_title_role.role_id IS 'Rol sugerido para el puesto (roles.id)';
COMMENT ON COLUMN job_title_role.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN job_title_role.updated_at IS 'Fecha y hora de la última modificación del registro';
COMMENT ON TABLE employees IS 'Empleados de la agencia. Nunca se borran: se desactivan con is_active';
COMMENT ON COLUMN employees.id IS 'Identificador único del empleado';
COMMENT ON COLUMN employees.first_name IS 'Nombre(s) del empleado';
COMMENT ON COLUMN employees.last_name IS 'Apellido(s) del empleado';
COMMENT ON COLUMN employees.job_title_id IS 'Puesto del empleado (job_titles.id)';
COMMENT ON COLUMN employees.branch_id IS 'Sucursal en la que trabaja (branches.id)';
COMMENT ON COLUMN employees.user_id IS 'Cuenta de acceso al sistema (users.id), relación 1 a 1. NULL si el empleado no usa el sistema';
COMMENT ON COLUMN employees.is_active IS '1: activo, 0: dado de baja. Un empleado dado de baja conserva su historial pero no recibe ventas';
COMMENT ON COLUMN employees.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN employees.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE vehicle_types IS 'Catálogo de tipos de vehículo (carrocería)';
COMMENT ON COLUMN vehicle_types.id IS 'Identificador único del tipo de vehículo';
COMMENT ON COLUMN vehicle_types.code IS 'Código estable del tipo. auto: auto, suv: SUV, pickup: camioneta de caja abierta, van: van o minivan, truck: camión';
COMMENT ON COLUMN vehicle_types.name IS 'Nombre del tipo para mostrar en la interfaz. Por ejemplo SUV';
COMMENT ON COLUMN vehicle_types.is_active IS '1: tipo en uso, 0: dado de baja. Se desactiva en lugar de borrarse porque los autos lo referencian';
COMMENT ON COLUMN vehicle_types.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN vehicle_types.updated_at IS 'Fecha y hora de la última modificación del registro';
COMMENT ON TABLE cars IS 'Vehículos: inventario de la agencia y autos que pasan por el taller';
COMMENT ON COLUMN cars.id IS 'Identificador único del auto';
COMMENT ON COLUMN cars.brand IS 'Marca. Por ejemplo Nissan';
COMMENT ON COLUMN cars.model IS 'Submarca o línea. Por ejemplo Versa';
COMMENT ON COLUMN cars.vehicle_type_id IS 'Tipo de vehículo (vehicle_types.id). Por ejemplo SUV';
COMMENT ON COLUMN cars.model_year IS 'Año del modelo';
COMMENT ON COLUMN cars.mileage IS 'Último kilometraje registrado, en km. Se actualiza con cada orden de servicio';
COMMENT ON COLUMN cars.price_mxn IS 'Precio de lista en pesos mexicanos, con IVA incluido';
COMMENT ON COLUMN cars.status IS 'Estatus en el inventario. new: nuevo, used: usado, sold: vendido';
COMMENT ON COLUMN cars.branch_id IS 'Sucursal donde está el auto (branches.id)';
COMMENT ON COLUMN cars.vin IS 'Número de serie (VIN): 17 caracteres sin I, O ni Q. NULL en autos anteriores al taller';
COMMENT ON COLUMN cars.license_plate IS 'Placas del vehículo';
COMMENT ON COLUMN cars.color IS 'Color del vehículo';
COMMENT ON COLUMN cars.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN cars.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE sales IS 'Ventas de vehículos. Una venta nace en borrador y se cierra o se cancela';
COMMENT ON COLUMN sales.id IS 'Identificador único de la venta';
COMMENT ON COLUMN sales.car_id IS 'Auto vendido (cars.id)';
COMMENT ON COLUMN sales.customer_id IS 'Cliente que compra (customers.id)';
COMMENT ON COLUMN sales.employee_id IS 'Vendedor dueño actual de la venta (employees.id). Cambia si la venta se transfiere';
COMMENT ON COLUMN sales.branch_id IS 'Sucursal que registra la venta (branches.id)';
COMMENT ON COLUMN sales.status IS 'Estatus de la venta. draft: borrador, completed: cerrada, cancelled: cancelada';
COMMENT ON COLUMN sales.sale_date IS 'Fecha de la venta. En borrador es la de captura y se actualiza al cerrarla';
COMMENT ON COLUMN sales.car_status_at_sale IS 'Estatus que tenía el auto al cerrar la venta. new: nuevo, used: usado. Permite restaurarlo si la venta se cancela. NULL mientras es borrador';
COMMENT ON COLUMN sales.list_price_mxn IS 'Precio de lista del auto al momento de la venta (copia de cars.price_mxn), con IVA';
COMMENT ON COLUMN sales.discount_mxn IS 'Descuento aplicado, en pesos. Entre 0 y el precio de lista';
COMMENT ON COLUMN sales.discount_approved_by_user_id IS 'Gerente que autorizó el descuento (users.id). Obligatorio en la aplicación si supera el 10 por ciento';
COMMENT ON COLUMN sales.final_price_mxn IS 'Precio final del vehículo con IVA = precio de lista menos descuento. No incluye accesorios';
COMMENT ON COLUMN sales.tax_mxn IS 'IVA contenido en el precio final, en pesos (desglose, no se suma al precio)';
COMMENT ON COLUMN sales.net_sale_price_mxn IS 'Precio neto de la venta antes de impuestos, en pesos: auto más accesorios sin IVA, igual al subtotal de la factura. Se calcula al cerrar la venta y es la base de la comisión. NULL mientras es borrador';
COMMENT ON COLUMN sales.notes IS 'Observaciones de la venta';
COMMENT ON COLUMN sales.superseded_at IS 'Fecha y hora en que la venta dejó de estar vigente porque el auto regresó al inventario como auto a cuenta. NULL mientras está vigente';
COMMENT ON COLUMN sales.cancelled_at IS 'Fecha y hora de cancelación. NULL si no está cancelada';
COMMENT ON COLUMN sales.cancellation_reason IS 'Motivo de la cancelación. NULL si no está cancelada';
COMMENT ON COLUMN sales.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN sales.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE service_types IS 'Catálogo de servicios de mano de obra del taller';
COMMENT ON COLUMN service_types.id IS 'Identificador único del tipo de servicio';
COMMENT ON COLUMN service_types.name IS 'Nombre del servicio. Por ejemplo Cambio de aceite y filtro';
COMMENT ON COLUMN service_types.base_price_mxn IS 'Precio base en pesos, sin IVA';
COMMENT ON COLUMN service_types.is_active IS '1: activo, 0: dado de baja. Se desactiva en lugar de borrarse porque las partidas lo referencian';
COMMENT ON COLUMN service_types.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN service_types.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE service_order_statuses IS 'Catálogo de estatus de la máquina de estados de las órdenes de servicio';
COMMENT ON COLUMN service_order_statuses.id IS 'Identificador único del estatus';
COMMENT ON COLUMN service_order_statuses.code IS 'Identificador estable que usa el código. draft, pending, pending_approval, in_progress, completed, delivered, cancelled';
COMMENT ON COLUMN service_order_statuses.display_name IS 'Texto para mostrar en la interfaz';
COMMENT ON COLUMN service_order_statuses.color IS 'Color hexadecimal para la interfaz. Por ejemplo #4CAF50';
COMMENT ON COLUMN service_order_statuses.sort_order IS 'Posición al ordenar los estatus';
COMMENT ON COLUMN service_order_statuses.is_final IS '1: estatus final, la orden ya no cambia, 0: la orden puede seguir avanzando';
COMMENT ON COLUMN service_order_statuses.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN service_order_statuses.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE parts IS 'Refacciones, insumos y accesorios con control de existencias';
COMMENT ON COLUMN parts.id IS 'Identificador único de la refacción';
COMMENT ON COLUMN parts.sku IS 'Clave interna del artículo. No se repite';
COMMENT ON COLUMN parts.name IS 'Nombre de la refacción';
COMMENT ON COLUMN parts.unit IS 'Unidad de medida. pza: pieza, l: litro, kg: kilogramo';
COMMENT ON COLUMN parts.cost_mxn IS 'Costo para la agencia, en pesos';
COMMENT ON COLUMN parts.price_mxn IS 'Precio de venta al cliente, en pesos y sin IVA';
COMMENT ON COLUMN parts.stock IS 'Existencias disponibles. Admite fracciones (litros). Nunca negativas';
COMMENT ON COLUMN parts.is_active IS '1: activa, 0: dada de baja';
COMMENT ON COLUMN parts.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN parts.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE service_orders IS 'Órdenes de servicio del taller';
COMMENT ON COLUMN service_orders.id IS 'Identificador único de la orden';
COMMENT ON COLUMN service_orders.car_id IS 'Auto que ingresa al taller (cars.id)';
COMMENT ON COLUMN service_orders.customer_id IS 'Cliente que paga (customers.id). NULL cuando el auto es de la agencia';
COMMENT ON COLUMN service_orders.employee_id IS 'Empleado que atiende la orden (employees.id)';
COMMENT ON COLUMN service_orders.branch_id IS 'Sucursal del taller que atiende (branches.id)';
COMMENT ON COLUMN service_orders.status_id IS 'Estatus actual de la orden (service_order_statuses.id)';
COMMENT ON COLUMN service_orders.check_in_date IS 'Fecha de ingreso del auto al taller';
COMMENT ON COLUMN service_orders.estimated_delivery_date IS 'Fecha de entrega prometida al cliente. No puede ser anterior al ingreso';
COMMENT ON COLUMN service_orders.delivery_date IS 'Fecha real de entrega. No puede ser anterior al ingreso';
COMMENT ON COLUMN service_orders.mileage_in IS 'Kilometraje de entrada, en km. La aplicación lo exige; es NULL en las órdenes históricas';
COMMENT ON COLUMN service_orders.mileage_out IS 'Kilometraje de salida, en km. No puede ser menor al de entrada';
COMMENT ON COLUMN service_orders.customer_notes IS 'Problemas reportados por el cliente';
COMMENT ON COLUMN service_orders.internal_notes IS 'Notas internas del taller, no visibles al cliente';
COMMENT ON COLUMN service_orders.contact_phone IS 'Teléfono del cliente al momento de la orden (copia)';
COMMENT ON COLUMN service_orders.contact_email IS 'Correo del cliente al momento de la orden (copia)';
COMMENT ON COLUMN service_orders.total_mxn IS 'Suma de las partidas aprobadas, en pesos y sin IVA';
COMMENT ON COLUMN service_orders.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN service_orders.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE service_order_items IS 'Partidas de una orden de servicio: mano de obra o refacciones';
COMMENT ON COLUMN service_order_items.id IS 'Identificador único de la partida';
COMMENT ON COLUMN service_order_items.service_order_id IS 'Orden a la que pertenece (service_orders.id)';
COMMENT ON COLUMN service_order_items.service_type_id IS 'Servicio de mano de obra (service_types.id). Exactamente uno entre este y part_id';
COMMENT ON COLUMN service_order_items.part_id IS 'Refacción usada (parts.id). Exactamente uno entre este y service_type_id';
COMMENT ON COLUMN service_order_items.description IS 'Nombre del servicio o refacción al momento de la orden (copia)';
COMMENT ON COLUMN service_order_items.quantity IS 'Cantidad. Admite fracciones, por ejemplo litros';
COMMENT ON COLUMN service_order_items.unit_price_mxn IS 'Precio unitario en pesos y sin IVA';
COMMENT ON COLUMN service_order_items.applied_cost_mxn IS 'Total de la partida en pesos = cantidad por precio unitario';
COMMENT ON COLUMN service_order_items.approval_status IS 'Aprobación del cliente. approved: aprobada, pending: espera respuesta, rejected: rechazada';
COMMENT ON COLUMN service_order_items.approved_at IS 'Fecha y hora en que se registró la respuesta del cliente';
COMMENT ON COLUMN service_order_items.approved_by_user_id IS 'Usuario que registró la respuesta del cliente (users.id)';
COMMENT ON COLUMN service_order_items.added_by_user_id IS 'Usuario que agregó la partida (users.id)';
COMMENT ON COLUMN service_order_items.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN service_order_items.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE service_order_histories IS 'Bitácora de cambios de estatus de las órdenes. Solo se inserta, nunca se modifica';
COMMENT ON COLUMN service_order_histories.id IS 'Identificador único del movimiento';
COMMENT ON COLUMN service_order_histories.service_order_id IS 'Orden que cambió de estatus (service_orders.id)';
COMMENT ON COLUMN service_order_histories.from_status_id IS 'Estatus anterior (service_order_statuses.id). NULL en el alta de la orden';
COMMENT ON COLUMN service_order_histories.to_status_id IS 'Estatus nuevo (service_order_statuses.id)';
COMMENT ON COLUMN service_order_histories.user_id IS 'Usuario que hizo el cambio (users.id). NULL si el usuario fue eliminado';
COMMENT ON COLUMN service_order_histories.notes IS 'Notas del cambio. Obligatorias al cancelar o rechazar un extra';
COMMENT ON COLUMN service_order_histories.created_at IS 'Fecha y hora del cambio';

COMMENT ON TABLE invoices IS 'Facturas de ventas y de órdenes de servicio';
COMMENT ON COLUMN invoices.id IS 'Identificador único de la factura';
COMMENT ON COLUMN invoices.sale_id IS 'Venta facturada (sales.id). Exactamente una entre esta y service_order_id';
COMMENT ON COLUMN invoices.service_order_id IS 'Orden de servicio facturada (service_orders.id). Exactamente una entre esta y sale_id';
COMMENT ON COLUMN invoices.customer_id IS 'Cliente receptor de la factura (customers.id)';
COMMENT ON COLUMN invoices.fiscal_uuid IS 'Folio fiscal (UUID del CFDI). NULL hasta que el PAC timbra la factura';
COMMENT ON COLUMN invoices.subtotal_mxn IS 'Importe antes de IVA, en pesos';
COMMENT ON COLUMN invoices.tax_mxn IS 'IVA, en pesos';
COMMENT ON COLUMN invoices.total_mxn IS 'Total en pesos = subtotal más IVA';
COMMENT ON COLUMN invoices.status IS 'Estatus. pending: sin pagos, partially_paid: pagada en parte, paid: pagada, cancelled: cancelada';
COMMENT ON COLUMN invoices.pdf_path IS 'Ruta del PDF en el almacenamiento de archivos';
COMMENT ON COLUMN invoices.xml_path IS 'Ruta del XML en el almacenamiento de archivos';
COMMENT ON COLUMN invoices.issued_at IS 'Fecha y hora de emisión (timbrado). NULL mientras es un borrador';
COMMENT ON COLUMN invoices.cancelled_at IS 'Fecha y hora de cancelación. NULL si no está cancelada';
COMMENT ON COLUMN invoices.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN invoices.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE payments IS 'Pagos aplicados a una factura. Una factura admite varios';
COMMENT ON COLUMN payments.id IS 'Identificador único del pago';
COMMENT ON COLUMN payments.invoice_id IS 'Factura que se paga (invoices.id)';
COMMENT ON COLUMN payments.amount_mxn IS 'Monto del pago en pesos. Mayor que cero';
COMMENT ON COLUMN payments.method IS 'Forma de pago. cash: efectivo, card: tarjeta, transfer: transferencia, check: cheque, financing: depósito del crédito, trade_in: auto recibido a cuenta';
COMMENT ON COLUMN payments.reference IS 'Folio del terminal, número de transferencia o de cheque';
COMMENT ON COLUMN payments.paid_at IS 'Fecha y hora en que se recibió el pago';
COMMENT ON COLUMN payments.received_by_user_id IS 'Usuario que recibió el pago (users.id)';
COMMENT ON COLUMN payments.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN payments.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE sale_addons IS 'Accesorios y servicios vendidos junto con el auto: placas, seguros, tapetes';
COMMENT ON COLUMN sale_addons.id IS 'Identificador único del accesorio';
COMMENT ON COLUMN sale_addons.sale_id IS 'Venta a la que pertenece (sales.id)';
COMMENT ON COLUMN sale_addons.part_id IS 'Artículo del inventario (parts.id). NULL si no es de inventario, por ejemplo un seguro';
COMMENT ON COLUMN sale_addons.description IS 'Nombre del accesorio al momento de la venta (copia)';
COMMENT ON COLUMN sale_addons.quantity IS 'Cantidad. Mayor que cero';
COMMENT ON COLUMN sale_addons.unit_price_mxn IS 'Precio unitario en pesos, con IVA';
COMMENT ON COLUMN sale_addons.total_mxn IS 'Total en pesos = cantidad por precio unitario';
COMMENT ON COLUMN sale_addons.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN sale_addons.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE sale_trade_ins IS 'Autos que el cliente entrega como parte de pago de una venta';
COMMENT ON COLUMN sale_trade_ins.id IS 'Identificador único del auto a cuenta';
COMMENT ON COLUMN sale_trade_ins.sale_id IS 'Venta en la que se recibe (sales.id)';
COMMENT ON COLUMN sale_trade_ins.car_id IS 'Auto en el inventario (cars.id). NULL mientras la venta es borrador; se asigna al cerrarla';
COMMENT ON COLUMN sale_trade_ins.vin IS 'Número de serie (VIN) del auto recibido: 17 caracteres sin I, O ni Q';
COMMENT ON COLUMN sale_trade_ins.brand IS 'Marca del auto recibido';
COMMENT ON COLUMN sale_trade_ins.model IS 'Submarca o línea del auto recibido';
COMMENT ON COLUMN sale_trade_ins.model_year IS 'Año del modelo del auto recibido';
COMMENT ON COLUMN sale_trade_ins.mileage IS 'Kilometraje del auto al recibirlo, en km';
COMMENT ON COLUMN sale_trade_ins.license_plate IS 'Placas del auto recibido';
COMMENT ON COLUMN sale_trade_ins.color IS 'Color del auto recibido';
COMMENT ON COLUMN sale_trade_ins.valuation_price_mxn IS 'Valor al que se recibe el auto, en pesos. Se resta de lo que paga el cliente';
COMMENT ON COLUMN sale_trade_ins.condition_notes IS 'Estado físico y mecánico del auto recibido';
COMMENT ON COLUMN sale_trade_ins.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN sale_trade_ins.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE sale_financings IS 'Financiamiento de una venta a crédito. Máximo uno por venta';
COMMENT ON COLUMN sale_financings.id IS 'Identificador único del financiamiento';
COMMENT ON COLUMN sale_financings.sale_id IS 'Venta financiada (sales.id). Una venta tiene a lo mucho un financiamiento';
COMMENT ON COLUMN sale_financings.lender_type IS 'Quién financia. bank: banco, internal: crédito de la agencia';
COMMENT ON COLUMN sale_financings.bank_name IS 'Nombre del banco. Obligatorio si lender_type es bank';
COMMENT ON COLUMN sale_financings.status IS 'Estatus del crédito. pending: en trámite, approved: aprobado, rejected: rechazado, disbursed: depositado';
COMMENT ON COLUMN sale_financings.approved_at IS 'Fecha y hora de aprobación. NULL mientras está en trámite o rechazado';
COMMENT ON COLUMN sale_financings.down_payment_mxn IS 'Enganche pactado, en pesos';
COMMENT ON COLUMN sale_financings.financed_amount_mxn IS 'Monto financiado, en pesos. Mayor que cero';
COMMENT ON COLUMN sale_financings.months IS 'Plazo en meses, de 1 a 120';
COMMENT ON COLUMN sale_financings.interest_rate IS 'Tasa de interés anual, en porcentaje. Por ejemplo 12.50';
COMMENT ON COLUMN sale_financings.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN sale_financings.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE sale_transfers IS 'Historial de cambios de vendedor de una venta. Solo se inserta, nunca se modifica';
COMMENT ON COLUMN sale_transfers.id IS 'Identificador único de la transferencia';
COMMENT ON COLUMN sale_transfers.sale_id IS 'Venta transferida (sales.id)';
COMMENT ON COLUMN sale_transfers.from_employee_id IS 'Vendedor que entrega la venta (employees.id)';
COMMENT ON COLUMN sale_transfers.to_employee_id IS 'Vendedor que la recibe (employees.id). Distinto del anterior';
COMMENT ON COLUMN sale_transfers.reason IS 'Motivo de la transferencia: renuncia, vacaciones, reasignación';
COMMENT ON COLUMN sale_transfers.authorized_by_user_id IS 'Gerente que autoriza (users.id). NULL cuando el dueño transfiere su propia venta en borrador';
COMMENT ON COLUMN sale_transfers.created_at IS 'Fecha y hora de la transferencia';

COMMENT ON TABLE commission_rules IS 'Reglas de comisión por sucursal y condición del auto. Una regla nueva reemplaza a la anterior según su fecha de vigencia';
COMMENT ON COLUMN commission_rules.id IS 'Identificador único de la regla';
COMMENT ON COLUMN commission_rules.branch_id IS 'Sucursal a la que aplica (branches.id). NULL: regla global para todas las sucursales';
COMMENT ON COLUMN commission_rules.car_condition IS 'Condición del auto a la que aplica. new: nuevo, used: usado, all: ambos';
COMMENT ON COLUMN commission_rules.rate IS 'Tasa de comisión como fracción del precio neto de la venta. 0.0150 equivale a 1.5 por ciento. Entre 0 y 1';
COMMENT ON COLUMN commission_rules.effective_from IS 'Fecha desde la que la regla es vigente. Aplica a ventas cerradas en esa fecha o después, hasta que otra más reciente la reemplace';
COMMENT ON COLUMN commission_rules.is_active IS '1: regla en uso, 0: desactivada y no se aplica';
COMMENT ON COLUMN commission_rules.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN commission_rules.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE sale_commissions IS 'Reparto de la comisión de una venta entre quien la captura y quien la cierra';
COMMENT ON COLUMN sale_commissions.id IS 'Identificador único de la comisión';
COMMENT ON COLUMN sale_commissions.sale_id IS 'Venta que genera la comisión (sales.id)';
COMMENT ON COLUMN sale_commissions.employee_id IS 'Vendedor que recibe la comisión (employees.id)';
COMMENT ON COLUMN sale_commissions.commission_rule_id IS 'Regla de comisión que se aplicó (commission_rules.id)';
COMMENT ON COLUMN sale_commissions.role IS 'Participación. sole: vendió sin transferencia, capturer: quien capturó la venta, closer: quien la cerró';
COMMENT ON COLUMN sale_commissions.percentage IS 'Parte del total de la comisión de la venta que le corresponde a este vendedor, de 0.01 a 100. Los de una venta deben sumar 100';
COMMENT ON COLUMN sale_commissions.base_amount_mxn IS 'Base de cálculo en pesos: precio neto de la venta antes de impuestos, copia de sales.net_sale_price_mxn al cerrar la venta';
COMMENT ON COLUMN sale_commissions.commission_rate IS 'Tasa aplicada como fracción, copia de la regla al cerrar la venta. 0.0150 equivale a 1.5 por ciento';
COMMENT ON COLUMN sale_commissions.commission_amount_mxn IS 'Importe de la comisión en pesos = base x tasa x porcentaje de reparto, con un centavo de tolerancia por redondeo. Nunca mayor a la base';
COMMENT ON COLUMN sale_commissions.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN sale_commissions.updated_at IS 'Fecha y hora de la última modificación del registro';

COMMENT ON TABLE employee_specializations IS 'Con qué autos trabaja un empleado. Sin filas, el empleado atiende cualquier auto. Cada fila es una combinación permitida (tipo Y condición); varias filas se suman (O)';
COMMENT ON COLUMN employee_specializations.id IS 'Identificador único de la especialización';
COMMENT ON COLUMN employee_specializations.employee_id IS 'Empleado al que aplica (employees.id)';
COMMENT ON COLUMN employee_specializations.vehicle_type_id IS 'Tipo de vehículo permitido (vehicle_types.id). NULL: cualquier tipo';
COMMENT ON COLUMN employee_specializations.car_condition IS 'Condición del auto permitida. new: solo nuevos, used: solo usados, all: ambas';
COMMENT ON COLUMN employee_specializations.created_at IS 'Fecha y hora de creación del registro';
COMMENT ON COLUMN employee_specializations.updated_at IS 'Fecha y hora de la última modificación del registro';
-- Datos de referencia: puestos de los empleados. El code es de dos dígitos.
INSERT INTO job_titles (code, name, is_active, created_at, updated_at) VALUES
('01', 'Vendedor',           TRUE, NOW(), NOW()),
('02', 'Gerente',            TRUE, NOW(), NOW()),
('03', 'Asesor de servicio', TRUE, NOW(), NOW()),
('04', 'Jefe Mecánico',      TRUE, NOW(), NOW()),
('05', 'Mecánico',           TRUE, NOW(), NOW());

-- Datos de referencia: tipos de vehículo.
INSERT INTO vehicle_types (code, name, is_active, created_at, updated_at) VALUES
('auto',   'Auto',   TRUE, NOW(), NOW()),
('suv',    'SUV',    TRUE, NOW(), NOW()),
('pickup', 'Pickup', TRUE, NOW(), NOW()),
('van',    'Van',    TRUE, NOW(), NOW()),
('truck',  'Camión', TRUE, NOW(), NOW());

-- Datos de referencia: estatus de la máquina de estados de las órdenes de servicio.
INSERT INTO service_order_statuses (code, display_name, color, sort_order, is_final, created_at, updated_at) VALUES
('draft',            'Borrador',    '#9E9E9E', 10, FALSE, NOW(), NOW()),
('pending',          'Pendiente',   '#2196F3', 20, FALSE, NOW(), NOW()),
('pending_approval', 'Por aprobar', '#FF9800', 30, FALSE, NOW(), NOW()),
('in_progress',      'En proceso',  '#3F51B5', 40, FALSE, NOW(), NOW()),
('completed',        'Terminada',   '#4CAF50', 50, FALSE, NOW(), NOW()),
('delivered',        'Entregada',   '#009688', 60, TRUE,  NOW(), NOW()),
('cancelled',        'Cancelada',   '#F44336', 70, TRUE,  NOW(), NOW());
