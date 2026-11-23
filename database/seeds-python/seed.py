"""Seed masivo y determinista de la base `agencia` (PostgreSQL).

Portado de 02_seed_masivo.sql (MySQL). Usa CRC32 sobre un texto fijo en lugar de
un generador aleatorio, así que cada ejecución produce exactamente los mismos datos.

Requisitos:
    * El esquema creado (php artisan migrate o database/schema/01_schema.sql).
    * El seed manual cargado (database/schema/02_seed_manual.sql): este script
      continúa los ids de ese seed y aborta si las tablas no coinciden con él.

Uso:
    python -m venv .venv && .venv/bin/pip install -r requirements.txt
    .venv/bin/python seed.py                       # dry-run: solo muestra conteos
    .venv/bin/python seed.py --dsn postgresql://agencia:secret@localhost:5432/agencia

Se aplica en una sola transacción: si algo falla, no se agrega nada.
"""
from __future__ import annotations

import argparse
import sys
import zlib
from dataclasses import dataclass
from datetime import date, datetime, time, timedelta
from decimal import ROUND_HALF_UP, Decimal
from math import floor

# ----------------------------------------------------------------------------
# Datos de referencia
# ----------------------------------------------------------------------------

# Estado que deja 02_seed_manual.sql (los ids nuevos continúan desde aquí).
BASE = {
    "branches": 3, "employees": 5, "customers": 12, "cars": 17,
    "sales": 12, "service_types": 7, "service_orders": 8, "service_order_items": 11,
}
CIUDADES_BASE = {1: "Ciudad de México", 2: "Guadalajara", 3: "Monterrey"}
EMPLEADOS_BASE = [  # (id, clave interna del puesto, branch_id)
    (1, "seller", 1), (2, "manager", 2), (3, "seller", 3),
    (4, "service_advisor", 1), (5, "mechanic", 1),
]

# Timestamp de las filas "de catálogo" (sucursales, empleados, tipos de servicio, autos).
EPOCH = datetime(2021, 1, 1)

SUCURSALES_NUEVAS = [
    (4, "Sucursal Angelópolis", "Puebla", "Blvd. Atlixcáyotl 1500"),
    (5, "Sucursal Bajío", "Querétaro", "Av. Constituyentes 820"),
    (6, "Sucursal Frontera", "Tijuana", "Blvd. Agua Caliente 3400"),
]

# (clave interna del puesto, etiqueta en español usada como semilla del CRC32, cantidad por sucursal).
# La etiqueta en español se conserva solo para el hash: así los nombres generados son los
# mismos que en el seed original. Orden alfabético de la etiqueta, igual que el original.
PUESTOS_GENERADOS = [
    ("service_advisor", "Asesor de Servicio", 1),
    ("mechanic", "Mecánico", 2),
    ("seller", "Vendedor", 2),
]

NOMBRES = [
    "Juan", "José", "Luis", "Carlos", "Miguel", "Jorge", "Fernando", "Ricardo",
    "Alejandro", "Daniel", "Eduardo", "Manuel", "Raúl", "Sergio", "Andrés",
    "María", "Ana", "Laura", "Sofía", "Patricia", "Gabriela", "Fernanda",
    "Daniela", "Lucía", "Valeria", "Mariana", "Paola", "Verónica", "Alicia", "Rosa",
]
APELLIDOS = [
    "García", "Martínez", "López", "Hernández", "González", "Rodríguez", "Pérez",
    "Sánchez", "Ramírez", "Flores", "Gómez", "Morales", "Vázquez", "Jiménez",
    "Reyes", "Cruz", "Ortiz", "Gutiérrez", "Chávez", "Ramos", "Mendoza",
    "Aguilar", "Castillo", "Romero", "Herrera", "Medina", "Guerrero", "Ríos",
    "Salazar", "Navarro",
]

