<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->comment('Municipios de México');
            $table->id()->comment('Identificador único del municipio');
            $table->foreignId('state_id')->comment('Entidad federativa a la que pertenece (states.id)')->constrained('states')->restrictOnDelete();
            $table->string('code', 3)->comment('Clave del municipio (INEGI), de tres dígitos. Se repite entre entidades: la clave completa es entidad más municipio');
            $table->string('name', 150)->comment('Nombre del municipio o alcaldía. Por ejemplo Cuauhtémoc');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
