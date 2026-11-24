<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->comment('Clientes que compran autos o ingresan vehículos al taller');
            $table->id()->comment('Identificador único del cliente');
            $table->string('first_name', 50)->comment('Nombre(s) del cliente');
            $table->string('last_name', 50)->comment('Apellido(s) del cliente');
            $table->string('email', 100)->unique()->comment('Correo electrónico. No se repite entre clientes');
            $table->string('phone', 15)->nullable()->comment('Teléfono de contacto');
            $table->foreignId('city_id')->comment('Municipio de residencia del cliente (cities.id)')->constrained('cities')->restrictOnDelete();
            $table->date('registration_date')->nullable()->default('2023-01-01')->comment('Fecha de alta del cliente. Por defecto 2023-01-01');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
