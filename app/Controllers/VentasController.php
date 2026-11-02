<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Core\Response;
use App\Services\CajaService;
use App\Services\ClienteService;
use App\Services\Numeracion;
use App\Services\PedidoService;
use App\Services\VentaService;
use App\Support\Exportador;
use App\Support\PdfComprobante;

final class VentasController extends Controller
{
    private function filtros(): array
    {
        $w = [];
        $p = [];
        if ($t = Request::get('tipo')) {
            if ($t === 'facturas') {
                $w[] = "c.tipo IN ('FA','FB','FC')";
            } elseif ($t === 'notas') {
                $w[] = "c.tipo IN ('NCA','NCB','NCC','NDA','NDB','NDC')";
            } else {
                $w[] = 'c.tipo = ?';
                $p[] = (string) $t;
            }
        } else {
            $w[] = "c.tipo <> 'PR'";
        }
        if ($e = Request::get('estado')) {
            $w[] = 'c.estado = ?';
            $p[] = (string) $e;
        }
        if ($o = Request::get('origen')) {
            $w[] = 'c.origen = ?';
            $p[] = (string) $o;
        }
        if ($cl = (int) Request::get('cliente_id', 0)) {
            $w[] = 'c.cliente_id = ?';
            $p[] = $cl;
        }
        if ($q = trim((string) Request::get('q', ''))) {
            $w[] = '(c.cliente_nombre LIKE ? OR c.numero = ?)';
            array_push($p, "%$q%", (int) preg_replace('/\D/', '', $q));
        }
        if ($d = parse_fecha(Request::get('desde'))) {
            $w[] = 'c.fecha >= ?';
            $p[] = $d . ' 00:00:00';
        }
        if ($h = parse_fecha(Request::get('hasta'))) {
            $w[] = 'c.fecha <= ?';
            $p[] = $h . ' 23:59:59';
        }
        $sql = 'SELECT c.*, pv.numero AS pv_numero, u.nombre AS usuario FROM comprobantes c JOIN puntos_venta pv ON pv.id = c.punto_venta_id LEFT JOIN usuarios u ON u.id = c.usuario_id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        return [$sql, $p];
    }

    public function index(): void
    {
        [$sql, $p] = $this->filtros();
        $pag = new Paginador($sql, $p, ['fecha' => 'c.id', 'cliente' => 'c.cliente_nombre', 'total' => 'c.total'], 'fecha', 'desc');
        $totales = DB::fila("SELECT SUM(CASE WHEN t.tipo LIKE 'NC%' THEN -t.total ELSE t.total END) AS total, COUNT(*) AS n FROM ($sql) t WHERE t.estado = 'emitido'", $p);
        $this->vista('ventas/index', ['titulo' => ($_GET['tipo'] ?? '') === 'PR' ? 'Presupuestos' : 'Comprobantes de venta', 'pag' => $pag, 'totales' => $totales]);
    }

    public function exportar(): void
    {
        [$sql, $p] = $this->filtros();
        $filas = DB::filas($sql . ' ORDER BY c.fecha LIMIT 10000', $p);
        foreach ($filas as &$f) {
            $f['comprobante'] = VentaService::numero($f);
            $s = str_starts_with($f['tipo'], 'NC') ? -1 : 1;
            foreach (['neto_21', 'neto_105', 'neto_exento', 'iva_21', 'iva_105', 'impuestos_internos', 'total'] as $k) {
                $f[$k] = $s * (float) $f[$k];
            }
        }
        $col = ['fecha' => ['Fecha', 'fecha'], 'comprobante' => ['Comprobante', 'texto'], 'cliente_nombre' => ['Cliente', 'texto'], 'cliente_documento' => ['CUIT/DNI', 'texto'],
            'neto_21' => ['Neto 21%', 'money'], 'neto_105' => ['Neto 10,5%', 'money'], 'neto_exento' => ['Exento', 'money'], 'iva_21' => ['IVA 21%', 'money'], 'iva_105' => ['IVA 10,5%', 'money'],
            'impuestos_internos' => ['Internos', 'money'], 'total' => ['Total', 'money'], 'estado' => ['Estado', 'texto']];
        $emitidos = array_filter($filas, fn ($f) => $f['estado'] === 'emitido');
        $tot = ['comprobante' => 'TOTAL'];
        foreach (['neto_21', 'neto_105', 'neto_exento', 'iva_21', 'iva_105', 'impuestos_internos', 'total'] as $k) {
            $tot[$k] = array_sum(array_column($emitidos, $k));
        }
        Exportador::enviar((string) Request::get('formato', 'csv'), 'Comprobantes de venta', $col, $filas, $tot);
    }

