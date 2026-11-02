<?php
/**
 * Aplica las migraciones pendientes de la base de datos.
 * Uso: php cron/migrar.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sólo por consola.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$hechas = App\Services\Migrador::migrar();
echo $hechas ? 'Migraciones aplicadas: ' . implode(', ', $hechas) . PHP_EOL : 'La base ya está actualizada.' . PHP_EOL;