# Catálogo de modelos con precio de lista (auto nuevo, MXN, referencia 2024).
# Los primeros son los más vendidos (la selección favorece los índices bajos).
CATALOGO = [  # (brand, model, precio base, tipo de vehículo = vehicle_types.code)
    ("Nissan", "Versa", 325000, "auto"), ("Chevrolet", "Aveo", 260000, "auto"), ("Nissan", "Kicks", 350000, "suv"),
    ("Toyota", "Corolla", 420000, "auto"), ("Volkswagen", "Virtus", 340000, "auto"), ("Kia", "Rio", 310000, "auto"),
    ("Mazda", "Mazda 3", 380000, "auto"), ("Honda", "Civic", 450000, "auto"), ("Hyundai", "Elantra", 360000, "auto"),
    ("Nissan", "Sentra", 365000, "auto"), ("Renault", "Kwid", 210000, "auto"), ("Volkswagen", "Jetta", 380000, "auto"),
    ("Toyota", "RAV4", 640000, "suv"), ("Mazda", "CX-5", 560000, "suv"), ("Honda", "CR-V", 640000, "suv"),
    ("Chevrolet", "Onix", 280000, "auto"), ("Ford", "Ranger", 780000, "pickup"), ("Toyota", "Hilux", 820000, "pickup"),
    ("Nissan", "Frontier", 450000, "pickup"), ("Suzuki", "Swift", 290000, "auto"), ("Kia", "Seltos", 430000, "suv"),
    ("Hyundai", "Tucson", 600000, "suv"), ("Peugeot", "208", 310000, "auto"), ("Renault", "Duster", 380000, "suv"),
    ("Volkswagen", "Tiguan", 720000, "suv"), ("Ford", "Mustang", 850000, "auto"), ("SEAT", "Ibiza", 330000, "auto"),
    ("Chevrolet", "Tracker", 410000, "suv"), ("Mazda", "CX-30", 470000, "suv"), ("Honda", "HR-V", 480000, "suv"),
]

TIPOS_SERVICIO_NUEVOS = [
    ("Afinación mayor", "2800.00"), ("Cambio de bujías", "1200.00"),
    ("Cambio de banda de distribución", "4200.00"), ("Cambio de llantas (juego)", "9000.00"),
    ("Cambio de amortiguadores", "5200.00"), ("Servicio de aire acondicionado", "1800.00"),
    ("Cambio de filtro de aire", "450.00"), ("Limpieza de inyectores", "1300.00"),
    ("Reparación de clutch", "7500.00"), ("Revisión de dirección", "1600.00"),
    ("Cambio de líquido de frenos", "900.00"), ("Pulido y encerado", "1500.00"),
    ("Verificación vehicular", "600.00"),
]
# Nombre y precio base de los 7 tipos del seed manual (ids 1..7), necesarios para los detalles.
TIPOS_SERVICIO_BASE = [
    ("Cambio de aceite y filtro", "1500.00"), ("Revisión y ajuste de frenos", "2500.00"),
    ("Alineación y balanceo", "800.00"), ("Cambio de batería", "3500.00"),
    ("Servicio de suspensión", "4500.00"), ("Lavado y detallado", "500.00"),
    ("Diagnóstico computarizado", "1000.00"),
]

# ELT(1 + CRC32(...) MOD 10, ...): CDMX 30 %, GDL 20 %, MTY 20 %, Puebla/Querétaro/Tijuana 10 % c/u
SUCURSAL_POR_DECIL = (1, 1, 1, 2, 2, 3, 3, 4, 5, 6)
LADA_POR_SUCURSAL = {1: "555", 2: "333", 3: "818", 4: "222", 5: "442", 6: "664"}