    // ---------------- Emisión ----------------

    public function pos(): void
    {
        $cf = (int) Ajustes::get('cliente_consumidor_final', 1);
        $caja = CajaService::cajaDelUsuario();
        $this->vista('ventas/pos', [
            'titulo' => 'Mostrador', 'clienteCF' => ['id' => $cf, 'texto' => DB::valor('SELECT nombre_comercio FROM clientes WHERE id = ?', [$cf])],
            'lista' => (int) Ajustes::get('pos_lista_precio', 3), 'deposito' => (int) Ajustes::get('deposito_central', 1),
            'cajaAbierta' => $caja ? CajaService::sesionAbierta($caja) : null, 'cajaNombre' => $caja ? DB::valor('SELECT nombre FROM cajas WHERE id = ?', [$caja]) : '',
            'cuentas' => DB::pares('SELECT id, nombre FROM cuentas_bancarias WHERE activo = 1 ORDER BY id'),
        ]);
    }

    public function nueva(): void
    {
        $clase = (string) Request::get('clase', 'factura');
        $this->vista('ventas/nueva', [
            'titulo' => 'Nueva venta', 'clase' => in_array($clase, ['factura', 'remito', 'presupuesto', 'nd'], true) ? $clase : 'factura',
            'deposito' => (int) Ajustes::get('deposito_central', 1),
            'depositos' => DB::pares("SELECT id, nombre FROM depositos WHERE activo = 1 AND tipo = 'deposito' ORDER BY id"),
            'cuentas' => DB::pares('SELECT id, nombre FROM cuentas_bancarias WHERE activo = 1 ORDER BY id'),
        ]);
    }

    public function devolucion(): void
    {
        $factura = null;
        $items = [];
        if ($fid = (int) Request::get('factura_id', 0)) {
            $factura = DB::fila("SELECT * FROM comprobantes WHERE id = ? AND tipo IN ('FA','FB','FC','RM') AND estado = 'emitido'", [$fid]);
            if ($factura) {
                $devuelto = VentaService::devuelto($fid);
                foreach (DB::filas('SELECT ci.*, p.codigo, p.unidad_base FROM comprobante_items ci LEFT JOIN productos p ON p.id = ci.producto_id WHERE ci.comprobante_id = ?', [$fid]) as $it) {
                    if (!$it['producto_id']) {
                        continue;
                    }
                    $yaDevuelto = (float) ($devuelto[$it['producto_id']] ?? 0);
                    $it['max_base'] = max(0, (float) $it['cantidad_base'] - $yaDevuelto);
                    $it['precio_base'] = round((float) $it['precio_unitario'] / max(0.001, (float) $it['factor']) * (1 - (float) $it['descuento_porc'] / 100) * (1 - (float) $factura['descuento_porc'] / 100), 2);
                    $items[] = $it;
                }
            }
        }
        $this->vista('ventas/devolucion', [
            'titulo' => 'Devolución / nota de crédito o débito', 'factura' => $factura, 'items' => $items,
            'depositos' => DB::pares("SELECT id, nombre FROM depositos WHERE activo = 1 AND tipo = 'deposito' ORDER BY id"),
        ]);
    }

