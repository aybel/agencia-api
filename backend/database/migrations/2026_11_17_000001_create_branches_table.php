<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->comment('Sucursales de la agencia');
            $table->id()->comment('Identificador único de la sucursal');
            $table->string('name', 100)->comment('Nombre de la sucursal');
            $table->foreignId('city_id')->comment('Municipio donde se ubica la sucursal (cities.id)')->constrained('cities')->restrictOnDelete();
            $table->string('address', 150)->comment('Dirección completa');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
