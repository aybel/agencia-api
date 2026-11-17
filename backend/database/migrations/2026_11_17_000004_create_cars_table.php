<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->integer('model_year');
            $table->integer('mileage');
            $table->decimal('price_mxn', 12, 2); // precio de lista
            $table->string('status', 10)->default('new');
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->timestamps();
        });

        // CHECK en lugar de ENUM nativo (ver docs/decisiones.md, decisión 2)
        DB::statement("ALTER TABLE cars ADD CONSTRAINT cars_status_check CHECK (status IN ('new', 'used', 'sold'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
