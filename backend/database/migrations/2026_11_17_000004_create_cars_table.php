<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->comment('Vehículos: inventario de la agencia y autos que pasan por el taller');
            $table->id()->comment('Identificador único del auto');
            $table->string('brand', 50)->comment('Marca. Por ejemplo Nissan');
            $table->string('model', 50)->comment('Submarca o línea. Por ejemplo Versa');
            $table->foreignId('vehicle_type_id')->comment('Tipo de vehículo (vehicle_types.id). Por ejemplo SUV')->constrained()->restrictOnDelete();
            $table->integer('model_year')->comment('Año del modelo');
            // Último kilometraje registrado. Se actualiza con el de cada orden de servicio.
            $table->integer('mileage')->comment('Último kilometraje registrado, en km. Se actualiza con cada orden de servicio');
            $table->decimal('price_mxn', 12, 2)->comment('Precio de lista en pesos mexicanos, con IVA incluido');
            $table->string('status', 10)->default('new')->comment('Estatus en el inventario. new: nuevo, used: usado, sold: vendido');
            $table->foreignId('branch_id')->nullable()->comment('Sucursal donde está el auto (branches.id)')->constrained();
            // Identificación del vehículo. Son NULL en los autos anteriores al taller; la aplicación
            // los exige al dar de alta autos nuevos.
            $table->string('vin', 17)->nullable()->unique()->comment('Número de serie (VIN): 17 caracteres sin I, O ni Q. NULL en autos anteriores al taller');
            $table->string('license_plate', 15)->nullable()->index()->comment('Placas del vehículo');
            $table->string('color', 30)->nullable()->comment('Color del vehículo');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        // CHECK en lugar de ENUM nativo (ver docs/decisiones.md, decisión 2)
        DB::statement("ALTER TABLE cars ADD CONSTRAINT cars_status_check CHECK (status IN ('new', 'used', 'sold'))");
        // VIN: 17 caracteres alfanuméricos, sin I, O ni Q.
        DB::statement("ALTER TABLE cars ADD CONSTRAINT cars_vin_check CHECK (vin ~ '^[A-HJ-NPR-Z0-9]{17}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
