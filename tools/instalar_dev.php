<?php
/**
 * Instalación por consola (desarrollo / pruebas). ¡Borra y recrea la base indicada!
 *
 * Uso: php tools/instalar_dev.php --db=distri_toro [--usuario=root] [--clave=] [--host=localhost] [--demo] [--sin-config]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Sólo por consola.\n");
}
$opt = getopt('', ['db:', 'usuario::', 'clave::', 'host::', 'puerto::', 'demo', 'sin-config', 'debug']);
$db = [
    'host' => $opt['host'] ?? '127.0.0.1',
    'puerto' => (int) ($opt['puerto'] ?? 3306),
    'nombre' => $opt['db'] ?? 'distri_toro',
    'usuario' => $opt['usuario'] ?? 'root',
    'clave' => $opt['clave'] ?? '',
];

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Support\Instalacion;

$pdo = new PDO("mysql:host={$db['host']};port={$db['puerto']};charset=utf8mb4", $db['usuario'], $db['clave'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `{$db['nombre']}`");
$pdo->exec("CREATE DATABASE `{$db['nombre']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
Config::set('db', $db);
DB::usar(DB::conectar($db));

Instalacion::crearEstructura();
Instalacion::datosIniciales(['nombre' => 'Administrador', 'usuario' => 'admin', 'clave' => 'admin123']);
echo "Estructura y datos iniciales creados en {$db['nombre']}.\n";

if (isset($opt['demo'])) {
    App\Support\DatosDemo::cargar(fn ($m) => print("  · $m\n"));
    echo "Datos de demostración cargados.\n";
}
if (!isset($opt['sin-config'])) {
    Instalacion::escribirConfig($db, isset($opt['debug']));
    Instalacion::bloquear();
    echo "config/config.php escrito.\n";
}