    /** Emisión por JSON (venta directa, POS, NC/ND, presupuesto). */
    public function emitir(): void
    {
        $d = Request::json();
        $clase = (string) ($d['clase'] ?? 'factura');
        if (!in_array($clase, ['factura', 'remito', 'presupuesto', 'nc', 'nd'], true)) {
            throw new ErrorNegocio('Tipo de comprobante inválido.');
        }
        if (!empty($d['forzar_credito']) && !Auth::puede('clientes.editar')) {
            $d['forzar_credito'] = false;
        }
        if ($clase === 'nc' && !empty($d['comprobante_asociado_id'])) {
            $this->validarDevolucion((int) $d['comprobante_asociado_id'], $d['items'] ?? []);
            $d['cliente_id'] = DB::valor('SELECT cliente_id FROM comprobantes WHERE id = ?', [(int) $d['comprobante_asociado_id']]);
        }
        if (!Auth::puede('productos.precios') && in_array($clase, ['factura', 'remito', 'presupuesto'], true)) {
            // Sin permiso de precios se usa siempre el precio de lista
            foreach ($d['items'] as &$it) {
                unset($it['precio_unitario']);
            }
            unset($it);
        }
        $id = VentaService::emitir($d);
        $c = DB::fila('SELECT * FROM comprobantes WHERE id = ?', [$id]);
        $nro = VentaService::numero($c);
        if (($d['origen'] ?? '') !== 'mostrador') {
            flash('exito', tipo_comprobante($c['tipo']) . ' ' . $nro . ' emitida por ' . money($c['total']) . '.');
        }
        $this->json([
            'ok' => true, 'id' => $id, 'numero' => $nro, 'total' => (float) $c['total'],
            'redirigir' => ($d['origen'] ?? '') === 'mostrador' ? null : url('/ventas/' . $id),
            'imprimir' => url('/ventas/' . $id . '/imprimir?formato=' . (($d['origen'] ?? '') === 'mostrador' ? 'ticket' : 'a4') . '&auto=1'),
        ]);
    }

    /** Una NC por devolución no puede superar lo vendido menos lo ya devuelto. */
    private function validarDevolucion(int $facturaId, array $items): void
    {
        $vendido = DB::pares('SELECT producto_id, SUM(cantidad_base) FROM comprobante_items WHERE comprobante_id = ? AND producto_id IS NOT NULL GROUP BY producto_id', [$facturaId]);
        $devuelto = VentaService::devuelto($facturaId);
        foreach ($items as $it) {
            if (empty($it['producto_id'])) {
                continue;
            }
            $pid = (int) $it['producto_id'];
            $base = parse_decimal($it['cantidad'] ?? 0) * (float) ($it['factor'] ?? 1);
            $max = (float) ($vendido[$pid] ?? 0) - (float) ($devuelto[$pid] ?? 0);
            if ($base > $max + 0.0005) {
                $desc = DB::valor('SELECT descripcion FROM productos WHERE id = ?', [$pid]);
                throw new ErrorNegocio("No se pueden devolver más unidades de \"$desc\" que las facturadas (máximo " . cant($max) . ').');
            }
        }
    }

    public function credito(string $clienteId): void
    {
        $this->json(['problemas' => ClienteService::problemasCredito((int) $clienteId, Request::decimal('monto')), 'resumen' => ClienteService::resumen((int) $clienteId)]);
    }

    // ---------------- Consulta, impresión y anulación ----------------

