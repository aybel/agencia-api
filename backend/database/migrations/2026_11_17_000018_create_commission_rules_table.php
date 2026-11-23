<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reglas de comisión por sucursal y condición del auto.
     *
     * Una regla no se edita: para cambiar una tasa se agrega otra con una nueva `effective_from` y la
     * anterior queda como historial. Para elegir la regla de una venta se toma, entre las activas y ya
     * vigentes en la fecha de cierre, la más específica:
     *   1. sucursal de la venta + condición exacta del auto
     *   2. sucursal de la venta + 'all'
     *   3. global (branch_id NULL) + condición exacta
     *   4. global + 'all'
     * y dentro del mismo nivel, la de `effective_from` más reciente. Si no hay ninguna, la venta no se cierra.
     */
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->comment('Reglas de comisión por sucursal y condición del auto. Una regla nueva reemplaza a la anterior según su fecha de vigencia');
            $table->id()->comment('Identificador único de la regla');
            $table->foreignId('branch_id')->nullable()->comment('Sucursal a la que aplica (branches.id). NULL: regla global para todas las sucursales')->constrained()->restrictOnDelete();
            $table->string('car_condition', 4)->default('all')->comment('Condición del auto a la que aplica. new: nuevo, used: usado, all: ambos');
            $table->decimal('rate', 5, 4)->comment('Tasa de comisión como fracción del precio neto de la venta. 0.0150 equivale a 1.5 por ciento. Entre 0 y 1');
            $table->date('effective_from')->comment('Fecha desde la que la regla es vigente. Aplica a ventas cerradas en esa fecha o después, hasta que otra más reciente la reemplace');
            $table->boolean('is_active')->default(true)->comment('1: regla en uso, 0: desactivada y no se aplica');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_car_condition_check CHECK (car_condition IN ('new', 'used', 'all'))");
        // La tasa puede ser 0 (una regla "sin comisión", por ejemplo para autos usados).
        DB::statement('ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_rate_check CHECK (rate >= 0 AND rate <= 1)');
        // Una sola regla por sucursal, condición y fecha. NULLS NOT DISTINCT hace que dos reglas globales
        // (branch_id NULL) iguales también se rechacen: sin él, un UNIQUE normal las dejaría pasar.
        // Este índice también sirve para buscar la regla aplicable.
        DB::statement('CREATE UNIQUE INDEX commission_rules_branch_condition_from_unique ON commission_rules (branch_id, car_condition, effective_from) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
