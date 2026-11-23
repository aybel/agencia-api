<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_specializations', function (Blueprint $table) {
            $table->comment('Con qué autos trabaja un empleado. Sin filas, el empleado atiende cualquier auto. Cada fila es una combinación permitida (tipo Y condición); varias filas se suman (O)');
            $table->id()->comment('Identificador único de la especialización');
            $table->foreignId('employee_id')->comment('Empleado al que aplica (employees.id)')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->comment('Tipo de vehículo permitido (vehicle_types.id). NULL: cualquier tipo')->constrained()->restrictOnDelete();
            $table->string('car_condition', 5)->default('all')->comment('Condición del auto permitida. new: solo nuevos, used: solo usados, all: ambas');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        DB::statement("ALTER TABLE employee_specializations ADD CONSTRAINT employee_specializations_car_condition_check CHECK (car_condition IN ('new', 'used', 'all'))");

        // Una combinación no se repite por empleado. NULLS NOT DISTINCT también rechaza dos filas
        // iguales con vehicle_type_id NULL (PostgreSQL 15 o superior).
        DB::statement('CREATE UNIQUE INDEX employee_specializations_employee_type_condition_unique ON employee_specializations (employee_id, vehicle_type_id, car_condition) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_specializations');
    }
};
