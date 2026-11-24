<?php

namespace Database\Seeders;

use Generator;
use RuntimeException;

/**
 * Lector de los catálogos CSV de database/seeders/data. Valida el archivo antes de entregar filas.
 */
final class CsvCatalog
{
    /**
     * @param  list<string>  $expectedHeader  Encabezado exacto que debe tener el archivo
     * @return Generator<int, array<string, string>>
     */
    public static function read(string $path, array $expectedHeader): Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir {$path}.");
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header !== $expectedHeader) {
                throw new RuntimeException("Encabezado inesperado en {$path}.");
            }

            while (($line = fgetcsv($handle, escape: '')) !== false) {
                if ($line === [null]) {
                    continue;
                }

                if (count($line) !== count($header)) {
                    throw new RuntimeException("Fila con columnas de más o de menos en {$path}.");
                }

                // Un archivo en Latin-1 deja bytes inválidos: mejor fallar que guardar nombres rotos.
                foreach ($line as $value) {
                    if (! mb_check_encoding($value, 'UTF-8')) {
                        throw new RuntimeException("{$path} no está en UTF-8.");
                    }
                }

                yield array_combine($header, $line);
            }
        } finally {
            fclose($handle);
        }
    }
}
