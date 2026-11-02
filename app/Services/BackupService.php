<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use PDO;

/**
 * Copias de seguridad de la base generadas en PHP (sin mysqldump), por lotes para no
 * agotar la memoria en hosting compartido. Se comprimen con gzip si está disponible.
 */
final class BackupService
{
    public static function directorio(): string
    {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Genera un respaldo completo y devuelve la ruta del archivo. */
    public static function generar(string $prefijo = 'backup'): string
    {
        @set_time_limit(600);
        $gzip = function_exists('gzopen');
        $nombre = $prefijo . '_' . date('Ymd_His') . '.sql' . ($gzip ? '.gz' : '');
        $ruta = self::directorio() . '/' . $nombre;
        $fh = $gzip ? gzopen($ruta, 'wb6') : fopen($ruta, 'wb');
        $escribir = fn (string $s) => $gzip ? gzwrite($fh, $s) : fwrite($fh, $s);

        self::volcar($escribir, null, true);

        $gzip ? gzclose($fh) : fclose($fh);
        self::depurar();
        return $ruta;
    }

    /**
     * Vuelca estructura y/o datos con el callback $escribir.
     * $transformar(tabla, columna, valor, tipo): permite reemplazar valores (se usa para generar datos demo con fechas relativas).
     */
    public static function volcar(callable $escribir, ?callable $transformar = null, bool $estructura = true, ?array $soloTablas = null, array $excluirTablas = []): void
    {
        $pdo = DB::pdo();
        $escribir("-- Distribuidora Toro - copia de seguridad\n-- Generada: " . date('d/m/Y H:i:s') . "\n-- Base: " . Config::get('db.nombre') . "\n\n");
        $escribir("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
        $tablas = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tablas as $tabla) {
            if (($soloTablas !== null && !in_array($tabla, $soloTablas, true)) || in_array($tabla, $excluirTablas, true)) {
                continue;
            }
            if ($estructura) {
                $create = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_NUM)[1];
                $escribir("DROP TABLE IF EXISTS `$tabla`;\n$create;\n\n");
            }
            $tipos = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $tipos[$col['Field']] = strtolower($col['Type']);
            }
            $offset = 0;
            $lote = 500;
            do {
                $filas = $pdo->query("SELECT * FROM `$tabla` LIMIT $lote OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
                if (!$filas) {
                    break;
                }
                $cols = '`' . implode('`,`', array_keys($filas[0])) . '`';
                $valores = [];
                foreach ($filas as $f) {
                    $v = [];
                    foreach ($f as $col => $val) {
                        if ($transformar) {
                            $t = $transformar($tabla, $col, $val, $tipos[$col] ?? '');
                            if ($t !== null) {
                                $v[] = $t;
                                continue;
                            }
                        }
                        if ($val === null) {
                            $v[] = 'NULL';
                        } elseif (is_int($val) || is_float($val)) {
                            $v[] = (string) $val;
                        } else {
                            $v[] = $pdo->quote((string) $val);
                        }
                    }
                    $valores[] = '(' . implode(',', $v) . ')';
                }
                $escribir("INSERT INTO `$tabla` ($cols) VALUES\n" . implode(",\n", $valores) . ";\n");
                $offset += $lote;
            } while (count($filas) === $lote);
            $escribir("\n");
        }
        $escribir("SET FOREIGN_KEY_CHECKS = 1;\n");
    }

    /** Lista de respaldos disponibles (más recientes primero). */
    public static function listar(): array
    {
        $archivos = glob(self::directorio() . '/*.sql*') ?: [];
        $out = [];
        foreach ($archivos as $a) {
            $out[] = ['nombre' => basename($a), 'tamano' => filesize($a), 'fecha' => filemtime($a)];
        }
        usort($out, fn ($x, $y) => $y['fecha'] <=> $x['fecha']);
        return $out;
    }

    /** Conserva sólo los N respaldos más recientes. */
    public static function depurar(): void
    {
        $conservar = max(1, (int) Config::get('backup.conservar', 15));
        foreach (array_slice(self::listar(), $conservar) as $b) {
            @unlink(self::directorio() . '/' . $b['nombre']);
        }
    }

    /** Ruta segura de un respaldo por nombre (evita salir de la carpeta). */
    public static function ruta(string $nombre): ?string
    {
        $nombre = basename($nombre);
        if (!preg_match('/^[\w\-]+\.sql(\.gz)?$/', $nombre)) {
            return null;
        }
        $ruta = self::directorio() . '/' . $nombre;
        return is_file($ruta) ? $ruta : null;
    }
}
