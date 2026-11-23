<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagos aplicados a una factura. Una factura admite varios (anticipo y liquidación). Además de las formas
     * de pago habituales, `financing` es el depósito del banco o del crédito interno y `trade_in` el valor de un
     * auto recibido a cuenta, de modo que el saldo de una venta es siempre total menos la suma de sus pagos. Que la suma
     * no exceda el total de la factura lo valida la aplicación, porque un CHECK no puede consultar otra tabla.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->comment('Pagos aplicados a una factura. Una factura admite varios');
            $table->id()->comment('Identificador único del pago');
            $table->foreignId('invoice_id')->comment('Factura que se paga (invoices.id)')->constrained()->restrictOnDelete();
            $table->decimal('amount_mxn', 12, 2)->comment('Monto del pago en pesos. Mayor que cero');
            $table->string('method', 15)->comment('Forma de pago. cash: efectivo, card: tarjeta, transfer: transferencia, check: cheque, financing: depósito del crédito, trade_in: auto recibido a cuenta');
            $table->string('reference', 100)->nullable()->comment('Folio del terminal, número de transferencia o de cheque');
            $table->timestamp('paid_at')->comment('Fecha y hora en que se recibió el pago');
            $table->foreignId('received_by_user_id')->nullable()->comment('Usuario que recibió el pago (users.id)')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');

            $table->index('invoice_id');
            $table->index('paid_at');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount_mxn > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'card', 'transfer', 'check', 'financing', 'trade_in'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
