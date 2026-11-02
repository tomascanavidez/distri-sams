<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Support\Exportador;

final class AuditoriaController extends Controller
{
    public function index(): void
    {
        $w = [];
        $p = [];
        foreach (['modulo' => 'a.modulo', 'accion' => 'a.accion', 'entidad' => 'a.entidad'] as $k => $col) {
            if ($v = Request::get($k)) {
                $w[] = "$col = ?";
                $p[] = (string) $v;
            }
        }
        if ($u = (int) Request::get('usuario_id', 0)) {
            $w[] = 'a.usuario_id = ?';
            $p[] = $u;
        }
        if ($id = (int) Request::get('entidad_id', 0)) {
            $w[] = 'a.entidad_id = ?';
            $p[] = $id;
        }
        if ($d = parse_fecha(Request::get('desde'))) {
            $w[] = 'a.fecha >= ?';
            $p[] = $d . ' 00:00:00';
        }
        if ($h = parse_fecha(Request::get('hasta'))) {
            $w[] = 'a.fecha <= ?';
            $p[] = $h . ' 23:59:59';
        }
        if ($q = trim((string) Request::get('q', ''))) {
            $w[] = '(a.descripcion LIKE ? OR a.datos_nuevos LIKE ? OR a.datos_anteriores LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        $sql = 'SELECT a.*, u.nombre AS usuario FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        if ($f = Request::get('formato')) {
            if (!Auth::puede('auditoria.exportar')) {
                throw new ErrorNegocio('No tenés permiso para exportar la auditoría.');
            }
            Exportador::enviar((string) $f, 'Auditoría', ['fecha' => ['Fecha', 'fecha_hora'], 'usuario' => ['Usuario', 'texto'], 'modulo' => ['Módulo', 'texto'], 'accion' => ['Acción', 'texto'],
                'entidad' => ['Entidad', 'texto'], 'entidad_id' => ['ID', 'texto'], 'descripcion' => ['Descripción', 'texto'], 'datos_anteriores' => ['Antes', 'texto'], 'datos_nuevos' => ['Después', 'texto'], 'ip' => ['IP', 'texto']],
                DB::filas($sql . ' ORDER BY a.id DESC LIMIT 20000', $p));
            return;
        }
        $pag = new Paginador($sql, $p, ['fecha' => 'a.id'], 'fecha', 'desc', 50);
        $this->vista('auditoria/index', [
            'titulo' => 'Auditoría', 'pag' => $pag,
            'modulos' => DB::columna('SELECT DISTINCT modulo FROM auditoria ORDER BY modulo'),
            'usuarios' => DB::pares('SELECT id, nombre FROM usuarios ORDER BY nombre'),
        ]);
    }
}
