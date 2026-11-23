<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de cambios de vendedor. Es solo de inserción: no tiene updated_at y la aplicación
     * nunca la modifica ni la borra.
     */
    public function up(): void
    {
        Schema::create('sale_transfers', function (Blueprint $table) {
            $table->comment('Historial de cambios de vendedor de una venta. Solo se inserta, nunca se modifica');
            $table->id()->comment('Identificador único de la transferencia');
            $table->foreignId('sale_id')->comment('Venta transferida (sales.id)')->constrained()->restrictOnDelete();
            $table->foreignId('from_employee_id')->comment('Vendedor que entrega la venta (employees.id)')->constrained('employees')->restrictOnDelete();
            $table->foreignId('to_employee_id')->comment('Vendedor que la recibe (employees.id). Distinto del anterior')->constrained('employees')->restrictOnDelete();
            $table->text('reason')->comment('Motivo de la transferencia: renuncia, vacaciones, reasignación');
            $table->foreignId('authorized_by_user_id')->nullable()->comment('Gerente que autoriza (users.id). NULL cuando el dueño transfiere su propia venta en borrador')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->comment('Fecha y hora de la transferencia');

            $table->index(['sale_id', 'created_at']);
        });

        DB::statement('ALTER TABLE sale_transfers ADD CONSTRAINT sale_transfers_distinct_employees_check CHECK (from_employee_id <> to_employee_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_transfers');
    }
};
