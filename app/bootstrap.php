<?php
/**
 * Arranque de la aplicación: constantes, autoloader PSR-4 propio,
 * configuración, zona horaria y manejo centralizado de errores.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('APP_VERSION', '1.0.0');

// Autoloader PSR-4: App\Foo\Bar => app/Foo/Bar.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'App\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $relativo = substr($clase, strlen($prefijo));
    $archivo = APP_PATH . '/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

require APP_PATH . '/helpers.php';

date_default_timezone_set('America/Argentina/Salta');
mb_internal_encoding('UTF-8');
setlocale(LC_TIME, 'es_AR.UTF-8', 'es_ES.UTF-8', 'es');

App\Core\Config::cargar(BASE_PATH . '/config/config.php');
App\Core\ManejadorErrores::registrar();
