<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\Response;
use App\Services\BackupService;

final class BackupController extends Controller
{
    public function index(): void
    {
        $this->vista('backups/index', ['titulo' => 'Copias de seguridad', 'archivos' => BackupService::listar()]);
    }

    public function generar(): void
    {
        $ruta = BackupService::generar('manual');
        Auditoria::registrar('backups', 'generar', null, null, null, ['archivo' => basename($ruta)], 'Copia de seguridad manual');
        $this->volver('Copia de seguridad generada: ' . basename($ruta));
    }

    public function descargar(string $nombre): void
    {
        $ruta = BackupService::ruta($nombre);
        if (!$ruta) {
            Response::error(404, 'La copia de seguridad no existe.');
            return;
        }
        Auditoria::registrar('backups', 'descargar', null, null, null, ['archivo' => basename($ruta)], 'Descarga de copia de seguridad');
        header('Content-Type: ' . (str_ends_with($ruta, '.gz') ? 'application/gzip' : 'application/sql'));
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
    }
}
