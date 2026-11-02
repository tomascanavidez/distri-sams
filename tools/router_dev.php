<?php
/**
 * Router para el servidor embebido de PHP (desarrollo local):
 *   php -S localhost:8080 -t public tools/router_dev.php
 * Sirve los archivos estáticos de /public y todo lo demás lo envía a public/index.php.
 */

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivo = __DIR__ . '/../public' . $ruta;
if ($ruta !== '/' && is_file($archivo)) {
    return false;
}
if (str_starts_with($ruta, '/install')) {
    $_SERVER['SCRIPT_NAME'] = '/install/index.php';
    require __DIR__ . '/../public/install/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../public/index.php';
