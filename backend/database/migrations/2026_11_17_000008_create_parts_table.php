<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refacciones e insumos del taller. Se mantiene aparte de `service_types` (mano de obra) porque
     * tienen ciclo de vida distinto: existencias, costo y precio.
     */
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->comment('Refacciones, insumos y accesorios con control de existencias');
            $table->id()->comment('Identificador único de la refacción');
            $table->string('sku', 50)->unique()->comment('Clave interna del artículo. No se repite');
            $table->string('name', 150)->comment('Nombre de la refacción');
            $table->string('unit', 10)->default('pza')->comment('Unidad de medida. pza: pieza, l: litro, kg: kilogramo');
            $table->decimal('cost_mxn', 10, 2)->comment('Costo para la agencia, en pesos');
            $table->decimal('price_mxn', 10, 2)->comment('Precio de venta al cliente, en pesos y sin IVA');
            $table->decimal('stock', 12, 2)->default(0)->comment('Existencias disponibles. Admite fracciones (litros). Nunca negativas');
            $table->boolean('is_active')->default(true)->comment('1: activa, 0: dada de baja');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('name');
        });

        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_cost_check CHECK (cost_mxn >= 0)');
        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_price_check CHECK (price_mxn >= 0)');
        // Las existencias nunca bajan de cero: impide vender lo que no hay, aunque falle la validación de la aplicación.
        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_stock_check CHECK (stock >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
