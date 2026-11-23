<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->comment('Ventas de vehículos. Una venta nace en borrador y se cierra o se cancela');
            $table->id()->comment('Identificador único de la venta');
            $table->foreignId('car_id')->comment('Auto vendido (cars.id)')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->comment('Cliente que compra (customers.id)')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->comment('Vendedor dueño actual de la venta (employees.id). Cambia si la venta se transfiere')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->comment('Sucursal que registra la venta (branches.id)')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('draft')->comment('Estatus de la venta. draft: borrador, completed: cerrada, cancelled: cancelada');
            $table->date('sale_date')->comment('Fecha de la venta. En borrador es la de captura y se actualiza al cerrarla');
            $table->string('car_status_at_sale', 10)->nullable()->comment('Estatus que tenía el auto al cerrar la venta. new: nuevo, used: usado. Permite restaurarlo si la venta se cancela. NULL mientras es borrador');
            $table->decimal('list_price_mxn', 12, 2)->comment('Precio de lista del auto al momento de la venta (copia de cars.price_mxn), con IVA');
            $table->decimal('discount_mxn', 12, 2)->default(0)->comment('Descuento aplicado, en pesos. Entre 0 y el precio de lista');
            $table->foreignId('discount_approved_by_user_id')->nullable()->comment('Gerente que autorizó el descuento (users.id). Obligatorio en la aplicación si supera el 10 por ciento')->constrained('users')->restrictOnDelete();
            $table->decimal('final_price_mxn', 12, 2)->comment('Precio final del vehículo con IVA = precio de lista menos descuento. No incluye accesorios');
            $table->decimal('tax_mxn', 12, 2)->comment('IVA contenido en el precio final, en pesos (desglose, no se suma al precio)');
            // Se llena al cerrar la venta: los accesorios todavía pueden cambiar mientras es borrador.
            $table->decimal('net_sale_price_mxn', 12, 2)->nullable()->comment('Precio neto de la venta antes de impuestos, en pesos: auto más accesorios sin IVA, igual al subtotal de la factura. Se calcula al cerrar la venta y es la base de la comisión. NULL mientras es borrador');
            $table->text('notes')->nullable()->comment('Observaciones de la venta');
            $table->timestamp('superseded_at')->nullable()->comment('Fecha y hora en que la venta dejó de estar vigente porque el auto regresó al inventario como auto a cuenta. NULL mientras está vigente');
            $table->timestamp('cancelled_at')->nullable()->comment('Fecha y hora de cancelación. NULL si no está cancelada');
            $table->text('cancellation_reason')->nullable()->comment('Motivo de la cancelación. NULL si no está cancelada');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('car_id');
            $table->index('customer_id');
            $table->index('employee_id');
            $table->index('sale_date');
            $table->index(['branch_id', 'status']); // ventas abiertas por sucursal
        });

        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('draft', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_car_status_at_sale_check CHECK (car_status_at_sale IS NULL OR car_status_at_sale IN ('new', 'used'))");
        // Al cerrar la venta se guarda el estatus que tenía el auto, para poder restaurarlo si se cancela.
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_completed_car_status_check CHECK (status <> 'completed' OR car_status_at_sale IS NOT NULL)");
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amounts_check CHECK (list_price_mxn >= 0 AND discount_mxn >= 0 AND discount_mxn <= list_price_mxn AND final_price_mxn = list_price_mxn - discount_mxn AND tax_mxn >= 0 AND tax_mxn <= final_price_mxn)');
        // Un descuento mayor al 10 % del precio de lista exige haber registrado quién lo autorizó
        // (que sea gerente lo valida la aplicación). Cambiar el umbral requiere una migración.
        // El neto incluye al auto sin IVA y, además, los accesorios: nunca es menor que el neto del auto.
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_net_sale_price_check CHECK (net_sale_price_mxn IS NULL OR net_sale_price_mxn >= final_price_mxn - tax_mxn)');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_completed_net_price_check CHECK (status <> 'completed' OR net_sale_price_mxn IS NOT NULL)");
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_discount_approval_check CHECK (discount_mxn * 10 <= list_price_mxn OR discount_approved_by_user_id IS NOT NULL)');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_cancelled_check CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_cancellation_reason_check CHECK (status <> 'cancelled' OR cancellation_reason IS NOT NULL)");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_superseded_check CHECK (superseded_at IS NULL OR status = 'completed')");

        // Un auto tiene a lo mucho una venta vigente (borrador o cerrada). Una cancelada no bloquea al auto,
        // y una venta reemplazada (el auto regresó al inventario a cuenta de otra venta) tampoco.
        DB::statement("CREATE UNIQUE INDEX sales_car_id_active_unique ON sales (car_id) WHERE status <> 'cancelled' AND superseded_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
