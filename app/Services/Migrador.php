<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Migraciones versionadas de la base de datos.
 *
 * Cada archivo de database/migrations se llama NNN_descripcion.sql (o .php que devuelve un callable).
 * La versión 001 es la estructura inicial (database/schema.sql). Las demás se aplican en orden
 * y quedan registradas en la tabla `migraciones`.
 */
final class Migrador
{
    public static function directorio(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    /** [version => [nombre, archivo]] ordenadas. */
    public static function disponibles(): array
    {
        $out = [];
        foreach (glob(self::directorio() . '/*.{sql,php}', GLOB_BRACE) ?: [] as $archivo) {
            if (preg_match('/^(\d{3,})_(.+)\.(sql|php)$/', basename($archivo), $m)) {
                $out[$m[1]] = [str_replace('_', ' ', $m[2]), $archivo];
            }
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    public static function aplicadas(): array
    {
        try {
            return DB::pares('SELECT version, aplicada_en FROM migraciones');
        } catch (\Throwable) {
            return [];
        }
    }

    public static function pendientes(): array
    {
        return array_diff_key(self::disponibles(), self::aplicadas());
    }

    /** Aplica las pendientes. Devuelve las versiones aplicadas. */
    public static function migrar(): array
    {
        $hechas = [];
        foreach (self::pendientes() as $version => [$nombre, $archivo]) {
            if (str_ends_with($archivo, '.php')) {
                $fn = require $archivo;
                $fn(DB::pdo());
            } else {
                self::ejecutarSql((string) file_get_contents($archivo));
            }
            DB::query('INSERT INTO migraciones (version, nombre, aplicada_en) VALUES (?, ?, ?)', [$version, $nombre, ahora()]);
            $hechas[] = $version;
        }
        return $hechas;
    }

    /** Marca todas como aplicadas (instalación nueva desde schema.sql). */
    public static function marcarTodas(): void
    {
        foreach (self::disponibles() as $version => [$nombre]) {
            DB::query('INSERT IGNORE INTO migraciones (version, nombre, aplicada_en) VALUES (?, ?, ?)', [$version, $nombre, ahora()]);
        }
    }

    /** Ejecuta un script SQL con varias sentencias (separa por ";" al final de línea). */
    public static function ejecutarSql(string $sql): void
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sentencias = preg_split('/;\s*(\r?\n|$)/', (string) $sql);
        foreach ($sentencias as $s) {
            $s = trim($s);
            if ($s !== '') {
                DB::pdo()->exec($s);
            }
        }
    }
}
