<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_addons', function (Blueprint $table) {
            $table->comment('Accesorios y servicios vendidos junto con el auto: placas, seguros, tapetes');
            $table->id()->comment('Identificador único del accesorio');
            $table->foreignId('sale_id')->comment('Venta a la que pertenece (sales.id)')->constrained()->restrictOnDelete();
            $table->foreignId('part_id')->nullable()->comment('Artículo del inventario (parts.id). NULL si no es de inventario, por ejemplo un seguro')->constrained()->restrictOnDelete();
            $table->string('description', 255)->comment('Nombre del accesorio al momento de la venta (copia)');
            $table->decimal('quantity', 10, 2)->default(1)->comment('Cantidad. Mayor que cero');
            $table->decimal('unit_price_mxn', 10, 2)->comment('Precio unitario en pesos, con IVA');
            $table->decimal('total_mxn', 12, 2)->comment('Total en pesos = cantidad por precio unitario');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('sale_id');
            $table->index('part_id');
        });

        DB::statement('ALTER TABLE sale_addons ADD CONSTRAINT sale_addons_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sale_addons ADD CONSTRAINT sale_addons_unit_price_check CHECK (unit_price_mxn >= 0)');
        DB::statement('ALTER TABLE sale_addons ADD CONSTRAINT sale_addons_total_check CHECK (total_mxn = round(quantity * unit_price_mxn, 2))');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_addons');
    }
};
