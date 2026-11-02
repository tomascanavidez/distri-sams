<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Core\Validador;
use App\Services\ComisionService;

final class PreventistasController extends Controller
{
    public function index(): void
    {
        $desde = date('Y-m-01');
        $filas = DB::filas(
            "SELECT p.*, u.usuario,
                    (SELECT GROUP_CONCAT(nombre SEPARATOR ', ') FROM zonas WHERE preventista_id = p.id) AS zonas,
                    (SELECT COUNT(*) FROM clientes WHERE preventista_id = p.id AND estado <> 'inactivo') AS clientes,
                    (SELECT COALESCE(SUM(CASE WHEN c.tipo LIKE 'NC%' THEN -c.total ELSE c.total END), 0) FROM comprobantes c WHERE c.preventista_id = p.id AND c.estado = 'emitido' AND c.tipo IN ('FA','FB','FC','RM','NCA','NCB','NCC') AND c.fecha >= ?) AS ventas_mes,
                    (SELECT COUNT(*) FROM visitas v WHERE v.preventista_id = p.id AND v.fecha >= ?) AS visitas_mes,
                    (SELECT COUNT(*) FROM visitas v WHERE v.preventista_id = p.id AND v.fecha >= ? AND v.resultado = 'pedido') AS efectivas_mes
             FROM preventistas p LEFT JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.activo DESC, p.nombre",
            [$desde . ' 00:00:00', $desde . ' 00:00:00', $desde . ' 00:00:00']
        );
        $this->vista('preventistas/index', ['titulo' => 'Preventistas', 'filas' => $filas]);
    }

