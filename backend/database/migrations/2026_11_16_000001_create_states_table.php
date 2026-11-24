<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->comment('Estados de México');
            $table->id()->comment('Identificador único del estado');
            $table->string('code', 2)->comment('Clave de la entidad federativa (INEGI), de dos dígitos. Por ejemplo 09');
            $table->string('short_name', 6)->comment('Nombre abreviado. Por ejemplo CDMX o Q. Roo');
            $table->string('name', 150)->comment('Nombre completo de la entidad. Por ejemplo Ciudad de México');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
