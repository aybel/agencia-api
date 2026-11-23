<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_items', function (Blueprint $table) {
            $table->comment('Partidas de una orden de servicio: mano de obra o refacciones');
            $table->id()->comment('Identificador único de la partida');
            // RESTRICT: no se puede borrar una orden con partidas, para no perder historial.
            $table->foreignId('service_order_id')->comment('Orden a la que pertenece (service_orders.id)')->constrained()->restrictOnDelete();
            // Cada partida es un servicio (mano de obra) o una refacción, nunca ambos ni ninguno.
            $table->foreignId('service_type_id')->nullable()->comment('Servicio de mano de obra (service_types.id). Exactamente uno entre este y part_id')->constrained()->restrictOnDelete();
            $table->foreignId('part_id')->nullable()->comment('Refacción usada (parts.id). Exactamente uno entre este y service_type_id')->constrained()->restrictOnDelete();

            // Copia del nombre y del precio al momento de la orden: cambiar o desactivar el catálogo
            // después no altera órdenes ya cobradas.
            $table->string('description', 255)->comment('Nombre del servicio o refacción al momento de la orden (copia)');
            $table->decimal('quantity', 10, 2)->default(1)->comment('Cantidad. Admite fracciones, por ejemplo litros');
            $table->decimal('unit_price_mxn', 10, 2)->comment('Precio unitario en pesos y sin IVA');
            $table->decimal('applied_cost_mxn', 10, 2)->comment('Total de la partida en pesos = cantidad por precio unitario');

            // Aprobación del cliente para costos extra. Tres estados y no un booleano: así se distingue
            // lo que espera respuesta (pending) de lo que el cliente rechazó (rejected).
            $table->string('approval_status', 10)->default('approved')->comment('Aprobación del cliente. approved: aprobada, pending: espera respuesta, rejected: rechazada');
            $table->timestamp('approved_at')->nullable()->comment('Fecha y hora en que se registró la respuesta del cliente');
            $table->foreignId('approved_by_user_id')->nullable()->comment('Usuario que registró la respuesta del cliente (users.id)')->constrained('users')->nullOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->comment('Usuario que agregó la partida (users.id)')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('service_order_id');
            $table->index('service_type_id');
            $table->index('part_id');
            $table->index(['service_order_id', 'approval_status']);
        });

        DB::statement('ALTER TABLE service_order_items ADD CONSTRAINT service_order_items_source_check CHECK (num_nonnulls(service_type_id, part_id) = 1)');
        DB::statement('ALTER TABLE service_order_items ADD CONSTRAINT service_order_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE service_order_items ADD CONSTRAINT service_order_items_unit_price_check CHECK (unit_price_mxn >= 0)');
        DB::statement('ALTER TABLE service_order_items ADD CONSTRAINT service_order_items_total_check CHECK (applied_cost_mxn = round(quantity * unit_price_mxn, 2))');
        DB::statement("ALTER TABLE service_order_items ADD CONSTRAINT service_order_items_approval_status_check CHECK (approval_status IN ('approved', 'pending', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_items');
    }
};
