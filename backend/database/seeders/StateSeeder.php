<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StateSeeder extends Seeder
{
    /**
     * Carga las 32 entidades federativas desde data/states.csv (UTF-8, catálogo del INEGI).
     *
     * Se puede ejecutar varias veces: solo inserta las entidades cuyo `code` aún no existe.
     * Los ids salen del orden del archivo, y CitySeeder y los seeds de sucursales y clientes los consultan por `code`.
     */
    public function run(): void
    {
        $existing = DB::table('states')->pluck('code')->flip();
        $now = now();
        $rows = [];

        foreach (CsvCatalog::read(__DIR__.'/data/states.csv', ['code', 'short_name', 'name']) as $row) {
            if ($existing->has($row['code'])) {
                continue;
            }

            $rows[] = $row + ['created_at' => $now, 'updated_at' => $now];
        }

        DB::table('states')->insert($rows);

        if (DB::table('states')->count() !== 32) {
            throw new RuntimeException('states debe tener 32 entidades federativas.');
        }
    }
}
