<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Request;
use App\Core\Response;
use App\Support\Exportador;
use App\Support\Reportes;

final class ReportesController extends Controller
{
    public function index(): void
    {
        $grupos = [];
        foreach (Reportes::catalogo() as $clave => [$titulo, $grupo, $desc]) {
            $grupos[$grupo][$clave] = [$titulo, $desc];
        }
        $this->vista('reportes/index', ['titulo' => 'Reportes', 'grupos' => $grupos]);
    }

    public function ver(string $clave): void
    {
        $cat = Reportes::catalogo();
        if (!isset($cat[$clave])) {
            Response::error(404, 'Reporte inexistente.');
            return;
        }
        $filtros = $_GET;
        $res = Reportes::generar($clave, $filtros);
        $formato = (string) Request::get('formato', '');
        if ($formato !== '') {
            if (!Auth::puede('reportes.exportar')) {
                throw new ErrorNegocio('No tenés permiso para exportar reportes.');
            }
            Exportador::enviar($formato, $res['titulo'] ?? $cat[$clave][0], $res['columnas'], $res['filas'], $res['totales'], $res['subtitulo'], $res['grupo'] ?? null);
            return;
        }
        $producto = !empty($filtros['producto_id']) ? DB::fila('SELECT id, descripcion FROM productos WHERE id = ?', [(int) $filtros['producto_id']]) : null;
        $opciones = in_array('lista_precios', $cat[$clave][3], true) ? [
            'listas' => DB::pares('SELECT id, nombre FROM listas_precios WHERE activo = 1 ORDER BY orden, id'),
            'rubros' => DB::pares('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre'),
            'marcas' => DB::pares('SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre'),
        ] : [];
        $this->vista('reportes/ver', ['titulo' => $cat[$clave][0], 'clave' => $clave, 'def' => $cat[$clave], 'res' => $res, 'producto' => $producto, 'opciones' => $opciones]);
    }
}
