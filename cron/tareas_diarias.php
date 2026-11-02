<?php
/**
 * Tareas diarias (programar una vez por día, por ejemplo a las 3:00):
 *  - copia de seguridad automática de la base de datos;
 *  - bloqueo automático de clientes con deuda vencida (si está activado en Configuración);
 *  - limpieza de intentos de ingreso viejos.
 * Uso: /usr/bin/php /home/USUARIO/public_html/cron/tareas_diarias.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sólo por consola.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Log;
use App\Services\BackupService;
use App\Services\ClienteService;

$ruta = BackupService::generar('auto');
echo 'Copia de seguridad: ' . basename($ruta) . PHP_EOL;

$bloqueados = ClienteService::bloqueoAutomatico();
echo "Clientes bloqueados por deuda: $bloqueados" . PHP_EOL;

$borrados = DB::query('DELETE FROM login_intentos WHERE fecha < DATE_SUB(NOW(), INTERVAL 90 DAY)')->rowCount();
echo "Intentos de ingreso depurados: $borrados" . PHP_EOL;

Log::info('Tareas diarias ejecutadas', ['backup' => basename($ruta), 'bloqueados' => $bloqueados]);
