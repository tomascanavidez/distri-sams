<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\CajaService;
use App\Services\CompraService;
use App\Services\CuentaCorriente;

final class ComprasController extends Controller
{
    private function proveedores(): array
    {
        return DB::pares('SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social');
    }

    private function depositos(): array
    {
        return DB::pares("SELECT id, nombre FROM depositos WHERE activo = 1 AND tipo = 'deposito' ORDER BY id");
    }

    // ---------------- Órdenes de compra ----------------

    public function index(): void
    {
        $w = [];
        $p = [];
        if ($pr = (int) Request::get('proveedor_id', 0)) {
            $w[] = 'o.proveedor_id = ?';
            $p[] = $pr;
        }
        if ($e = Request::get('estado')) {
            $w[] = $e === 'pendientes' ? "o.estado IN ('borrador','enviada','recibida_parcial')" : 'o.estado = ?';
            if ($e !== 'pendientes') {
                $p[] = (string) $e;
            }
        }
        $sql = 'SELECT o.*, pr.razon_social FROM ordenes_compra o JOIN proveedores pr ON pr.id = o.proveedor_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        $pag = new Paginador($sql, $p, ['numero' => 'o.id', 'total' => 'o.total'], 'numero', 'desc');
        $this->vista('compras/index', ['titulo' => 'Órdenes de compra', 'pag' => $pag, 'proveedores' => $this->proveedores()]);
    }

    public function nueva(): void
    {
        $prov = (int) Request::get('proveedor_id', 0);
        $items = [];
        if (Request::get('sugerencia') && $prov) {
            $sel = array_map('intval', (array) Request::get('productos', []));
            foreach (CompraService::sugerencia($prov, (int) Ajustes::get('sugerencia_dias_venta', 30), (int) Ajustes::get('sugerencia_dias_cobertura', 15)) as $s) {
                if ($sel && !in_array((int) $s['id'], $sel, true)) {
                    continue;
                }
                $items[] = $this->itemEditor((int) $s['id'], (int) $s['presentacion_id'], (float) $s['sugerido'], (float) $s['costo_presentacion']);
            }
        }
        $this->formularioOrden(null, $prov, $items);
    }

    public function editar(string $id): void
    {
        $oc = $this->buscarOFallar('ordenes_compra', (int) $id);
        if (!in_array($oc['estado'], ['borrador', 'enviada'], true)) {
            $this->redirigir('/compras/' . $id);
        }
        $items = [];
        foreach (DB::filas('SELECT * FROM orden_compra_items WHERE orden_id = ?', [$id]) as $it) {
            $items[] = $this->itemEditor((int) $it['producto_id'], (int) $it['presentacion_id'], (float) $it['cantidad'], (float) $it['costo_unitario'], (float) $it['factor'], $it['presentacion_nombre']);
        }
        $this->formularioOrden($oc, (int) $oc['proveedor_id'], $items);
    }

    private function formularioOrden(?array $oc, int $prov, array $items): void
    {
        $this->vista('compras/form', [
            'titulo' => $oc ? 'Orden de compra N° ' . $oc['numero'] : 'Nueva orden de compra', 'oc' => $oc, 'items' => $items,
            'cab' => ['id' => $oc['id'] ?? null, 'proveedor_id' => $prov ?: null, 'fecha_entrega' => $oc['fecha_entrega'] ?? '', 'observaciones' => $oc['observaciones'] ?? ''],
            'proveedores' => $this->proveedores(), 'depositos' => $this->depositos(), 'deposito' => (int) ($oc['deposito_id'] ?? Ajustes::get('deposito_central', 1)),
        ]);
    }

