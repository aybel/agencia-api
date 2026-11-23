<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facturas de ventas y de órdenes de servicio.
     *
     * Usa dos llaves foráneas reales (sale_id, service_order_id) y un CHECK que exige exactamente una,
     * en lugar de una relación polimórfica: PostgreSQL no puede validar llaves polimórficas, y así
     * no puede existir una factura apuntando a un registro inexistente.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->comment('Facturas de ventas y de órdenes de servicio');
            $table->id()->comment('Identificador único de la factura');
            $table->foreignId('sale_id')->nullable()->comment('Venta facturada (sales.id). Exactamente una entre esta y service_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_order_id')->nullable()->comment('Orden de servicio facturada (service_orders.id). Exactamente una entre esta y sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->comment('Cliente receptor de la factura (customers.id)')->constrained()->restrictOnDelete();

            // Folio fiscal (UUID del CFDI). Es NULL hasta que el PAC timbra la factura.
            $table->uuid('fiscal_uuid')->nullable()->unique()->comment('Folio fiscal (UUID del CFDI). NULL hasta que el PAC timbra la factura');
            $table->decimal('subtotal_mxn', 12, 2)->comment('Importe antes de IVA, en pesos');
            $table->decimal('tax_mxn', 12, 2)->comment('IVA, en pesos');
            $table->decimal('total_mxn', 12, 2)->comment('Total en pesos = subtotal más IVA');
            $table->string('status', 15)->default('pending')->comment('Estatus. pending: sin pagos, partially_paid: pagada en parte, paid: pagada, cancelled: cancelada');

            // Rutas dentro del almacenamiento de archivos, no URLs (las URLs cambian y caducan).
            $table->string('pdf_path')->nullable()->comment('Ruta del PDF en el almacenamiento de archivos');
            $table->string('xml_path')->nullable()->comment('Ruta del XML en el almacenamiento de archivos');
            $table->timestamp('issued_at')->nullable()->comment('Fecha y hora de emisión (timbrado). NULL mientras es un borrador');
            $table->timestamp('cancelled_at')->nullable()->comment('Fecha y hora de cancelación. NULL si no está cancelada');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('customer_id');
            $table->index('status');
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_source_check CHECK (num_nonnulls(sale_id, service_order_id) = 1)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amounts_check CHECK (subtotal_mxn >= 0 AND tax_mxn >= 0 AND total_mxn = subtotal_mxn + tax_mxn)');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('pending', 'partially_paid', 'paid', 'cancelled'))");
        // Una factura está cancelada si y solo si tiene fecha de cancelación.
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_cancelled_check CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL))");

        // Solo una factura vigente por venta y por orden. Una cancelada permite emitir otra.
        DB::statement("CREATE UNIQUE INDEX invoices_sale_id_active_unique ON invoices (sale_id) WHERE sale_id IS NOT NULL AND status <> 'cancelled'");
        DB::statement("CREATE UNIQUE INDEX invoices_service_order_id_active_unique ON invoices (service_order_id) WHERE service_order_id IS NOT NULL AND status <> 'cancelled'");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
