<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Paginador;
use App\Core\Request;
use App\Core\Validador;
use App\Services\CuentaCorriente;

final class ProveedoresController extends Controller
{
    public function index(): void
    {
        $w = [];
        $p = [];
        if ($q = trim((string) Request::get('q', ''))) {
            $w[] = '(pr.razon_social LIKE ? OR pr.nombre_fantasia LIKE ? OR pr.cuit LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        $w[] = Request::get('inactivos') ? 'pr.activo = 0' : 'pr.activo = 1';
        $sql = 'SELECT pr.*, (SELECT COALESCE(SUM(importe), 0) FROM cc_proveedores WHERE proveedor_id = pr.id) AS saldo,
                       (SELECT COALESCE(SUM(pendiente), 0) FROM cc_proveedores WHERE proveedor_id = pr.id AND importe > 0 AND vencimiento < CURDATE()) AS vencido
                FROM proveedores pr WHERE ' . implode(' AND ', $w);
        $pag = new Paginador($sql, $p, ['razon' => 'pr.razon_social', 'saldo' => 'saldo'], 'razon');
        $this->vista('proveedores/index', ['titulo' => 'Proveedores', 'pag' => $pag]);
    }

    public function nuevo(): void
    {
        $this->vista('proveedores/form', ['titulo' => 'Nuevo proveedor', 'datos' => ['condicion_iva' => 'RI', 'localidad' => 'Salta', 'plazo_pago_dias' => 15], 'errores' => [], 'id' => null]);
    }

    public function editar(string $id): void
    {
        $this->vista('proveedores/form', ['titulo' => 'Editar proveedor', 'datos' => $this->buscarOFallar('proveedores', (int) $id), 'errores' => [], 'id' => (int) $id]);
    }

    public function guardar(?string $id = null): void
    {
        $id = $id !== null ? (int) $id : null;
        $antes = $id ? $this->buscarOFallar('proveedores', $id) : null;
        $d = [];
        foreach (['razon_social', 'nombre_fantasia', 'cuit', 'condicion_iva', 'direccion', 'localidad', 'telefono', 'email', 'contacto', 'observaciones'] as $c) {
            $d[$c] = Request::texto($c);
        }
        $d['plazo_pago_dias'] = Request::entero('plazo_pago_dias');
        $v = Validador::make($d, ['razon_social' => 'requerido|max:150', 'cuit' => 'cuit', 'condicion_iva' => 'requerido|en:RI,MONO,EX,CF', 'email' => 'email']);
        if ($v->falla()) {
            $this->vista('proveedores/form', ['titulo' => $id ? 'Editar proveedor' : 'Nuevo proveedor', 'datos' => array_merge($antes ?? [], $d), 'errores' => $v->errores(), 'id' => $id]);
            return;
        }
        if ($id) {
            DB::actualizar('proveedores', $d, 'id = :id', ['id' => $id]);
            Auditoria::registrar('proveedores', 'editar', 'proveedor', $id, $antes, $d);
        } else {
            $id = DB::insertar('proveedores', $d + ['activo' => 1, 'creado_en' => ahora()]);
            Auditoria::registrar('proveedores', 'crear', 'proveedor', $id, null, $d);
        }
        $this->redirigir('/proveedores/' . $id, 'Proveedor guardado.');
    }

    public function ver(string $id): void
    {
        $pr = $this->buscarOFallar('proveedores', (int) $id);
        $cc = CuentaCorriente::proveedores();
        $desde = parse_fecha(Request::get('desde')) ?? date('Y-m-d', strtotime('-90 days'));
        $hasta = parse_fecha(Request::get('hasta')) ?? hoy();
        $this->vista('proveedores/ver', [
            'titulo' => $pr['razon_social'], 'pr' => $pr, 'saldo' => $cc->saldo((int) $id), 'antiguedad' => $cc->antiguedad((int) $id),
            'mov' => $cc->movimientos((int) $id, $desde, $hasta), 'desde' => $desde, 'hasta' => $hasta,
            'ordenes' => DB::filas('SELECT * FROM ordenes_compra WHERE proveedor_id = ? ORDER BY id DESC LIMIT 8', [$id]),
            'productos' => (int) DB::valor('SELECT COUNT(*) FROM productos WHERE proveedor_id = ? AND activo = 1', [$id]),
        ]);
    }

    public function alternarEstado(string $id): void
    {
        $pr = $this->buscarOFallar('proveedores', (int) $id);
        $nuevo = (int) $pr['activo'] ? 0 : 1;
        DB::actualizar('proveedores', ['activo' => $nuevo], 'id = :id', ['id' => (int) $id]);
        Auditoria::registrar('proveedores', $nuevo ? 'activar' : 'desactivar', 'proveedor', (int) $id, ['activo' => $pr['activo']], ['activo' => $nuevo]);
        $this->volver($nuevo ? 'Proveedor activado.' : 'Proveedor desactivado.');
    }
}
