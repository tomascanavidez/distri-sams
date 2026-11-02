<?php
/**
 * Punto de entrada único de la aplicación.
 */

declare(strict_types=1);

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (!Config::instalado()) {
    header('Location: ' . url('/install/'));
    exit;
}

Session::iniciar();
$router = require APP_PATH . '/routes.php';
$router->despachar(Request::metodo(), Request::ruta());
