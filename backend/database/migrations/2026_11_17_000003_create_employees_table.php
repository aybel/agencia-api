<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->comment('Empleados de la agencia. Nunca se borran: se desactivan con is_active');
            $table->id()->comment('Identificador único del empleado');
            $table->string('first_name', 50)->comment('Nombre(s) del empleado');
            $table->string('last_name', 50)->comment('Apellido(s) del empleado');
            $table->foreignId('job_title_id')->comment('Puesto del empleado (job_titles.id)')->constrained('job_titles')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->comment('Sucursal en la que trabaja (branches.id)')->constrained();
            // Cuenta de acceso del empleado (1 a 1). Es NULL si el empleado no usa el sistema, y un
            // usuario sin empleado (por ejemplo el admin) no pertenece a ninguna sucursal.
            $table->foreignId('user_id')->nullable()->unique()->comment('Cuenta de acceso al sistema (users.id), relación 1 a 1. NULL si el empleado no usa el sistema')->constrained()->nullOnDelete();
            // Un empleado nunca se borra (sus ventas y órdenes lo referencian): se da de baja con is_active = false.
            $table->boolean('is_active')->default(true)->comment('1: activo, 0: dado de baja. Un empleado dado de baja conserva su historial pero no recibe ventas');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