    private function cargar(int $id): array
    {
        $c = DB::fila('SELECT c.*, pv.numero AS pv_numero, u.nombre AS usuario, cl.codigo AS cliente_codigo, pr.nombre AS preventista
                       FROM comprobantes c JOIN puntos_venta pv ON pv.id = c.punto_venta_id LEFT JOIN usuarios u ON u.id = c.usuario_id
                       JOIN clientes cl ON cl.id = c.cliente_id LEFT JOIN preventistas pr ON pr.id = c.preventista_id WHERE c.id = ?', [$id]);
        if (!$c) {
            Response::error(404, 'El comprobante no existe.');
            exit;
        }
        $c['items'] = DB::filas('SELECT ci.*, p.codigo FROM comprobante_items ci LEFT JOIN productos p ON p.id = ci.producto_id WHERE ci.comprobante_id = ? ORDER BY ci.id', [$id]);
        $c['nro'] = VentaService::numero($c);
        return $c;
    }

    public function ver(string $id): void
    {
        $c = $this->cargar((int) $id);
        $recibos = DB::filas('SELECT r.*, (SELECT GROUP_CONCAT(DISTINCT forma_pago) FROM recibo_pagos WHERE recibo_id = r.id) AS formas FROM recibos r WHERE r.comprobante_id = ? ORDER BY r.id', [$id]);
        $asociados = DB::filas('SELECT * FROM comprobantes WHERE comprobante_asociado_id = ? ORDER BY id', [$id]);
        $origen = $c['comprobante_asociado_id'] ? DB::fila('SELECT * FROM comprobantes WHERE id = ?', [$c['comprobante_asociado_id']]) : null;
        $pendiente = (float) DB::valor("SELECT COALESCE(SUM(pendiente), 0) FROM cc_clientes WHERE documento_tipo = 'comprobante' AND documento_id = ? AND importe > 0", [$id]);
        $this->vista('ventas/ver', ['titulo' => $c['nro'], 'c' => $c, 'recibos' => $recibos, 'asociados' => $asociados, 'origen' => $origen, 'pendiente' => $pendiente]);
    }

    public function imprimir(string $id): void
    {
        $c = $this->cargar((int) $id);
        $formato = Request::get('formato') === 'ticket' ? 'ticket' : 'a4';
        $pagos = DB::filas("SELECT rp.forma_pago, SUM(rp.importe) AS importe FROM recibos r JOIN recibo_pagos rp ON rp.recibo_id = r.id WHERE r.comprobante_id = ? AND r.estado = 'emitido' GROUP BY rp.forma_pago", [$id]);
        $this->vista('impresion/' . ($formato === 'ticket' ? 'ticket' : 'comprobante'), [
            'titulo' => $c['nro'], 'c' => $c, 'pagos' => $pagos, 'claseBody' => $formato === 'ticket' ? 'ticket' : 'hoja-a4',
            'autoImprimir' => (bool) Request::get('auto'), 'urlPdf' => url('/ventas/' . $id . '/pdf'),
            'formatos' => $formato === 'ticket' ? ['Hoja A4' => url('/ventas/' . $id . '/imprimir')] : ['Ticket 80 mm' => url('/ventas/' . $id . '/imprimir?formato=ticket')],
        ], 'layouts/impresion');
    }

    public function pdf(string $id): void
    {
        $c = $this->cargar((int) $id);
        $pdf = new PdfComprobante($c);
        Response::descarga($pdf->generar(), str_replace([' ', '/'], '_', $c['nro']) . '.pdf', 'application/pdf');
    }

    public function anular(string $id): void
    {
        $motivo = $this->motivoAnulacion();
        VentaService::anular((int) $id, $motivo, Request::input('anular_recibos', '1') === '1');
        $this->volver('Comprobante anulado: se revirtió el stock y la cuenta corriente.');
    }

    /** Convierte un presupuesto en pedido (borrador) para revisarlo y confirmarlo. */
    public function convertirPresupuesto(string $id): void
    {
        $c = $this->cargar((int) $id);
        if ($c['tipo'] !== 'PR' || $c['estado'] !== 'emitido') {
            throw new ErrorNegocio('Sólo se pueden convertir presupuestos vigentes.');
        }
        $items = array_map(fn ($it) => [
            'producto_id' => $it['producto_id'], 'factor' => $it['factor'], 'presentacion_nombre' => $it['presentacion_nombre'],
            'cantidad' => $it['cantidad'], 'precio_unitario' => $it['precio_unitario'], 'descuento_porc' => $it['descuento_porc'],
        ], array_filter($c['items'], fn ($it) => $it['producto_id']));
        $pid = PedidoService::guardar(['cliente_id' => $c['cliente_id'], 'items' => $items, 'descuento_porc' => $c['descuento_porc'], 'observaciones' => 'Desde presupuesto ' . $c['nro']]);
        $this->redirigir('/pedidos/' . $pid . '/editar', 'Se creó un pedido en borrador a partir del presupuesto. Revisalo y confirmalo.');
    }
}
