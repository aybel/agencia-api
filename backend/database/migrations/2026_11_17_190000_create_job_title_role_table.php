<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles que se sugieren al crear el usuario de un empleado, según su puesto. Es solo una
     * sugerencia: el rol real del usuario sigue siendo el de Spatie (model_has_roles).
     *
     * Va después de create_permission_tables porque referencia `roles`.
     */
    public function up(): void
    {
        Schema::create('job_title_role', function (Blueprint $table) {
            $table->comment('Roles sugeridos para cada puesto. No otorga permisos: solo precarga el rol al crear el usuario de un empleado');
            $table->foreignId('job_title_id')->comment('Puesto (job_titles.id)')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->comment('Rol sugerido para el puesto (roles.id)')->constrained('roles')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->primary(['job_title_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_title_role');
    }
};
