<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de vehículo (carrocería). Sirven para clasificar los autos y para limitar con qué tipos
     * trabaja un empleado (employee_specializations).
     *
     * "Camioneta" no es un tipo: según el caso es `suv` o `pickup`, y una especialización puede
     * incluir varios.
     *
     * @var list<array{code: string, name: string}>
     */
    private const VEHICLE_TYPES = [
        ['code' => 'auto', 'name' => 'Auto'],
        ['code' => 'suv', 'name' => 'SUV'],
        ['code' => 'pickup', 'name' => 'Pickup'],
        ['code' => 'van', 'name' => 'Van'],
        ['code' => 'truck', 'name' => 'Camión'],
    ];

    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->comment('Catálogo de tipos de vehículo (carrocería)');
            $table->id()->comment('Identificador único del tipo de vehículo');
            $table->string('code', 20)->unique()->comment('Código estable del tipo. auto: auto, suv: SUV, pickup: camioneta de caja abierta, van: van o minivan, truck: camión');
            $table->string('name', 50)->comment('Nombre del tipo para mostrar en la interfaz. Por ejemplo SUV');
            $table->boolean('is_active')->default(true)->comment('1: tipo en uso, 0: dado de baja. Se desactiva en lugar de borrarse porque los autos lo referencian');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        // Datos de referencia: los autos y los seeds dependen de que existan (ver job_titles).
        $now = now();
        DB::table('vehicle_types')->insert(
            array_map(fn (array $type) => $type + ['created_at' => $now, 'updated_at' => $now], self::VEHICLE_TYPES)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
