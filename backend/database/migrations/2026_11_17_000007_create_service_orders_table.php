<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained(); // NULL cuando el auto es de la agencia
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->date('check_in_date');
            $table->date('delivery_date')->nullable();
            $table->decimal('total_mxn', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
