<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_financings', function (Blueprint $table) {
            $table->comment('Financiamiento de una venta a crédito. Máximo uno por venta');
            $table->id()->comment('Identificador único del financiamiento');
            $table->foreignId('sale_id')->unique()->comment('Venta financiada (sales.id). Una venta tiene a lo mucho un financiamiento')->constrained()->restrictOnDelete();
            $table->string('lender_type', 10)->comment('Quién financia. bank: banco, internal: crédito de la agencia');
            $table->string('bank_name', 100)->nullable()->comment('Nombre del banco. Obligatorio si lender_type es bank');
            $table->string('status', 10)->default('pending')->comment('Estatus del crédito. pending: en trámite, approved: aprobado, rejected: rechazado, disbursed: depositado');
            $table->timestamp('approved_at')->nullable()->comment('Fecha y hora de aprobación. NULL mientras está en trámite o rechazado');
            $table->decimal('down_payment_mxn', 12, 2)->comment('Enganche pactado, en pesos');
            $table->decimal('financed_amount_mxn', 12, 2)->comment('Monto financiado, en pesos. Mayor que cero');
            $table->unsignedSmallInteger('months')->comment('Plazo en meses, de 1 a 120');
            $table->decimal('interest_rate', 5, 2)->comment('Tasa de interés anual, en porcentaje. Por ejemplo 12.50');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        DB::statement("ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_lender_type_check CHECK (lender_type IN ('bank', 'internal'))");
        DB::statement("ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_bank_name_check CHECK (lender_type <> 'bank' OR bank_name IS NOT NULL)");
        DB::statement("ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'disbursed'))");
        // Tiene fecha de aprobación si y solo si el crédito está aprobado o ya depositado.
        DB::statement("ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_approved_check CHECK ((status IN ('approved', 'disbursed')) = (approved_at IS NOT NULL))");
        DB::statement('ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_down_payment_check CHECK (down_payment_mxn >= 0)');
        DB::statement('ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_financed_amount_check CHECK (financed_amount_mxn > 0)');
        DB::statement('ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_months_check CHECK (months BETWEEN 1 AND 120)');
        DB::statement('ALTER TABLE sale_financings ADD CONSTRAINT sale_financings_interest_rate_check CHECK (interest_rate >= 0 AND interest_rate <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_financings');
    }
};