# Orden de carga y columnas de cada tabla. Todas llevan `id` explícito y, al final,
# created_at y updated_at.
_TS = ["created_at", "updated_at"]
TABLAS: dict[str, list[str]] = {
    "branches": ["id", "name", "city", "address", *_TS],
    "employees": ["id", "first_name", "last_name", "job_title_id", "branch_id", *_TS],
    "service_types": ["id", "name", "base_price_mxn", *_TS],
    "customers": ["id", "first_name", "last_name", "email", "phone", "city", "registration_date", *_TS],
    "cars": ["id", "brand", "model", "vehicle_type_id", "model_year", "mileage", "price_mxn", "status", "branch_id", *_TS],
    "sales": [
        "id", "car_id", "customer_id", "employee_id", "branch_id", "status", "sale_date", "car_status_at_sale",
        "list_price_mxn", "discount_mxn", "final_price_mxn", "tax_mxn", "net_sale_price_mxn", *_TS,
    ],
    "service_orders": [
        "id", "car_id", "customer_id", "employee_id", "branch_id", "status_id",
        "check_in_date", "delivery_date", "total_mxn", *_TS,
    ],
    "service_order_items": [
        "id", "service_order_id", "service_type_id", "description", "unit_price_mxn", "applied_cost_mxn", *_TS,
    ],
}


# ----------------------------------------------------------------------------
# Utilidades
# ----------------------------------------------------------------------------

def crc(texto: str) -> int:
    """CRC32 sin signo sobre los bytes UTF-8, igual que CRC32() de MySQL."""
    return zlib.crc32(texto.encode("utf-8"))


def redondear(valor: Decimal, digitos: int) -> Decimal:
    """ROUND(valor, digitos) de MySQL: mitades hacia arriba. `digitos` puede ser negativo."""
    paso = Decimal(1).scaleb(-digitos)
    return (valor / paso).quantize(Decimal(1), rounding=ROUND_HALF_UP) * paso


def ts(d: date) -> datetime:
    """Medianoche de una fecha de negocio, usada como created_at / updated_at."""
    return datetime.combine(d, time.min)


@dataclass(frozen=True)
class Parametros:
    n_ventas: int = 2000        # ventas nuevas (1 auto vendido por venta)
    n_ordenes: int = 4000       # órdenes de servicio nuevas
    n_inventario: int = 400     # autos en inventario (sin vender)
    fecha_inicio: date = date(2022, 1, 1)
    fecha_corte: date = date(2024, 6, 30)  # ningún dato posterior a esta fecha

    @property
    def dias(self) -> int:
        return (self.fecha_corte - self.fecha_inicio).days + 1


# ----------------------------------------------------------------------------
# Generación
# ----------------------------------------------------------------------------

# El generador usa claves internas para los puestos (las mismas con las que calcula los CRC32). En la base
# cada puesto tiene un código de dos dígitos (job_titles.code). Este es el puente entre ambos.
# "Jefe Mecánico" (04) existe en el catálogo pero el seed no genera empleados con ese puesto.
CODIGO_DE_PUESTO = {"seller": "01", "manager": "02", "service_advisor": "03", "mechanic": "05"}

# Ids de job_titles tal como los crea la migración (por si se usa el dry-run sin base).
JOB_TITLE_IDS_POR_DEFECTO = {"seller": 1, "manager": 2, "service_advisor": 3, "mechanic": 5}
# Ids de vehicle_types tal como los crea la migración.
VEHICLE_TYPE_IDS_POR_DEFECTO = {"auto": 1, "suv": 2, "pickup": 3, "van": 4, "truck": 5}


