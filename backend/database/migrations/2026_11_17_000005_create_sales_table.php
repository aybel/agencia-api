<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->unique()->constrained(); // un auto se vende una sola vez
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->date('sale_date');
            $table->decimal('final_price_mxn', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