    /** Ítem en el formato del editor JS (modo compra). */
    private function itemEditor(int $productoId, int $presId, float $cantidad, float $costo, ?float $factor = null, ?string $presNombre = null, array $extra = []): array
    {
        $p = DB::fila('SELECT id, codigo, descripcion, unidad_base, costo, maneja_lotes FROM productos WHERE id = ?', [$productoId]);
        $pres = DB::filas('SELECT id, nombre, factor + 0 AS factor, es_venta AS venta, es_compra AS compra, predeterminada_compra AS pc FROM producto_presentaciones WHERE producto_id = ? AND activo = 1 ORDER BY factor', [$productoId]);
        $pres = array_map(fn ($x) => ['id' => (int) $x['id'], 'nombre' => $x['nombre'], 'factor' => (float) $x['factor'], 'venta' => (int) $x['venta'], 'compra' => (int) $x['compra'], 'pc' => (int) $x['pc']], $pres);
        $sel = null;
        foreach ($pres as $x) {
            if ($x['id'] === $presId || ($factor !== null && abs($x['factor'] - $factor) < 0.0001 && !$sel)) {
                $sel = $x;
            }
        }
        $sel ??= $pres[0] ?? ['id' => 0, 'nombre' => $p['unidad_base'], 'factor' => 1.0];
        return $extra + [
            'producto_id' => (int) $p['id'], 'descripcion' => $p['descripcion'], 'codigo' => $p['codigo'], 'unidad' => $p['unidad_base'],
            'presentaciones' => $pres, 'presentacion_id' => $sel['id'], 'factor' => $factor ?? $sel['factor'], 'presentacion_nombre' => $presNombre ?? $sel['nombre'],
            'cantidad' => $cantidad, 'precio' => round($costo, 2), 'costo_base' => (float) $p['costo'], 'lotes' => (int) $p['maneja_lotes'],
        ];
    }

    public function guardar(): void
    {
        $d = Request::json();
        $id = CompraService::guardarOrden($d, !empty($d['id']) ? (int) $d['id'] : null);
        flash('exito', 'Orden de compra guardada.');
        $this->json(['ok' => true, 'id' => $id, 'redirigir' => url('/compras/' . $id)]);
    }

