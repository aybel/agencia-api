<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Debe correr después de las tablas de Spatie (permission_id apunta a `permissions`).
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->comment('Opciones del menú de la aplicación y el permiso que da acceso a cada una');
            $table->id()->comment('Identificador único del ítem del menú');
            $table->string('semantic_key')->unique()->nullable()->comment('Clave única y estable del ítem. Por ejemplo sales.list');
            $table->string('display_name')->comment('Nombre a mostrar en el menú');
            $table->string('route_name')->nullable()->comment('Nombre de la ruta, no URL');
            $table->string('semantic_icon')->nullable()->comment('Icono semántico, por ejemplo: "supplier", "dashboard", etc.');
            $table->enum('semantic_type', ['header', 'module', 'group', 'link'])->default('link')->comment('Tipo semántico del elemento del menú. header: encabezado de sección, module: módulo, group: grupo de enlaces, link: enlace a una pantalla');
            $table->foreignId('permission_id')->nullable()->comment('Permiso que da acceso al ítem (permissions.id). NULL: visible para todos')->constrained('permissions')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->comment('Ítem padre (menu_items.id). NULL en el nivel raíz')->constrained('menu_items')->cascadeOnDelete();
            $table->integer('order')->default(0)->comment('Posición entre los ítems hermanos');
            $table->json('metadata')->nullable()->comment('Datos extra para el frontend, en JSON');
            $table->boolean('is_active')->default(true)->comment('1: activo, 0: oculto');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index(['order', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
