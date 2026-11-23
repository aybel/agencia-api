<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de cambios de estatus de una orden. Es solo de inserción: no tiene updated_at y la
     * aplicación nunca la actualiza ni la borra. Las órdenes históricas no tienen filas aquí.
     */
    public function up(): void
    {
        Schema::create('service_order_histories', function (Blueprint $table) {
            $table->comment('Bitácora de cambios de estatus de las órdenes. Solo se inserta, nunca se modifica');
            $table->id()->comment('Identificador único del movimiento');
            $table->foreignId('service_order_id')->comment('Orden que cambió de estatus (service_orders.id)')->constrained()->restrictOnDelete();
            $table->foreignId('from_status_id')->nullable()->comment('Estatus anterior (service_order_statuses.id). NULL en el alta de la orden')->constrained('service_order_statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->comment('Estatus nuevo (service_order_statuses.id)')->constrained('service_order_statuses')->restrictOnDelete();
            // Quien hizo el cambio. Si se borra el usuario, la bitácora se conserva con user_id en NULL.
            $table->foreignId('user_id')->nullable()->comment('Usuario que hizo el cambio (users.id). NULL si el usuario fue eliminado')->constrained()->nullOnDelete();
            $table->text('notes')->nullable()->comment('Notas del cambio. Obligatorias al cancelar o rechazar un extra');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha y hora del cambio');

            $table->index(['service_order_id', 'created_at']);
            $table->index('user_id');
        });

        DB::statement('ALTER TABLE service_order_histories ADD CONSTRAINT service_order_histories_transition_check CHECK (from_status_id IS NULL OR from_status_id <> to_status_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_histories');
    }
};
