<?php
/**
 * Ejecutor de pruebas automatizadas (sin dependencias externas).
 *
 * Crea una base de pruebas desde cero (por defecto "<base>_test", con las credenciales de
 * config/config.php o de las variables DT_TEST_DB_HOST/USER/PASS/NAME), corre todos los
 * archivos tests/*Test.php y muestra el resultado.
 *
 * Uso: php tests/run.php [filtro]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Sólo por consola.');
}
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Prueba.php';

use App\Core\Config;
use App\Core\DB;
use App\Support\Instalacion;

$db = [
    'host' => getenv('DT_TEST_DB_HOST') ?: (Config::get('db.host') ?: '127.0.0.1'),
    'puerto' => (int) (getenv('DT_TEST_DB_PORT') ?: (Config::get('db.puerto') ?: 3306)),
    'usuario' => getenv('DT_TEST_DB_USER') ?: (Config::get('db.usuario') ?: 'root'),
    'clave' => getenv('DT_TEST_DB_PASS') !== false ? (string) getenv('DT_TEST_DB_PASS') : (string) Config::get('db.clave', ''),
    'nombre' => getenv('DT_TEST_DB_NAME') ?: ((Config::get('db.nombre') ?: 'distri_toro') . '_test'),
];
$pdo = new PDO("mysql:host={$db['host']};port={$db['puerto']};charset=utf8mb4", $db['usuario'], $db['clave'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `{$db['nombre']}`");
$pdo->exec("CREATE DATABASE `{$db['nombre']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
Config::set('db', $db);
DB::usar(DB::conectar($db));
Instalacion::crearEstructura();
Instalacion::datosIniciales(['nombre' => 'Admin pruebas', 'usuario' => 'admin', 'clave' => 'admin123']);
$_SESSION = ['usuario_id' => (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'admin'")];
App\Core\Auth::refrescar();

echo "Base de pruebas: {$db['nombre']}\n\n";
$filtro = $argv[1] ?? '';
$totales = ['ok' => 0, 'falla' => 0];
foreach (glob(__DIR__ . '/*Test.php') as $archivo) {
    require_once $archivo;
    $clase = 'Tests\\' . basename($archivo, '.php');
    if ($filtro && stripos($clase, $filtro) === false) {
        continue;
    }
    echo "▶ " . basename($archivo, '.php') . "\n";
    $obj = new $clase();
    foreach (get_class_methods($obj) as $m) {
        if (!str_starts_with($m, 'test')) {
            continue;
        }
        try {
            $obj->$m();
            $totales['ok']++;
            echo "  ✔ $m\n";
        } catch (Throwable $e) {
            $totales['falla']++;
            echo "  ✘ $m\n      " . $e->getMessage() . "\n      en " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        }
    }
}
echo "\n" . ($totales['falla'] ? "FALLARON {$totales['falla']} de " : 'OK: ') . ($totales['ok'] + $totales['falla']) . " pruebas.\n";
$pdo->exec("DROP DATABASE IF EXISTS `{$db['nombre']}`");
exit($totales['falla'] ? 1 : 0);
