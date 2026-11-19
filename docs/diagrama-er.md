# Diagrama ER

Base de datos `agencia` (PostgreSQL). Incluye las tablas del negocio y las de usuarios, roles, permisos y menú.

```mermaid
erDiagram
    %% Negocio
    BRANCHES ||--o{ EMPLOYEES : "employs"
    BRANCHES ||--o{ CARS : "stocks"
    CUSTOMERS ||--o{ SALES : "buys"
    EMPLOYEES ||--o{ SALES : "sells"
    CARS ||--o| SALES : "is sold once"
    CARS ||--o{ SERVICE_ORDERS : "enters workshop"
    CUSTOMERS |o--o{ SERVICE_ORDERS : "pays (optional)"
    EMPLOYEES ||--o{ SERVICE_ORDERS : "attends"
    SERVICE_ORDERS ||--|{ SERVICE_ORDER_ITEMS : "includes"
    SERVICE_TYPES ||--o{ SERVICE_ORDER_ITEMS : "is applied in"

    %% Usuarios, roles, permisos y menú
    USERS ||--o{ MODEL_HAS_ROLES : "has roles"
    ROLES ||--o{ MODEL_HAS_ROLES : "is assigned"
    ROLES ||--o{ ROLE_HAS_PERMISSIONS : "groups"
    PERMISSIONS ||--o{ ROLE_HAS_PERMISSIONS : "belongs to"
    USERS ||--o{ MODEL_HAS_PERMISSIONS : "has direct"
    PERMISSIONS ||--o{ MODEL_HAS_PERMISSIONS : "is assigned"
    PERMISSIONS |o--o{ MENU_ITEMS : "controls"
    MENU_ITEMS |o--o{ MENU_ITEMS : "parent of"

    BRANCHES {
        bigint id PK
        varchar name
        varchar city
        varchar address
    }

    CUSTOMERS {
        bigint id PK
        varchar first_name
        varchar last_name
        varchar email UK
        varchar phone
        varchar city
        date registration_date
    }

    EMPLOYEES {
        bigint id PK
        varchar first_name
        varchar last_name
        varchar job_title "seller, manager, service_advisor, mechanic"
        bigint branch_id FK
    }

    CARS {
        bigint id PK
        varchar brand
        varchar model
        int model_year
        int mileage
        numeric price_mxn
        varchar status "CHECK new, used, sold"
        bigint branch_id FK
    }

    SALES {
        bigint id PK
        bigint car_id FK, UK
        bigint customer_id FK
        bigint employee_id FK
        date sale_date
        numeric final_price_mxn
    }

    SERVICE_TYPES {
        bigint id PK
        varchar name
        numeric base_price_mxn
    }

    SERVICE_ORDERS {
        bigint id PK
        bigint car_id FK
        bigint customer_id FK
        bigint employee_id FK
        date check_in_date
        date delivery_date
        numeric total_mxn
    }

    SERVICE_ORDER_ITEMS {
        bigint id PK
        bigint service_order_id FK
        bigint service_type_id FK
        numeric applied_cost_mxn
    }

    USERS {
        bigint id PK
        varchar name
        varchar email UK
        varchar password "hash"
    }

    ROLES {
        bigint id PK
        varchar name UK
        varchar guard_name UK
    }

    PERMISSIONS {
        bigint id PK
        varchar name UK "branches.view_any"
        varchar guard_name UK "api"
    }

    MODEL_HAS_ROLES {
        bigint role_id PK, FK
        varchar model_type PK "App Models User"
        bigint model_id PK "id del usuario, sin FK"
    }

    MODEL_HAS_PERMISSIONS {
        bigint permission_id PK, FK
        varchar model_type PK "App Models User"
        bigint model_id PK "id del usuario, sin FK"
    }

    ROLE_HAS_PERMISSIONS {
        bigint permission_id PK, FK
        bigint role_id PK, FK
    }

    MENU_ITEMS {
        bigint id PK
        varchar semantic_key UK
        varchar display_name
        varchar route_name
        varchar semantic_icon
        varchar semantic_type "CHECK header, module, group, link"
        bigint permission_id FK "ON DELETE SET NULL"
        bigint parent_id FK "ON DELETE CASCADE"
        int order
        json metadata
        boolean is_active
    }
```

**Leyenda**
- `PK` llave primaria, `FK` llave foránea, `UK` única.
- Todas las tablas tienen además `created_at` y `updated_at`, que no se dibujan. `users` omite también `remember_token` y `email_verified_at`.
- `model_has_roles` y `model_has_permissions` (Spatie) son polimórficas: `model_type` y `model_id` identifican al usuario, pero no son una llave foránea real, por eso `users` se conecta a ellas solo en el diagrama.
- `users` todavía no se relaciona con `employees`.
- No se dibujan las tablas internas de Laravel: `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions` y `password_reset_tokens`.
- Detalle de las tablas del negocio en [`er.md`](er.md). Cómo se unen permisos y menú, en el [README](../README.md#permisos-y-menú).
