<?php
/**
 * Migración 001: estructura inicial. Ejecuta database/schema.sql.
 * En una instalación nueva el instalador la marca como aplicada.
 */

declare(strict_types=1);

return static function (PDO $pdo): void {
    App\Services\Migrador::ejecutarSql((string) file_get_contents(BASE_PATH . '/database/schema.sql'));
};