def generar(
    p: Parametros,
    delivered_status_id: int = 6,
    job_title_ids: dict[str, int] | None = None,
    vehicle_type_ids: dict[str, int] | None = None,
) -> dict[str, list[tuple]]:
    """`delivered_status_id` es el id del estatus `delivered` en service_order_statuses.

    `job_title_ids` mapea la clave interna de cada puesto (`seller`, `mechanic`...) al id de job_titles. En el dry-run se usan los
    que crean las migraciones; al aplicar se consultan a la base.
    `vehicle_type_ids` mapea el código de cada tipo de vehículo (`auto`, `suv`...) al id de vehicle_types, con la misma regla.
    """
    job_title_ids = job_title_ids or JOB_TITLE_IDS_POR_DEFECTO
    vehicle_type_ids = vehicle_type_ids or VEHICLE_TYPE_IDS_POR_DEFECTO
    t: dict[str, list[tuple]] = {nombre: [] for nombre in TABLAS}
    ciudad = dict(CIUDADES_BASE)

    # 1. Sucursales
    for id_suc, nombre, city, direccion in SUCURSALES_NUEVAS:
        t["branches"].append((id_suc, nombre, city, direccion, EPOCH, EPOCH))
        ciudad[id_suc] = city

    # 2. Empleados: por sucursal 1 asesor, 2 mecánicos y 2 vendedores, y un gerente donde no hay.
    empleados = list(EMPLEADOS_BASE)  # (id, clave interna del puesto, branch_id)
    sig_emp = BASE["employees"] + 1
    for suc in sorted(ciudad):
        for puesto, etiqueta, cant in PUESTOS_GENERADOS:
            for k in range(1, cant + 1):
                nom = NOMBRES[crc(f"emp-nom-{suc}{etiqueta}{k}") % 30]
                ape = APELLIDOS[crc(f"emp-ape-{suc}{etiqueta}{k}") % 30]
                t["employees"].append((sig_emp, nom, ape, job_title_ids[puesto], suc, EPOCH, EPOCH))
                empleados.append((sig_emp, puesto, suc))
                sig_emp += 1
    for suc in sorted(ciudad):
        if not any(e[1] == "manager" and e[2] == suc for e in empleados):
            nom = NOMBRES[crc(f"emp-nom-g{suc}") % 30]
            ape = APELLIDOS[crc(f"emp-ape-g{suc}") % 30]
            t["employees"].append((sig_emp, nom, ape, job_title_ids["manager"], suc, EPOCH, EPOCH))
            empleados.append((sig_emp, "manager", suc))
            sig_emp += 1

    vendedores = {s: sorted(e[0] for e in empleados if e[1] == "seller" and e[2] == s) for s in ciudad}
    servicio = {
        s: sorted(e[0] for e in empleados if e[1] in ("service_advisor", "mechanic") and e[2] == s)
        for s in ciudad
    }

    # 3. Tipos de servicio (13 nuevos, 20 en total)
    precios_tipo = [Decimal(precio) for _nombre, precio in TIPOS_SERVICIO_BASE]
    nombres_tipo = [nombre for nombre, _precio in TIPOS_SERVICIO_BASE]
    for i, (nombre, precio) in enumerate(TIPOS_SERVICIO_NUEVOS, start=BASE["service_types"] + 1):
        t["service_types"].append((i, nombre, Decimal(precio), EPOCH, EPOCH))
        precios_tipo.append(Decimal(precio))
        nombres_tipo.append(nombre)
    n_tipos = len(precios_tipo)

    # 4. Plan de ventas: n = orden cronológico; es_nuevo = comprador nuevo (~72 %);
    #    cli = número de cliente dentro de los generados; suc = sucursal del cliente.
    dias_venta = sorted((crc(f"vta-dia-{k}") % p.dias, k) for k in range(1, p.n_ventas + 1))
    plan = []  # (n, fecha, es_nuevo, cli, suc)
    acum = 0
    for n, (dia, _k) in enumerate(dias_venta, start=1):
        es_nuevo = n <= 20 or crc(f"vta-nuevo-{n}") % 100 < 72
        acum += es_nuevo
        cli = acum if es_nuevo else 1 + crc(f"vta-cli-{n}") % acum
        suc = SUCURSAL_POR_DECIL[crc(f"cli-suc-{cli}") % 10]
        plan.append((n, p.fecha_inicio + timedelta(days=dia), es_nuevo, cli, suc))

    # 5. Clientes (uno por cada venta de cliente nuevo; se registran 0 a 20 días antes)
    base_cli = BASE["customers"]
    for n, fecha, es_nuevo, cli, suc in plan:
        if not es_nuevo:
            continue
        id_cliente = base_cli + cli
        registro = fecha - timedelta(days=crc(f"cli-reg-{cli}") % 21)
        t["customers"].append((
            id_cliente,
            NOMBRES[crc(f"cli-nom-{cli}") % 30],
            APELLIDOS[crc(f"cli-ape-{cli}") % 30],
            f"cliente{id_cliente:05d}@example.com",
            f"{LADA_POR_SUCURSAL[suc]}-{crc(f'cli-tel-{cli}') % 10000:04d}",
            ciudad[suc],
            registro, ts(registro), ts(registro),
        ))

    # 6. Autos
    base_auto = BASE["cars"]
    n_cat = len(CATALOGO)
    precio_auto: dict[int, Decimal] = {}
    suc_auto: dict[int, int] = {}
    nuevo_auto: dict[int, bool] = {}  # True si el auto no tenía kilometraje (era nuevo)

    # 6a. Un auto vendido por venta (60 % nuevos, 40 % usados de 1 a 8 años).
    #     Precio: base del modelo -4 % por año de antigüedad de la venta (ref. 2024)
    #     y -8 % por año de uso. updated_at = día de la venta (cambió a 'sold').
    for n, fecha, _es_nuevo, _cli, suc in plan:
        cat = 1 + floor(n_cat * ((crc(f"auto-cat-{n}") % 10000) / 10000) ** 2)
        edad = 0 if crc(f"auto-tipo-{n}") % 100 < 60 else 1 + crc(f"auto-edad-{n}") % 8
        brand, model, base, tipo = CATALOGO[cat - 1]
        precio = redondear(
            Decimal(base)
            * (1 - Decimal("0.04") * (2024 - fecha.year))
            * (1 - Decimal("0.08") * edad),
            -3,
        )
        model_year = fecha.year + (1 if edad == 0 and fecha.month >= 10 else 0) - edad
        km = 0 if edad == 0 else edad * (8000 + crc(f"auto-km-{n}") % 14000)
        id_auto = base_auto + n
        t["cars"].append((id_auto, brand, model, vehicle_type_ids[tipo], model_year, km, precio, "sold", suc, EPOCH, ts(fecha)))
        precio_auto[id_auto], suc_auto[id_auto], nuevo_auto[id_auto] = precio, suc, km == 0

    # 6b. Inventario sin vender (55 % nuevos, el resto usados de 1 a 6 años)
    for n in range(1, p.n_inventario + 1):
        cat = 1 + floor(n_cat * ((crc(f"inv-cat-{n}") % 10000) / 10000) ** 2)
        edad = 0 if crc(f"inv-tipo-{n}") % 100 < 55 else 1 + crc(f"inv-edad-{n}") % 6
        suc = SUCURSAL_POR_DECIL[crc(f"inv-suc-{n}") % 10]
        brand, model, base, tipo = CATALOGO[cat - 1]
        precio = redondear(Decimal(base) * (1 - Decimal("0.08") * edad), -3)
        km = 0 if edad == 0 else edad * (8000 + crc(f"inv-km-{n}") % 14000)
        id_auto = base_auto + p.n_ventas + n
        t["cars"].append((
            id_auto, brand, model, vehicle_type_ids[tipo], 2024 - edad, km, precio,
            "new" if edad == 0 else "used", suc, EPOCH, EPOCH,
        ))
        precio_auto[id_auto], suc_auto[id_auto], nuevo_auto[id_auto] = precio, suc, km == 0

    # 7. Ventas: el vendedor es de la sucursal del auto; precio final 94 %-100 % de lista
    for n, fecha, _es_nuevo, cli, suc in plan:
        id_auto = base_auto + n
        lista = vendedores[suc_auto[id_auto]]
        id_vendedor = lista[crc(f"vta-emp-{n}") % len(lista)]
        factor = Decimal("0.94") + Decimal(crc(f"vta-desc-{n}") % 7) / 100
        precio_lista = precio_auto[id_auto]
        final = redondear(precio_lista * factor, -2)
        # Ventas cerradas: sucursal del auto, descuento = lista menos final, IVA del 16 % contenido en el final
        # y precio neto antes de impuestos (sin accesorios, porque las ventas históricas no tienen).
        iva = redondear(final - final / Decimal("1.16"), 2)
        t["sales"].append((
            BASE["sales"] + n, id_auto, base_cli + cli, id_vendedor, suc_auto[id_auto], "completed", fecha,
            "new" if nuevo_auto[id_auto] else "used", precio_lista, precio_lista - final, final,
            iva, final - iva, ts(fecha), ts(fecha),
        ))
    fecha_plan = {n: (fecha, cli) for n, fecha, _e, cli, _s in plan}

    # 8. Órdenes de servicio
    #    tipo 0 (8 %):  revisión previa a la venta, sin cliente
    #    tipo 1 (85 %): servicio posterior a la venta, a nombre del comprador
    #    tipo 2 (7 %):  servicio a un auto del inventario, sin cliente
    #    Los autos vendidos hace más tiempo reciben más servicios.
    base_ord = BASE["service_orders"]
    sig_det = BASE["service_order_items"] + 1
    for m in range(1, p.n_ordenes + 1):
        h = crc(f"ord-tipo-{m}") % 100
        tipo = 0 if h < 8 else 1 if h < 93 else 2
        sn = 1 + floor(p.n_ventas * ((crc(f"ord-vta-{m}") % 10000) / 10000) ** 1.5)
        h_lin = crc(f"ord-lin-{m}") % 100
        lineas = 1 if h_lin < 55 else 2 if h_lin < 85 else 3
        fecha_venta, cli = fecha_plan[sn]

        if tipo == 2:
            id_auto = base_auto + p.n_ventas + 1 + crc(f"ord-inv-{m}") % p.n_inventario
        else:
            id_auto = base_auto + sn
        id_cliente = base_cli + cli if tipo == 1 else None

        if tipo == 0:
            ingreso = fecha_venta - timedelta(days=1 + crc(f"ord-pre-{m}") % 10)
        elif tipo == 1:
            restante = (p.fecha_corte - fecha_venta).days
            ingreso = fecha_venta + timedelta(days=0 if restante <= 0 else 1 + crc(f"ord-post-{m}") % restante)
        else:
            ingreso = p.fecha_corte - timedelta(days=crc(f"ord-invf-{m}") % 182)

        entrega = min(ingreso + timedelta(days=crc(f"ord-ent-{m}") % 4), p.fecha_corte)
        atendedores = servicio[suc_auto[id_auto]]
        id_empleado = atendedores[crc(f"ord-emp-{m}") % len(atendedores)]

        # 9. Detalle: 1 a 3 servicios distintos; los primeros tipos son los más frecuentes.
        #    El 10 % de las líneas lleva 10 % de descuento.
        id_orden = base_ord + m
        base_tipo = floor(n_tipos * ((crc(f"det-tipo-{m}") % 10000) / 10000) ** 2)
        total = Decimal("0.00")
        for k in range(1, lineas + 1):
            idx = (base_tipo + (k - 1) * 7) % n_tipos
            descuento = Decimal("0.90") if crc(f"det-desc-{m}-{k}") % 10 == 0 else Decimal("1.00")
            costo = redondear(precios_tipo[idx] * descuento, 2)
            # cantidad 1: precio unitario = costo aplicado; la descripción copia el nombre del servicio
            t["service_order_items"].append((
                sig_det, id_orden, idx + 1, nombres_tipo[idx], costo, costo, ts(ingreso), ts(ingreso),
            ))
            sig_det += 1
            total += costo
        # Todas entregadas; la sucursal es la del auto (también la del empleado que las atiende).
        t["service_orders"].append((
            id_orden, id_auto, id_cliente, id_empleado, suc_auto[id_auto], delivered_status_id,
            ingreso, entrega, total, ts(ingreso), ts(entrega),
        ))

    return t


