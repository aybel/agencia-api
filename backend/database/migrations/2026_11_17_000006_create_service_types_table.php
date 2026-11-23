<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->comment('Catálogo de servicios de mano de obra del taller');
            $table->id()->comment('Identificador único del tipo de servicio');
            $table->string('name', 100)->comment('Nombre del servicio. Por ejemplo Cambio de aceite y filtro');
            $table->decimal('base_price_mxn', 10, 2)->comment('Precio base en pesos, sin IVA');
            // Un servicio dado de baja se desactiva, no se borra: las partidas de órdenes lo referencian.
            $table->boolean('is_active')->default(true)->comment('1: activo, 0: dado de baja. Se desactiva en lugar de borrarse porque las partidas lo referencian');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
