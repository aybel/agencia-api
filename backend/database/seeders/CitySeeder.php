<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CitySeeder extends Seeder
{
    /**
     * Carga los municipios y alcaldías desde data/cities.csv (UTF-8, catálogo del INEGI).
     *
     * Requiere StateSeeder: cada municipio se liga a su entidad por la clave `CVE_ENT`.
     * Se puede ejecutar varias veces: solo inserta los municipios cuya pareja (entidad, clave) aún no existe.
     * La clave del municipio se repite entre entidades, por eso la pareja identifica el registro.
     */
    public function run(): void
    {
        $stateIds = DB::table('states')->pluck('id', 'code');

        $existing = DB::table('cities')
            ->join('states', 'states.id', '=', 'cities.state_id')
            ->select('states.code as state_code', 'cities.code')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->state_code.'-'.$row->code => true]);

        $now = now();
        $rows = [];

        foreach (CsvCatalog::read(__DIR__.'/data/cities.csv', ['CVE_ENT', 'NOM_ENT', 'CVE_MUN', 'NOM_MUN']) as $row) {
            if (! $stateIds->has($row['CVE_ENT'])) {
                throw new RuntimeException("La entidad {$row['CVE_ENT']} no existe en states: ejecuta StateSeeder antes.");
            }

            if ($existing->has($row['CVE_ENT'].'-'.$row['CVE_MUN'])) {
                continue;
            }

            $rows[] = [
                'state_id' => $stateIds[$row['CVE_ENT']],
                'code' => $row['CVE_MUN'],
                'name' => $row['NOM_MUN'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Lotes de 500 filas: una sola sentencia con miles de parámetros excede el límite del driver.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('cities')->insert($chunk);
        }
    }
}