# ----------------------------------------------------------------------------
# Carga en PostgreSQL
# ----------------------------------------------------------------------------

def estatus_entregado(cur) -> int:
    cur.execute("SELECT id FROM service_order_statuses WHERE code = 'delivered'")
    fila = cur.fetchone()
    if fila is None:
        sys.exit("Abortado: faltan los estatus de service_order_statuses. Ejecuta php artisan migrate.")
    return fila[0]


def puestos(cur) -> dict[str, int]:
    cur.execute("SELECT code, id FROM job_titles")
    por_codigo = dict(cur.fetchall())
    faltan = sorted(c for c in CODIGO_DE_PUESTO.values() if c not in por_codigo)
    if faltan:
        sys.exit(f"Abortado: faltan los puestos con código {faltan} en job_titles. Ejecuta php artisan migrate.")
    return {clave: por_codigo[codigo] for clave, codigo in CODIGO_DE_PUESTO.items()}


def tipos_de_vehiculo(cur) -> dict[str, int]:
    cur.execute("SELECT code, id FROM vehicle_types")
    por_codigo = dict(cur.fetchall())
    faltan = sorted({tipo for *_, tipo in CATALOGO} - por_codigo.keys())
    if faltan:
        sys.exit(f"Abortado: faltan los tipos de vehículo {faltan} en vehicle_types. Ejecuta php artisan migrate.")
    return por_codigo
