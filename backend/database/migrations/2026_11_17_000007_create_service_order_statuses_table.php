<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estatus de la máquina de estados de una orden de servicio (ver docs/arquitectura_servicios.md).
     *
     * @var list<array{code: string, display_name: string, color: string, sort_order: int, is_final: bool}>
     */
    private const STATUSES = [
        ['code' => 'draft', 'display_name' => 'Borrador', 'color' => '#9E9E9E', 'sort_order' => 10, 'is_final' => false],
        ['code' => 'pending', 'display_name' => 'Pendiente', 'color' => '#2196F3', 'sort_order' => 20, 'is_final' => false],
        ['code' => 'pending_approval', 'display_name' => 'Por aprobar', 'color' => '#FF9800', 'sort_order' => 30, 'is_final' => false],
        ['code' => 'in_progress', 'display_name' => 'En proceso', 'color' => '#3F51B5', 'sort_order' => 40, 'is_final' => false],
        ['code' => 'completed', 'display_name' => 'Terminada', 'color' => '#4CAF50', 'sort_order' => 50, 'is_final' => false],
        ['code' => 'delivered', 'display_name' => 'Entregada', 'color' => '#009688', 'sort_order' => 60, 'is_final' => true],
        ['code' => 'cancelled', 'display_name' => 'Cancelada', 'color' => '#F44336', 'sort_order' => 70, 'is_final' => true],
    ];

    public function up(): void
    {
        Schema::create('service_order_statuses', function (Blueprint $table) {
            $table->comment('Catálogo de estatus de la máquina de estados de las órdenes de servicio');
            $table->id()->comment('Identificador único del estatus');
            $table->string('code', 30)->unique()->comment('Identificador estable que usa el código. draft, pending, pending_approval, in_progress, completed, delivered, cancelled');
            $table->string('display_name', 50)->comment('Texto para mostrar en la interfaz');
            $table->string('color', 7)->nullable()->comment('Color hexadecimal para la interfaz. Por ejemplo #4CAF50');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Posición al ordenar los estatus');
            $table->boolean('is_final')->default(false)->comment('1: estatus final, la orden ya no cambia, 0: la orden puede seguir avanzando');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        DB::statement("ALTER TABLE service_order_statuses ADD CONSTRAINT service_order_statuses_color_check CHECK (color IS NULL OR color ~ '^#[0-9A-Fa-f]{6}$')");

        // Datos de referencia: el código y las llaves foráneas dependen de que existan, y los tests
        // (que no ejecutan seeders) también. Por eso se insertan aquí y no en un seeder.
        $now = now();
        DB::table('service_order_statuses')->insert(
            array_map(fn (array $status) => $status + ['created_at' => $now, 'updated_at' => $now], self::STATUSES)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_statuses');
    }
};
