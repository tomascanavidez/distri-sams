<?php
/**
 * Genera database/datos_iniciales.sql y database/seed_demo.sql a partir de los
 * instaladores en PHP (así el SQL importable por phpMyAdmin coincide con el instalador web).
 *
 * En seed_demo.sql las fechas se escriben relativas al momento de importar
 * (DATE_ADD(@ahora, INTERVAL n SECOND)), para que el panel siempre muestre datos recientes.
 *
 * Uso: php tools/generar_sql.php [--usuario=root] [--clave=] [--host=127.0.0.1]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Sólo por consola.\n");
}
$opt = getopt('', ['usuario::', 'clave::', 'host::']);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Services\BackupService;
use App\Support\DatosDemo;
use App\Support\Instalacion;

$db = ['host' => $opt['host'] ?? '127.0.0.1', 'puerto' => 3306, 'usuario' => $opt['usuario'] ?? 'root', 'clave' => $opt['clave'] ?? '', 'nombre' => 'distri_toro_generador'];
$pdo = new PDO("mysql:host={$db['host']};charset=utf8mb4", $db['usuario'], $db['clave'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function preparar(PDO $pdo, array $db): void
{
    $pdo->exec("DROP DATABASE IF EXISTS `{$db['nombre']}`");
    $pdo->exec("CREATE DATABASE `{$db['nombre']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    Config::set('db', $db);
    DB::usar(DB::conectar($db));
    App\Core\Ajustes::limpiar();
    Instalacion::crearEstructura();
    Instalacion::datosIniciales(['nombre' => 'Administrador', 'usuario' => 'admin', 'clave' => 'admin123']);
}

function escribir(string $archivo, string $titulo, ?callable $transformar): void
{
    $fh = fopen($archivo, 'wb');
    fwrite($fh, "-- $titulo\n-- Importar DESPUÉS de database/schema.sql, en una base vacía.\n");
    if ($transformar) {
        fwrite($fh, "-- Las fechas son relativas al momento de la importación.\nSET @ahora = NOW();\n");
    }
    BackupService::volcar(fn ($s) => fwrite($fh, $s), $transformar, false, null, ['login_intentos']);
    fclose($fh);
    echo "Generado: $archivo (" . round(filesize($archivo) / 1024) . " KB)\n";
}

// 1) Datos iniciales mínimos (sin demo)
preparar($pdo, $db);
escribir(BASE_PATH . '/database/datos_iniciales.sql', 'Distribuidora Toro - datos iniciales (roles, permisos, parámetros, usuario admin / admin123: cambiar la contraseña al ingresar)', null);

// 2) Datos de demostración completos con fechas relativas
preparar($pdo, $db);
DatosDemo::cargar();
$ref = time();
$transformar = function (string $tabla, string $col, mixed $val, string $tipo) use ($ref): ?string {
    if ($val === null || $val === '') {
        return null;
    }
    if (str_starts_with($tipo, 'datetime')) {
        $d = strtotime((string) $val) - $ref;
        return "DATE_ADD(@ahora, INTERVAL $d SECOND)";
    }
    if ($tipo === 'date') {
        $d = (int) round((strtotime((string) $val) - strtotime(date('Y-m-d', $ref))) / 86400);
        return "DATE_ADD(DATE(@ahora), INTERVAL $d DAY)";
    }
    return null;
};
escribir(BASE_PATH . '/database/seed_demo.sql', 'Distribuidora Toro - datos de demostración completos (incluye los datos iniciales y usuarios demo)', $transformar);
$pdo->exec("DROP DATABASE IF EXISTS `{$db['nombre']}`");
