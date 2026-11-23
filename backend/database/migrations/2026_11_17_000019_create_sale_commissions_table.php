<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada fila explica por sí sola cómo se calculó su importe:
     *
     *   commission_amount_mxn = base_amount_mxn x commission_rate x percentage / 100
     *
     * - base_amount_mxn: precio neto de la venta antes de impuestos (sales.net_sale_price_mxn), copiado al cerrar.
     * - commission_rate: tasa de la regla aplicada (commission_rules.rate), copiada, porque la regla puede cambiar.
     * - percentage: parte del total de la comisión que le toca a este vendedor (reparto entre captador y cierre).
     *
     * El CHECK de la fórmula admite un centavo de diferencia: así el reparto puede absorber el redondeo
     * para que las filas de una venta sumen exactamente el total. Que los porcentajes de una venta sumen 100
     * lo valida la aplicación, porque un CHECK no puede sumar filas de otras filas.
     */
    public function up(): void
    {
        Schema::create('sale_commissions', function (Blueprint $table) {
            $table->comment('Reparto de la comisión de una venta entre quien la captura y quien la cierra');
            $table->id()->comment('Identificador único de la comisión');
            $table->foreignId('sale_id')->comment('Venta que genera la comisión (sales.id)')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->comment('Vendedor que recibe la comisión (employees.id)')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->comment('Regla de comisión que se aplicó (commission_rules.id)')->constrained()->restrictOnDelete();
            $table->string('role', 10)->comment('Participación. sole: vendió sin transferencia, capturer: quien capturó la venta, closer: quien la cerró');
            $table->decimal('percentage', 5, 2)->comment('Parte del total de la comisión de la venta que le corresponde a este vendedor, de 0.01 a 100. Los de una venta deben sumar 100');
            $table->decimal('base_amount_mxn', 12, 2)->comment('Base de cálculo en pesos: precio neto de la venta antes de impuestos, copia de sales.net_sale_price_mxn al cerrar la venta');
            $table->decimal('commission_rate', 5, 4)->comment('Tasa aplicada como fracción, copia de la regla al cerrar la venta. 0.0150 equivale a 1.5 por ciento');
            $table->decimal('commission_amount_mxn', 12, 2)->comment('Importe de la comisión en pesos = base x tasa x porcentaje de reparto, con un centavo de tolerancia por redondeo. Nunca mayor a la base');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->unique(['sale_id', 'employee_id']); // un vendedor tiene una sola fila por venta
            $table->index('employee_id');
            $table->index('commission_rule_id');
        });

        DB::statement("ALTER TABLE sale_commissions ADD CONSTRAINT sale_commissions_role_check CHECK (role IN ('sole', 'capturer', 'closer'))");
        DB::statement('ALTER TABLE sale_commissions ADD CONSTRAINT sale_commissions_percentage_check CHECK (percentage > 0 AND percentage <= 100)');
        DB::statement('ALTER TABLE sale_commissions ADD CONSTRAINT sale_commissions_base_check CHECK (base_amount_mxn >= 0)');
        DB::statement('ALTER TABLE sale_commissions ADD CONSTRAINT sale_commissions_rate_check CHECK (commission_rate >= 0 AND commission_rate <= 1)');
        // La comisión es una fracción de la base: nunca puede superarla.
        DB::statement('ALTER TABLE sale_commissions ADD CONSTRAINT sale_commissions_amount_check CHECK (commission_amount_mxn >= 0 AND commission_amount_mxn <= base_amount_mxn)');
        DB::statement('ALTER TABLE sale_commissions ADD CONSTRAINT sale_commissions_formula_check CHECK (abs(commission_amount_mxn - base_amount_mxn * commission_rate * percentage / 100) <= 0.01)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_commissions');
    }
};
