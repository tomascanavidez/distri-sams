<?php
/**
 * Copia de seguridad de la base (para el cron de Ferozo/DonWeb).
 * Uso: /usr/bin/php /home/USUARIO/public_html/cron/backup.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sólo por consola.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$ruta = App\Services\BackupService::generar('auto');
App\Core\Log::info('Backup automático generado', ['archivo' => basename($ruta), 'bytes' => filesize($ruta)]);
echo 'Copia generada: ' . $ruta . PHP_EOL;
