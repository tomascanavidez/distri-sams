<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\CajaService;
use App\Services\FinanzasService;

final class FinanzasController extends Controller
{
    public function bancos(): void
    {
        $cuentas = DB::filas('SELECT * FROM cuentas_bancarias WHERE activo = 1 ORDER BY id');
        foreach ($cuentas as &$c) {
            $c['saldo'] = CajaService::saldoBanco((int) $c['id']);
        }
        unset($c);
        $this->vista('finanzas/bancos', ['titulo' => 'Bancos y billeteras', 'cuentas' => $cuentas]);
    }

    public function cuenta(string $id): void
    {
        $c = $this->buscarOFallar('cuentas_bancarias', (int) $id);
        $w = ['m.cuenta_id = ?'];
        $p = [(int) $id];
        if ($d = parse_fecha(Request::get('desde'))) {
            $w[] = 'm.fecha >= ?';
            $p[] = $d . ' 00:00:00';
        }
        if ($h = parse_fecha(Request::get('hasta'))) {
            $w[] = 'm.fecha <= ?';
            $p[] = $h . ' 23:59:59';
        }
        $pag = new Paginador('SELECT m.*, u.nombre AS usuario FROM banco_movimientos m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE ' . implode(' AND ', $w), $p, ['fecha' => 'm.id'], 'fecha', 'desc', 50);
        $this->vista('finanzas/cuenta', ['titulo' => $c['nombre'], 'c' => $c, 'pag' => $pag, 'saldo' => CajaService::saldoBanco((int) $id)]);
    }

    public function movimientoBanco(): void
    {
        $cuenta = Request::entero('cuenta_id');
        $tipo = Request::texto('tipo');
        $importe = Request::decimal('importe');
        $concepto = Request::texto('concepto') ?? '';
        if ($concepto === '' || $importe <= 0) {
            throw new ErrorNegocio('Indicá concepto e importe.');
        }
        if ($tipo === 'transferencia') {
            $destino = Request::entero('cuenta_destino_id');
            if (!$destino || $destino === $cuenta) {
                throw new ErrorNegocio('Elegí una cuenta de destino distinta.');
            }
            DB::transaccion(function () use ($cuenta, $destino, $importe, $concepto) {
                CajaService::movimientoBanco($cuenta, 'egreso', 'Transferencia: ' . $concepto, $importe, null, 'transferencia');
                CajaService::movimientoBanco($destino, 'ingreso', 'Transferencia: ' . $concepto, $importe, null, 'transferencia');
            });
        } else {
            CajaService::movimientoBanco($cuenta, $tipo === 'egreso' ? 'egreso' : 'ingreso', $concepto, $importe, Request::texto('referencia'), 'manual');
        }
        Auditoria::registrar('finanzas', 'movimiento_banco', 'cuenta_bancaria', $cuenta, null, ['tipo' => $tipo, 'importe' => $importe, 'concepto' => $concepto]);
        $this->volver('Movimiento registrado.');
    }

    public function cheques(): void
    {
        $w = [];
        $p = [];
        $estado = Request::get('estado', 'cartera');
        if ($estado !== 'todos') {
            $w[] = 'ch.estado = ?';
            $p[] = (string) $estado;
        }
        if ($q = trim((string) Request::get('q', ''))) {
            $w[] = '(ch.numero LIKE ? OR ch.librador LIKE ? OR c.nombre_comercio LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        $sql = 'SELECT ch.*, c.nombre_comercio AS cliente, pr.razon_social AS proveedor, cb.nombre AS cuenta FROM cheques ch
                LEFT JOIN clientes c ON c.id = ch.cliente_id LEFT JOIN proveedores pr ON pr.id = ch.proveedor_id LEFT JOIN cuentas_bancarias cb ON cb.id = ch.cuenta_deposito_id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        $pag = new Paginador($sql, $p, ['fecha_pago' => 'ch.fecha_pago', 'importe' => 'ch.importe'], 'fecha_pago', 'asc', 50);
        $totalCartera = (float) DB::valor("SELECT COALESCE(SUM(importe), 0) FROM cheques WHERE estado = 'cartera'");
        $this->vista('finanzas/cheques', [
            'titulo' => 'Cheques de terceros', 'pag' => $pag, 'totalCartera' => $totalCartera,
            'cuentas' => DB::pares("SELECT id, nombre FROM cuentas_bancarias WHERE activo = 1 AND tipo = 'banco' ORDER BY id"),
        ]);
    }

    public function depositarCheque(string $id): void
    {
        FinanzasService::depositarCheque((int) $id, Request::entero('cuenta_id'), parse_fecha(Request::texto('fecha')) ? parse_fecha(Request::texto('fecha')) . ' ' . date('H:i:s') : null);
        $this->volver('Cheque depositado.');
    }

    public function rechazarCheque(string $id): void
    {
        FinanzasService::rechazarCheque((int) $id, Request::decimal('gastos'), $this->motivoAnulacion());
        $this->volver('Cheque marcado como rechazado. Se cargó el importe en la cuenta corriente del cliente.');
    }

    public function gastos(): void
    {
        $w = [];
        $p = [];
        $desde = parse_fecha(Request::get('desde')) ?? date('Y-m-01');
        $hasta = parse_fecha(Request::get('hasta')) ?? hoy();
        $w[] = 'g.fecha BETWEEN ? AND ?';
        array_push($p, $desde, $hasta);
        if ($cat = (int) Request::get('categoria_id', 0)) {
            $w[] = 'g.categoria_id = ?';
            $p[] = $cat;
        }
        $sql = 'SELECT g.*, c.nombre AS categoria, cb.nombre AS cuenta, v.patente, u.nombre AS usuario FROM gastos g JOIN gastos_categorias c ON c.id = g.categoria_id
                LEFT JOIN cuentas_bancarias cb ON cb.id = g.cuenta_bancaria_id LEFT JOIN vehiculos v ON v.id = g.vehiculo_id LEFT JOIN usuarios u ON u.id = g.usuario_id
                WHERE ' . implode(' AND ', $w);
        $pag = new Paginador($sql, $p, ['fecha' => 'g.fecha', 'importe' => 'g.importe'], 'fecha', 'desc', 50);
        $porCategoria = DB::filas("SELECT c.nombre, SUM(g.importe) AS total FROM gastos g JOIN gastos_categorias c ON c.id = g.categoria_id WHERE g.estado = 'registrado' AND g.fecha BETWEEN ? AND ? GROUP BY c.nombre ORDER BY total DESC", [$desde, $hasta]);
        $this->vista('finanzas/gastos', [
            'titulo' => 'Gastos', 'pag' => $pag, 'porCategoria' => $porCategoria, 'desde' => $desde, 'hasta' => $hasta,
            'categorias' => DB::pares('SELECT id, nombre FROM gastos_categorias WHERE activo = 1 ORDER BY nombre'),
            'cuentas' => DB::pares('SELECT id, nombre FROM cuentas_bancarias WHERE activo = 1 ORDER BY id'),
            'cajas' => DB::pares('SELECT id, nombre FROM cajas WHERE activo = 1 ORDER BY id'),
            'vehiculos' => DB::pares('SELECT id, patente FROM vehiculos WHERE activo = 1'),
            'cajaUsuario' => CajaService::cajaDelUsuario(),
        ]);
    }

    public function guardarGasto(): void
    {
        FinanzasService::registrarGasto(Request::todos());
        $this->volver('Gasto registrado.');
    }

    public function anularGasto(string $id): void
    {
        FinanzasService::anularGasto((int) $id, $this->motivoAnulacion());
        $this->volver('Gasto anulado.');
    }
}
