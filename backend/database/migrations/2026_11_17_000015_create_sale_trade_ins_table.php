<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autos que el cliente entrega como parte de pago.
     *
     * Guarda una copia de los datos del auto y NO crea el auto en `cars` mientras la venta es borrador:
     * si la venta se cancela, no queda inventario fantasma. Al cerrar la venta la aplicación inserta el
     * auto en `cars` (o reutiliza el existente si el VIN ya está) y asigna `car_id`.
     */
    public function up(): void
    {
        Schema::create('sale_trade_ins', function (Blueprint $table) {
            $table->comment('Autos que el cliente entrega como parte de pago de una venta');
            $table->id()->comment('Identificador único del auto a cuenta');
            $table->foreignId('sale_id')->comment('Venta en la que se recibe (sales.id)')->constrained()->restrictOnDelete();
            $table->foreignId('car_id')->nullable()->comment('Auto en el inventario (cars.id). NULL mientras la venta es borrador; se asigna al cerrarla')->constrained()->restrictOnDelete();
            $table->string('vin', 17)->comment('Número de serie (VIN) del auto recibido: 17 caracteres sin I, O ni Q');
            $table->string('brand', 50)->comment('Marca del auto recibido');
            $table->string('model', 50)->comment('Submarca o línea del auto recibido');
            $table->integer('model_year')->comment('Año del modelo del auto recibido');
            $table->integer('mileage')->comment('Kilometraje del auto al recibirlo, en km');
            $table->string('license_plate', 15)->nullable()->comment('Placas del auto recibido');
            $table->string('color', 30)->nullable()->comment('Color del auto recibido');
            $table->decimal('valuation_price_mxn', 12, 2)->comment('Valor al que se recibe el auto, en pesos. Se resta de lo que paga el cliente');
            $table->text('condition_notes')->nullable()->comment('Estado físico y mecánico del auto recibido');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('sale_id');
            $table->index('car_id');
            $table->index('vin');
        });

        DB::statement("ALTER TABLE sale_trade_ins ADD CONSTRAINT sale_trade_ins_vin_check CHECK (vin ~ '^[A-HJ-NPR-Z0-9]{17}$')");
        DB::statement('ALTER TABLE sale_trade_ins ADD CONSTRAINT sale_trade_ins_mileage_check CHECK (mileage >= 0)');
        DB::statement('ALTER TABLE sale_trade_ins ADD CONSTRAINT sale_trade_ins_valuation_check CHECK (valuation_price_mxn >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_trade_ins');
    }
};
