<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Puestos de los empleados.
     *
     * Se llama `job_titles` y no `jobs` porque Laravel ya tiene una tabla `jobs` (la cola de trabajos).
     * El `code` es un número de dos dígitos. No es el nombre de un rol del sistema: qué rol corresponde a
     * cada puesto no se guarda aquí.
     *
     * @var list<array{code: string, name: string}>
     */
    private const JOB_TITLES = [
        ['code' => '01', 'name' => 'Vendedor'],
        ['code' => '02', 'name' => 'Gerente'],
        ['code' => '03', 'name' => 'Asesor de servicio'],
        ['code' => '04', 'name' => 'Jefe Mecánico'],
        ['code' => '05', 'name' => 'Mecánico'],

    ];

    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $table) {
            $table->comment('Catálogo de puestos de los empleados');
            $table->id()->comment('Identificador único del puesto');
            $table->string('code', 30)->unique()->comment('Código del puesto, de dos dígitos y estable. 01: vendedor, 02: gerente, 03: asesor de servicio, 04: jefe mecánico, 05: mecánico');
            $table->string('name', 80)->comment('Nombre del puesto para mostrar en la interfaz. Por ejemplo Vendedor');
            $table->boolean('is_active')->default(true)->comment('1: puesto en uso, 0: dado de baja. Se desactiva en lugar de borrarse porque los empleados lo referencian');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora de creación del registro');
            $table->timestamp('updated_at')->nullable()->comment('Fecha y hora de la última modificación del registro');
        });

        // Código de dos dígitos, como los de la lista de puestos.
        DB::statement("ALTER TABLE job_titles ADD CONSTRAINT job_titles_code_check CHECK (code ~ '^[0-9]{2}$')");

        // Datos de referencia: los empleados y los seeds dependen de que existan, y los tests (que no
        // ejecutan seeders) también. Por eso se insertan aquí y no en un seeder.
        $now = now();
        DB::table('job_titles')->insert(
            array_map(fn (array $title) => $title + ['created_at' => $now, 'updated_at' => $now], self::JOB_TITLES)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('job_titles');
    }
};
