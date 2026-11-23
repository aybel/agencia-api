<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->comment('Órdenes de servicio del taller');
            $table->id()->comment('Identificador único de la orden');
            $table->foreignId('car_id')->comment('Auto que ingresa al taller (cars.id)')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->comment('Cliente que paga (customers.id). NULL cuando el auto es de la agencia')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->comment('Empleado que atiende la orden (employees.id)')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->comment('Sucursal del taller que atiende (branches.id)')->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->comment('Estatus actual de la orden (service_order_statuses.id)')->constrained('service_order_statuses')->restrictOnDelete();

            $table->date('check_in_date')->comment('Fecha de ingreso del auto al taller');
            $table->date('estimated_delivery_date')->nullable()->comment('Fecha de entrega prometida al cliente. No puede ser anterior al ingreso');
            $table->date('delivery_date')->nullable()->comment('Fecha real de entrega. No puede ser anterior al ingreso');

            // Kilometraje: la aplicación exige mileage_in al crear una orden. En la base es NULL porque
            // las órdenes históricas no lo capturaron.
            $table->unsignedInteger('mileage_in')->nullable()->comment('Kilometraje de entrada, en km. La aplicación lo exige; es NULL en las órdenes históricas');
            $table->unsignedInteger('mileage_out')->nullable()->comment('Kilometraje de salida, en km. No puede ser menor al de entrada');

            $table->text('customer_notes')->nullable()->comment('Problemas reportados por el cliente');
            $table->text('internal_notes')->nullable()->comment('Notas internas del taller, no visibles al cliente');

            // Copia de los datos de contacto al momento de la orden: si el cliente cambia después
            // su teléfono o correo, la orden conserva los que se usaron entonces.
            $table->string('contact_phone', 15)->nullable()->comment('Teléfono del cliente al momento de la orden (copia)');
            $table->string('contact_email', 100)->nullable()->comment('Correo del cliente al momento de la orden (copia)');

            // Suma de las partidas aprobadas, sin IVA. La aplicación la recalcula en la misma
            // transacción en que cambia una partida.
            $table->decimal('total_mxn', 10, 2)->default(0)->comment('Suma de las partidas aprobadas, en pesos y sin IVA');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('car_id');
            $table->index('customer_id');
            $table->index('employee_id');
            $table->index('status_id');
            $table->index('check_in_date');
            $table->index(['branch_id', 'status_id']); // tablero del taller: órdenes abiertas por sucursal
        });

        DB::statement('ALTER TABLE service_orders ADD CONSTRAINT service_orders_estimated_delivery_check CHECK (estimated_delivery_date IS NULL OR estimated_delivery_date >= check_in_date)');
        DB::statement('ALTER TABLE service_orders ADD CONSTRAINT service_orders_delivery_check CHECK (delivery_date IS NULL OR delivery_date >= check_in_date)');
        DB::statement('ALTER TABLE service_orders ADD CONSTRAINT service_orders_mileage_check CHECK ((mileage_in IS NULL OR mileage_in >= 0) AND (mileage_out IS NULL OR mileage_out >= 0) AND (mileage_in IS NULL OR mileage_out IS NULL OR mileage_out >= mileage_in))');
        DB::statement('ALTER TABLE service_orders ADD CONSTRAINT service_orders_total_check CHECK (total_mxn >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