    public function ver(string $id): void
    {
        $oc = DB::fila('SELECT o.*, pr.razon_social, pr.cuit, pr.telefono, pr.email, pr.contacto, d.nombre AS deposito, u.nombre AS usuario FROM ordenes_compra o
                        JOIN proveedores pr ON pr.id = o.proveedor_id JOIN depositos d ON d.id = o.deposito_id LEFT JOIN usuarios u ON u.id = o.usuario_id WHERE o.id = ?', [(int) $id]);
        if (!$oc) {
            $this->buscarOFallar('ordenes_compra', (int) $id);
        }
        $items = DB::filas('SELECT oi.*, p.codigo, p.descripcion, p.unidad_base FROM orden_compra_items oi JOIN productos p ON p.id = oi.producto_id WHERE oi.orden_id = ?', [$id]);
        $recepciones = DB::filas('SELECT * FROM recepciones WHERE orden_id = ? ORDER BY id', [$id]);
        $this->vista('compras/ver', ['titulo' => 'Orden de compra N° ' . $oc['numero'], 'oc' => $oc, 'items' => $items, 'recepciones' => $recepciones]);
    }

    public function imprimir(string $id): void
    {
        $oc = DB::fila('SELECT o.*, pr.razon_social, pr.cuit, pr.direccion, pr.telefono, d.nombre AS deposito FROM ordenes_compra o JOIN proveedores pr ON pr.id = o.proveedor_id JOIN depositos d ON d.id = o.deposito_id WHERE o.id = ?', [(int) $id]);
        if (!$oc) {
            $this->buscarOFallar('ordenes_compra', (int) $id);
        }
        $items = DB::filas('SELECT oi.*, p.codigo, p.descripcion FROM orden_compra_items oi JOIN productos p ON p.id = oi.producto_id WHERE oi.orden_id = ?', [$id]);
        $this->vista('impresion/orden_compra', ['titulo' => 'OC ' . $oc['numero'], 'oc' => $oc, 'items' => $items], 'layouts/impresion');
    }

    public function cambiarEstado(string $id): void
    {
        $estado = (string) Request::texto('estado');
        $motivo = $estado === 'anulada' ? $this->motivoAnulacion() : null;
        CompraService::cambiarEstadoOrden((int) $id, $estado, $motivo);
        $this->volver($estado === 'enviada' ? 'Orden marcada como enviada.' : 'Orden actualizada.');
    }

    public function sugerencia(): void
    {
        $prov = (int) Request::get('proveedor_id', 0);
        $diasVenta = (int) Request::get('dias_venta', Ajustes::get('sugerencia_dias_venta', 30));
        $diasCob = (int) Request::get('dias_cobertura', Ajustes::get('sugerencia_dias_cobertura', 15));
        $filas = CompraService::sugerencia($prov ?: null, max(1, $diasVenta), max(1, $diasCob));
        $porProveedor = [];
        foreach ($filas as $f) {
            $porProveedor[(int) $f['proveedor_id']][] = $f;
        }
        $this->vista('compras/sugerencia', [
            'titulo' => 'Sugerencia de compra', 'porProveedor' => $porProveedor, 'proveedores' => $this->proveedores(),
            'diasVenta' => $diasVenta, 'diasCob' => $diasCob, 'prov' => $prov,
        ]);
    }

    // ---------------- Recepciones ----------------

    public function recepciones(): void
    {
        $pag = new Paginador('SELECT r.*, pr.razon_social, o.numero AS oc_numero, u.nombre AS usuario FROM recepciones r JOIN proveedores pr ON pr.id = r.proveedor_id
                              LEFT JOIN ordenes_compra o ON o.id = r.orden_id LEFT JOIN usuarios u ON u.id = r.usuario_id', [], ['fecha' => 'r.id'], 'fecha', 'desc');
        $this->vista('compras/recepciones', ['titulo' => 'Recepciones de mercadería', 'pag' => $pag]);
    }

    public function nuevaRecepcion(): void
    {
        $oc = null;
        $items = [];
        if ($oid = (int) Request::get('orden_id', 0)) {
            $oc = DB::fila('SELECT o.*, pr.razon_social FROM ordenes_compra o JOIN proveedores pr ON pr.id = o.proveedor_id WHERE o.id = ?', [$oid]);
            if ($oc) {
                foreach (DB::filas('SELECT * FROM orden_compra_items WHERE orden_id = ?', [$oid]) as $it) {
                    $pendBase = max(0, (float) $it['cantidad_base'] - (float) $it['cantidad_recibida_base']);
                    if ($pendBase <= 0) {
                        continue;
                    }
                    $items[] = $this->itemEditor((int) $it['producto_id'], (int) $it['presentacion_id'], round($pendBase / (float) $it['factor'], 3), (float) $it['costo_unitario'], (float) $it['factor'], $it['presentacion_nombre'], ['orden_item_id' => (int) $it['id']]);
                }
            }
        }
        $this->vista('compras/recepcion_form', [
            'titulo' => $oc ? 'Recepción de la OC N° ' . $oc['numero'] : 'Recepción de mercadería', 'oc' => $oc, 'items' => $items,
            'proveedores' => $this->proveedores(), 'depositos' => $this->depositos(),
            'deposito' => (int) ($oc['deposito_id'] ?? Ajustes::get('deposito_central', 1)),
            'ordenes' => DB::pares("SELECT o.id, CONCAT('OC N° ', o.numero, ' - ', pr.razon_social) FROM ordenes_compra o JOIN proveedores pr ON pr.id = o.proveedor_id WHERE o.estado IN ('borrador','enviada','recibida_parcial') ORDER BY o.id DESC"),
        ]);
    }

    public function guardarRecepcion(): void
    {
        $d = Request::json();
        $id = CompraService::recibir($d);
        flash('exito', 'Mercadería recibida: se actualizó el stock' . (!isset($d['actualizar_costos']) || $d['actualizar_costos'] ? ' y los costos.' : '.'));
        $this->json(['ok' => true, 'id' => $id, 'redirigir' => url('/compras/recepciones/' . $id)]);
    }

    public function verRecepcion(string $id): void
    {
        $r = DB::fila('SELECT r.*, pr.razon_social, o.numero AS oc_numero, d.nombre AS deposito, u.nombre AS usuario, f.tipo AS f_tipo, f.punto_venta AS f_pv, f.numero AS f_numero
                       FROM recepciones r JOIN proveedores pr ON pr.id = r.proveedor_id LEFT JOIN ordenes_compra o ON o.id = r.orden_id JOIN depositos d ON d.id = r.deposito_id
                       LEFT JOIN usuarios u ON u.id = r.usuario_id LEFT JOIN facturas_proveedor f ON f.id = r.factura_proveedor_id WHERE r.id = ?', [(int) $id]);
        if (!$r) {
            $this->buscarOFallar('recepciones', (int) $id);
        }
        $items = DB::filas('SELECT ri.*, p.codigo, p.descripcion, p.unidad_base FROM recepcion_items ri JOIN productos p ON p.id = ri.producto_id WHERE ri.recepcion_id = ?', [$id]);
        $this->vista('compras/recepcion_ver', ['titulo' => 'Recepción N° ' . $r['numero'], 'r' => $r, 'items' => $items]);
    }

    public function anularRecepcion(string $id): void
    {
        CompraService::anularRecepcion((int) $id, $this->motivoAnulacion());
        $this->volver('Recepción anulada: se descontó el stock ingresado.');
    }

    // ---------------- Facturas de proveedor ----------------

    public function facturas(): void
    {
        $w = [];
        $p = [];
        if ($pr = (int) Request::get('proveedor_id', 0)) {
            $w[] = 'f.proveedor_id = ?';
            $p[] = $pr;
        }
        $pag = new Paginador('SELECT f.*, pr.razon_social, (SELECT COALESCE(SUM(pendiente), 0) FROM cc_proveedores cc WHERE cc.documento_tipo = \'factura_proveedor\' AND cc.documento_id = f.id AND cc.importe > 0) AS pendiente
                              FROM facturas_proveedor f JOIN proveedores pr ON pr.id = f.proveedor_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p, ['fecha' => 'f.fecha', 'total' => 'f.total'], 'fecha', 'desc');
        $this->vista('compras/facturas', ['titulo' => 'Facturas de proveedores', 'pag' => $pag, 'proveedores' => $this->proveedores()]);
    }

    public function nuevaFactura(): void
    {
        $prov = (int) Request::get('proveedor_id', 0);
        $this->vista('compras/factura_form', [
            'titulo' => 'Cargar factura de proveedor', 'prov' => $prov, 'proveedores' => $this->proveedores(),
            'recepciones' => DB::pares("SELECT r.id, CONCAT('Recepción N° ', r.numero, ' - ', pr.razon_social, ' - ', DATE_FORMAT(r.fecha, '%d/%m/%Y'), ' - $', FORMAT(r.total, 2, 'es_AR')) FROM recepciones r JOIN proveedores pr ON pr.id = r.proveedor_id WHERE r.estado = 'aplicada' AND r.factura_proveedor_id IS NULL ORDER BY r.id DESC LIMIT 100"),
        ]);
    }

    public function guardarFactura(): void
    {
        CompraService::registrarFactura(Request::todos());
        $this->redirigir('/compras/facturas', 'Factura registrada en la cuenta corriente del proveedor.');
    }

    public function anularFactura(string $id): void
    {
        CompraService::anularFactura((int) $id, $this->motivoAnulacion());
        $this->volver('Factura anulada.');
    }

    // ---------------- Pagos ----------------

    public function pagos(): void
    {
        $pag = new Paginador('SELECT pp.*, pr.razon_social, (SELECT GROUP_CONCAT(DISTINCT forma_pago) FROM pagos_proveedor_detalle WHERE pago_id = pp.id) AS formas
                              FROM pagos_proveedor pp JOIN proveedores pr ON pr.id = pp.proveedor_id', [], ['fecha' => 'pp.id'], 'fecha', 'desc');
        $this->vista('compras/pagos', ['titulo' => 'Pagos a proveedores', 'pag' => $pag]);
    }

    public function nuevoPago(): void
    {
        $prov = (int) Request::get('proveedor_id', 0);
        $pend = [];
        if ($prov) {
            foreach (CuentaCorriente::proveedores()->pendientes($prov) as $f) {
                $pend[] = ['id' => (int) $f['id'], 'descripcion' => $f['descripcion'], 'vencimiento' => fecha($f['vencimiento']), 'pendiente' => (float) $f['pendiente'], 'dias' => (int) $f['dias_vencido'], 'importe' => ''];
            }
        }
        $this->vista('compras/pago_form', [
            'titulo' => 'Pago a proveedor', 'prov' => $prov, 'proveedores' => $this->proveedores(), 'pendientes' => $pend,
            'saldo' => $prov ? CuentaCorriente::proveedores()->saldo($prov) : 0,
            'cuentas' => DB::pares('SELECT id, nombre FROM cuentas_bancarias WHERE activo = 1 ORDER BY id'),
            'cheques' => DB::filas("SELECT id, numero, banco, importe, fecha_pago FROM cheques WHERE estado = 'cartera' ORDER BY fecha_pago"),
            'cajas' => DB::pares('SELECT id, nombre FROM cajas WHERE activo = 1'), 'cajaUsuario' => CajaService::cajaDelUsuario(),
        ]);
    }

    public function guardarPago(): void
    {
        $d = Request::json();
        $imp = [];
        foreach ($d['imputaciones'] ?? [] as $i) {
            if (parse_decimal($i['importe'] ?? 0) > 0) {
                $imp[(int) $i['id']] = parse_decimal($i['importe']);
            }
        }
        $d['imputaciones'] = $imp;
        $id = CompraService::registrarPago($d);
        flash('exito', 'Pago registrado.');
        $this->json(['ok' => true, 'id' => $id, 'redirigir' => url('/compras/pagos/' . $id)]);
    }

    public function verPago(string $id): void
    {
        $pago = DB::fila('SELECT pp.*, pr.razon_social, pr.cuit, u.nombre AS usuario FROM pagos_proveedor pp JOIN proveedores pr ON pr.id = pp.proveedor_id LEFT JOIN usuarios u ON u.id = pp.usuario_id WHERE pp.id = ?', [(int) $id]);
        if (!$pago) {
            $this->buscarOFallar('pagos_proveedor', (int) $id);
        }
        $detalle = DB::filas('SELECT d.*, cb.nombre AS cuenta, ch.numero AS cheque_numero, ch.banco AS cheque_banco FROM pagos_proveedor_detalle d LEFT JOIN cuentas_bancarias cb ON cb.id = d.cuenta_bancaria_id LEFT JOIN cheques ch ON ch.id = d.cheque_id WHERE d.pago_id = ?', [$id]);
        $cc = DB::valor("SELECT id FROM cc_proveedores WHERE documento_tipo = 'pago_proveedor' AND documento_id = ? AND tipo = 'pago'", [$id]);
        $imput = $cc ? DB::filas('SELECT i.importe, d.descripcion FROM cc_proveedores_imputaciones i JOIN cc_proveedores d ON d.id = i.debito_id WHERE i.credito_id = ?', [$cc]) : [];
        $this->vista('compras/pago_ver', ['titulo' => 'Pago N° ' . $pago['numero'], 'pago' => $pago, 'detalle' => $detalle, 'imput' => $imput]);
    }

    public function anularPago(string $id): void
    {
        CompraService::anularPago((int) $id, $this->motivoAnulacion());
        $this->volver('Pago anulado.');
    }

    // ---------------- Devoluciones ----------------

    public function devoluciones(): void
    {
        $pag = new Paginador('SELECT dv.*, pr.razon_social, d.nombre AS deposito, f.numero AS nc_numero FROM devoluciones_proveedor dv JOIN proveedores pr ON pr.id = dv.proveedor_id
                              JOIN depositos d ON d.id = dv.deposito_id LEFT JOIN facturas_proveedor f ON f.id = dv.nota_credito_id', [], ['fecha' => 'dv.id'], 'fecha', 'desc');
        $this->vista('compras/devoluciones', ['titulo' => 'Devoluciones a proveedores', 'pag' => $pag]);
    }

    public function nuevaDevolucion(): void
    {
        $this->vista('compras/devolucion_form', ['titulo' => 'Devolución a proveedor', 'proveedores' => $this->proveedores(), 'depositos' => $this->depositos(), 'deposito' => (int) Ajustes::get('deposito_central', 1)]);
    }

    public function guardarDevolucion(): void
    {
        $d = Request::json();
        if (empty($d['items'])) {
            throw new ErrorNegocio('Agregá productos a devolver.');
        }
        $id = CompraService::devolucion($d);
        flash('exito', 'Devolución registrada.');
        $this->json(['ok' => true, 'id' => $id, 'redirigir' => url('/compras/devoluciones')]);
    }

    public function anularDevolucion(string $id): void
    {
        CompraService::anularDevolucion((int) $id, $this->motivoAnulacion());
        $this->volver('Devolución anulada: la mercadería volvió al stock.');
    }
}