def aplicar(dsn: str, params: Parametros) -> dict[str, list[tuple]]:
    import psycopg

    with psycopg.connect(dsn) as conn, conn.cursor() as cur:
        datos = generar(params, estatus_entregado(cur), puestos(cur), tipos_de_vehiculo(cur))
        # El script continúa los ids del seed manual: comprobar que es el estado actual.
        for tabla in TABLAS:
            cur.execute(f"SELECT COUNT(*), COALESCE(MAX(id), 0) FROM {tabla}")
            filas, max_id = cur.fetchone()
            if filas != BASE[tabla] or max_id != BASE[tabla]:
                sys.exit(
                    f"Abortado: `{tabla}` tiene {filas} filas (id máx. {max_id}) y se esperaban "
                    f"{BASE[tabla]}. Carga antes database/schema/02_seed_manual.sql."
                )

        for tabla, cols in TABLAS.items():
            # Los nombres de tabla y columna salen de las constantes de este archivo.
            sql = f"INSERT INTO {tabla} ({', '.join(cols)}) VALUES ({', '.join(['%s'] * len(cols))})"
            cur.executemany(sql, datos[tabla])
            # Los ids se insertaron explícitos: alinear la secuencia para futuros INSERT sin id.
            cur.execute(f"SELECT setval(pg_get_serial_sequence('{tabla}', 'id'), (SELECT MAX(id) FROM {tabla}))")

    return datos


def main() -> None:
    ap = argparse.ArgumentParser(description="Seed masivo determinista de la base agencia.")
    ap.add_argument("--dsn", help="Cadena de conexión. Si se omite, solo genera y muestra conteos (dry-run).")
    ap.add_argument("--n-ventas", type=int, default=Parametros.n_ventas)
    ap.add_argument("--n-ordenes", type=int, default=Parametros.n_ordenes)
    ap.add_argument("--n-inventario", type=int, default=Parametros.n_inventario)
    args = ap.parse_args()

    params = Parametros(n_ventas=args.n_ventas, n_ordenes=args.n_ordenes, n_inventario=args.n_inventario)
    datos = aplicar(args.dsn, params) if args.dsn else generar(params)

    print(f"{'tabla':<22}{'nuevas':>8}{'total':>8}")
    for tabla in TABLAS:
        print(f"{tabla:<22}{len(datos[tabla]):>8}{BASE[tabla] + len(datos[tabla]):>8}")

    print("Seed aplicado." if args.dsn else "Dry-run (sin --dsn): no se escribió nada.")


if __name__ == "__main__":
    main()