    private function formulario(array $datos, array $errores, ?int $id): void
    {
        $this->vista('preventistas/form', [
            'titulo' => $id ? 'Editar preventista' : 'Nuevo preventista', 'datos' => $datos, 'errores' => $errores, 'id' => $id,
            'usuarios' => DB::pares("SELECT u.id, CONCAT(u.nombre, ' (', u.usuario, ')') FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.activo = 1 ORDER BY r.slug = 'preventista' DESC, u.nombre"),
            'zonas' => DB::filas('SELECT id, nombre, preventista_id FROM zonas WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function nuevo(): void
    {
        $this->formulario(['comision_porc' => 3, 'comision_base' => 'ventas', 'activo' => 1], [], null);
    }

    public function editar(string $id): void
    {
        $this->formulario($this->buscarOFallar('preventistas', (int) $id), [], (int) $id);
    }

    public function guardar(?string $id = null): void
    {
        $id = $id !== null ? (int) $id : null;
        $antes = $id ? $this->buscarOFallar('preventistas', $id) : null;
        $d = [
            'nombre' => Request::texto('nombre'), 'telefono' => Request::texto('telefono'), 'usuario_id' => Request::entero('usuario_id') ?: null,
            'comision_porc' => Request::input('comision_porc', '0'), 'comision_base' => Request::texto('comision_base', 'ventas'), 'activo' => Request::entero('activo', 1),
        ];
        $v = Validador::make($d, ['nombre' => 'requerido|max:100', 'comision_porc' => 'numerico|min:0', 'comision_base' => 'en:ventas,cobranzas']);
        if ($v->falla()) {
            $this->formulario(array_merge($antes ?? [], $d), $v->errores(), $id);
            return;
        }
        if ($d['usuario_id'] && DB::valor('SELECT id FROM preventistas WHERE usuario_id = ? AND id <> ?', [$d['usuario_id'], $id ?? 0])) {
            throw new ErrorNegocio('Ese usuario ya está asociado a otro preventista.');
        }
        $d['comision_porc'] = parse_decimal($d['comision_porc']);
        DB::transaccion(function () use (&$id, $d, $antes) {
            if ($id) {
                DB::actualizar('preventistas', $d, 'id = :id', ['id' => $id]);
                Auditoria::registrar('preventistas', 'editar', 'preventista', $id, $antes, $d);
            } else {
                $id = DB::insertar('preventistas', $d);
                Auditoria::registrar('preventistas', 'crear', 'preventista', $id, null, $d);
            }
            $zonas = array_map('intval', (array) Request::input('zonas', []));
            DB::query('UPDATE zonas SET preventista_id = NULL WHERE preventista_id = ?', [$id]);
            if ($zonas) {
                DB::query('UPDATE zonas SET preventista_id = ? WHERE id IN (' . DB::marcadores($zonas) . ')', array_merge([$id], $zonas));
                if (Request::input('asignar_clientes')) {
                    DB::query('UPDATE clientes SET preventista_id = ? WHERE zona_id IN (' . DB::marcadores($zonas) . ')', array_merge([$id], $zonas));
                }
            }
        });
        $this->redirigir('/preventistas', 'Preventista guardado.');
    }

    public function visitas(): void
    {
        $w = [];
        $p = [];
        $desde = parse_fecha(Request::get('desde')) ?? hoy();
        $hasta = parse_fecha(Request::get('hasta')) ?? hoy();
        $w[] = 'v.fecha BETWEEN ? AND ?';
        array_push($p, $desde . ' 00:00:00', $hasta . ' 23:59:59');
        if ($pr = (int) Request::get('preventista_id', 0)) {
            $w[] = 'v.preventista_id = ?';
            $p[] = $pr;
        }
        if ($r = Request::get('resultado')) {
            $w[] = 'v.resultado = ?';
            $p[] = (string) $r;
        }
        $sql = 'SELECT v.*, c.nombre_comercio, c.direccion, z.nombre AS zona, pr.nombre AS preventista, pe.numero AS pedido_numero, pe.total AS pedido_total
                FROM visitas v JOIN clientes c ON c.id = v.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id LEFT JOIN preventistas pr ON pr.id = v.preventista_id
                LEFT JOIN pedidos pe ON pe.id = v.pedido_id WHERE ' . implode(' AND ', $w);
        $pag = new Paginador($sql, $p, ['fecha' => 'v.fecha'], 'fecha', 'desc', 50);
        $resumen = DB::filas("SELECT pr.nombre, COUNT(*) AS visitas, SUM(v.resultado = 'pedido') AS con_pedido FROM visitas v LEFT JOIN preventistas pr ON pr.id = v.preventista_id WHERE " . implode(' AND ', $w) . ' GROUP BY pr.nombre', $p);
        $this->vista('preventistas/visitas', ['titulo' => 'Visitas de preventistas', 'pag' => $pag, 'resumen' => $resumen, 'desde' => $desde, 'hasta' => $hasta, 'preventistas' => DB::pares('SELECT id, nombre FROM preventistas ORDER BY nombre')]);
    }

    public function comisiones(): void
    {
        $prev = (int) Request::get('preventista_id', 0);
        $desde = parse_fecha(Request::get('desde')) ?? date('Y-m-01', strtotime('first day of last month'));
        $hasta = parse_fecha(Request::get('hasta')) ?? date('Y-m-t', strtotime('last day of last month'));
        $calculo = $prev ? ComisionService::calcular($prev, $desde, $hasta) : null;
        $liquidaciones = DB::filas('SELECT l.*, p.nombre AS preventista FROM comisiones_liquidaciones l JOIN preventistas p ON p.id = l.preventista_id ORDER BY l.id DESC LIMIT 50');
        $this->vista('preventistas/comisiones', [
            'titulo' => 'Comisiones', 'prev' => $prev, 'desde' => $desde, 'hasta' => $hasta, 'calculo' => $calculo, 'liquidaciones' => $liquidaciones,
            'preventistas' => DB::pares('SELECT id, nombre FROM preventistas WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function liquidar(): void
    {
        $id = ComisionService::liquidar(Request::entero('preventista_id'), (string) parse_fecha(Request::texto('desde')), (string) parse_fecha(Request::texto('hasta')), Request::texto('observaciones'));
        $this->redirigir('/preventistas/comisiones/' . $id, 'Liquidación generada.');
    }

    public function verLiquidacion(string $id): void
    {
        $l = DB::fila('SELECT l.*, p.nombre AS preventista FROM comisiones_liquidaciones l JOIN preventistas p ON p.id = l.preventista_id WHERE l.id = ?', [(int) $id]);
        if (!$l) {
            $this->buscarOFallar('comisiones_liquidaciones', (int) $id);
        }
        $docs = $l['base_calculo'] === 'cobranzas'
            ? DB::filas("SELECT r.fecha, CONCAT('RC ', LPAD(r.numero, 8, '0')) AS numero, c.nombre_comercio, r.total AS base FROM recibos r JOIN clientes c ON c.id = r.cliente_id WHERE r.liquidacion_id = ? ORDER BY r.fecha", [$id])
            : DB::filas("SELECT cp.fecha, CONCAT(cp.tipo, ' ', LPAD(cp.numero, 8, '0')) AS numero, c.nombre_comercio, (CASE WHEN cp.tipo LIKE 'NC%' THEN -1 ELSE 1 END) * (cp.total - cp.iva_21 - cp.iva_105 - cp.impuestos_internos) AS base FROM comprobantes cp JOIN clientes c ON c.id = cp.cliente_id WHERE cp.liquidacion_id = ? ORDER BY cp.fecha", [$id]);
        if ($f = Request::get('formato')) {
            \App\Support\Exportador::enviar((string) $f, 'Liquidación de comisiones N° ' . $l['id'], ['fecha' => ['Fecha', 'fecha'], 'numero' => ['Comprobante', 'texto'], 'nombre_comercio' => ['Cliente', 'texto'], 'base' => ['Base', 'money']],
                $docs, ['nombre_comercio' => 'Base total / comisión ' . num($l['porcentaje']) . '%', 'base' => $l['importe_base']], $l['preventista'] . ' · ' . fecha($l['desde']) . ' al ' . fecha($l['hasta']) . ' · Comisión ' . money($l['importe']));
            return;
        }
        $this->vista('preventistas/liquidacion', ['titulo' => 'Liquidación N° ' . $l['id'], 'l' => $l, 'docs' => $docs]);
    }

    public function estadoLiquidacion(string $id): void
    {
        ComisionService::cambiarEstado((int) $id, (string) Request::texto('estado'));
        $this->volver('Liquidación actualizada.');
    }
}
