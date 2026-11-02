<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Compras: órdenes de compra, recepción de mercadería (actualiza stock y costos),
 * facturas de proveedor, pagos y devoluciones.
 *
 * Costos: se mantienen dos valores por producto, el ÚLTIMO costo y el PROMEDIO PONDERADO.
 * El parámetro "costo_metodo" define cuál se usa para precios y valorización (por defecto: último).
 */
final class CompraService
{
    public const ESTADOS_OC = [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'recibida_parcial' => 'Recibida parcial',
        'recibida' => 'Recibida',
        'anulada' => 'Anulada',
    ];

    /** $d: proveedor_id, deposito_id, fecha, fecha_entrega, observaciones, items [[producto_id, presentacion_id, cantidad, costo_unitario]] */
    public static function guardarOrden(array $d, ?int $id = null): int
    {
        return DB::transaccion(function () use ($d, $id) {
            if (empty($d['proveedor_id'])) {
                throw new ErrorNegocio('Elegí el proveedor.');
            }
            if ($id) {
                $oc = DB::fila('SELECT * FROM ordenes_compra WHERE id = ? FOR UPDATE', [$id]);
                if (!$oc || !in_array($oc['estado'], ['borrador', 'enviada'], true)) {
                    throw new ErrorNegocio('Sólo se pueden modificar órdenes en borrador o enviadas.');
                }
            }
            $items = [];
            $total = 0.0;
            foreach ($d['items'] ?? [] as $i) {
                $cant = round(parse_decimal($i['cantidad'] ?? 0), 3);
                if ($cant <= 0 || empty($i['producto_id'])) {
                    continue;
                }
                [$nombre, $factor] = VentaService::presentacion((int) $i['producto_id'], (int) ($i['presentacion_id'] ?? 0), $i['factor'] ?? null, $i['presentacion_nombre'] ?? null);
                $costo = round(parse_decimal($i['costo_unitario'] ?? 0), 4);
                $items[] = [
                    'producto_id' => (int) $i['producto_id'], 'presentacion_id' => ((int) ($i['presentacion_id'] ?? 0)) ?: null,
                    'presentacion_nombre' => $nombre, 'cantidad' => $cant, 'factor' => $factor,
                    'cantidad_base' => round($cant * $factor, 3), 'costo_unitario' => $costo,
                ];
                $total += $cant * $costo;
            }
            if (!$items) {
                throw new ErrorNegocio('Agregá al menos un producto a la orden.');
            }
            $datos = [
                'fecha' => parse_fecha($d['fecha'] ?? '') ?? hoy(), 'proveedor_id' => (int) $d['proveedor_id'],
                'deposito_id' => (int) ($d['deposito_id'] ?? 0) ?: (int) Ajustes::get('deposito_central', 1),
                'fecha_entrega' => parse_fecha($d['fecha_entrega'] ?? ''), 'observaciones' => $d['observaciones'] ?? null,
                'total' => round($total, 2),
            ];
            if ($id) {
                DB::actualizar('ordenes_compra', $datos, 'id = :id', ['id' => $id]);
                DB::query('DELETE FROM orden_compra_items WHERE orden_id = ?', [$id]);
            } else {
                $id = DB::insertar('ordenes_compra', $datos + [
                    'numero' => Numeracion::siguiente('OC'), 'estado' => !empty($d['enviar']) ? 'enviada' : 'borrador',
                    'usuario_id' => Auth::id(), 'creado_en' => ahora(),
                ]);
            }
            foreach ($items as $it) {
                DB::insertar('orden_compra_items', $it + ['orden_id' => $id]);
            }
            Auditoria::registrar('compras', 'guardar', 'orden_compra', $id, null, ['total' => round($total, 2)], 'Orden de compra guardada');
            return $id;
        });
    }

    public static function cambiarEstadoOrden(int $id, string $estado, ?string $motivo = null): void
    {
        DB::transaccion(function () use ($id, $estado, $motivo) {
            $oc = DB::fila('SELECT * FROM ordenes_compra WHERE id = ? FOR UPDATE', [$id]);
            if (!$oc) {
                throw new ErrorNegocio('La orden no existe.');
            }
            if ($estado === 'enviada' && $oc['estado'] !== 'borrador') {
                throw new ErrorNegocio('Sólo se envían órdenes en borrador.');
            }
            if ($estado === 'anulada') {
                if (!in_array($oc['estado'], ['borrador', 'enviada', 'recibida_parcial'], true)) {
                    throw new ErrorNegocio('La orden no se puede anular en su estado actual.');
                }
                if ($oc['estado'] === 'recibida_parcial') {
                    // Se cierra lo pendiente; lo recibido queda firme
                    $estado = 'recibida';
                    $motivo = 'Cerrada con faltantes: ' . $motivo;
                }
            }
            DB::actualizar('ordenes_compra', ['estado' => $estado, 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $id]);
            Auditoria::registrar('compras', 'estado', 'orden_compra', $id, ['estado' => $oc['estado']], ['estado' => $estado, 'motivo' => $motivo]);
        });
    }

    /**
     * Sugerencia de compra: para cada producto (del proveedor si se indica) calcula la venta diaria
     * promedio de los últimos N días y sugiere cubrir M días o llegar al stock mínimo/máximo,
     * descontando lo pendiente de recibir. Se redondea a bultos de compra.
     */
    public static function sugerencia(?int $proveedorId, int $diasVenta = 30, int $diasCobertura = 15): array
    {
        $desde = date('Y-m-d', strtotime("-$diasVenta days"));
        $params = [$desde];
        $filtro = '';
        if ($proveedorId) {
            $filtro = ' AND p.proveedor_id = ?';
            $params[] = $proveedorId;
        }
        $filas = DB::filas(
            "SELECT p.id, p.codigo, p.descripcion, p.stock_minimo, p.stock_maximo, p.costo, p.proveedor_id,
                    COALESCE((SELECT SUM(s.cantidad) FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = p.id AND d.tipo = 'deposito'), 0) AS stock,
                    COALESCE((SELECT SUM(-m.cantidad) FROM stock_movimientos m WHERE m.producto_id = p.id AND m.tipo = 'venta' AND m.fecha >= ?), 0) AS vendido,
                    COALESCE((SELECT SUM(oi.cantidad_base - oi.cantidad_recibida_base) FROM orden_compra_items oi JOIN ordenes_compra o ON o.id = oi.orden_id
                              WHERE oi.producto_id = p.id AND o.estado IN ('borrador','enviada','recibida_parcial')), 0) AS pendiente
             FROM productos p WHERE p.activo = 1 $filtro ORDER BY p.descripcion",
            $params
        );
        $out = [];
        foreach ($filas as $f) {
            $diario = (float) $f['vendido'] / max(1, $diasVenta);
            $objetivo = max((float) $f['stock_minimo'], $diario * $diasCobertura);
            if ((float) $f['stock_maximo'] > 0) {
                $objetivo = min(max($objetivo, (float) $f['stock_minimo']), (float) $f['stock_maximo']);
            }
            $necesario = $objetivo - (float) $f['stock'] - (float) $f['pendiente'];
            if ($necesario <= 0.0005 || ((float) $f['stock'] > (float) $f['stock_minimo'] && $diario <= 0)) {
                continue;
            }
            $pres = DB::fila('SELECT id, nombre, factor FROM producto_presentaciones WHERE producto_id = ? AND es_compra = 1 AND activo = 1 ORDER BY predeterminada_compra DESC, factor DESC LIMIT 1', [$f['id']]);
            $factor = $pres ? (float) $pres['factor'] : 1.0;
            $bultos = (int) ceil($necesario / $factor);
            $f['diario'] = round($diario, 2);
            $f['necesario'] = round($necesario, 3);
            $f['presentacion_id'] = $pres['id'] ?? null;
            $f['presentacion_nombre'] = $pres['nombre'] ?? 'Unidad';
            $f['factor'] = $factor;
            $f['sugerido'] = $bultos;
            $f['costo_presentacion'] = round((float) $f['costo'] * $factor, 2);
            $out[] = $f;
        }
        return $out;
    }

    /**
     * Recepción de mercadería. $d: orden_id?, proveedor_id, deposito_id, remito_proveedor, observaciones,
     * items [[orden_item_id?, producto_id, presentacion_id?, factor?, cantidad, costo_unitario (neto por presentación), lote, vencimiento]],
     * actualizar_costos (bool)
     */
    public static function recibir(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            $oc = null;
            if (!empty($d['orden_id'])) {
                $oc = DB::fila('SELECT * FROM ordenes_compra WHERE id = ? FOR UPDATE', [(int) $d['orden_id']]);
                if (!$oc || !in_array($oc['estado'], ['borrador', 'enviada', 'recibida_parcial'], true)) {
                    throw new ErrorNegocio('La orden de compra no admite recepciones.');
                }
                $d['proveedor_id'] = $oc['proveedor_id'];
                $d['deposito_id'] = $d['deposito_id'] ?? $oc['deposito_id'];
            }
            if (empty($d['proveedor_id'])) {
                throw new ErrorNegocio('Elegí el proveedor.');
            }
            $deposito = (int) ($d['deposito_id'] ?? 0) ?: (int) Ajustes::get('deposito_central', 1);
            $id = DB::insertar('recepciones', [
                'numero' => Numeracion::siguiente('REC'), 'fecha' => ahora(), 'orden_id' => $oc['id'] ?? null,
                'proveedor_id' => (int) $d['proveedor_id'], 'deposito_id' => $deposito,
                'remito_proveedor' => $d['remito_proveedor'] ?? null, 'estado' => 'aplicada',
                'observaciones' => $d['observaciones'] ?? null, 'usuario_id' => Auth::id(),
            ]);
            $actualizarCostos = !isset($d['actualizar_costos']) || !empty($d['actualizar_costos']);
            $recalcular = Ajustes::get('actualizar_precios_al_recibir', '1') === '1';
            $total = 0.0;
            $n = 0;
            foreach ($d['items'] ?? [] as $i) {
                $cant = round(parse_decimal($i['cantidad'] ?? 0), 3);
                if ($cant <= 0 || empty($i['producto_id'])) {
                    continue;
                }
                $prodId = (int) $i['producto_id'];
                $p = DB::fila('SELECT * FROM productos WHERE id = ? FOR UPDATE', [$prodId]);
                if (!$p) {
                    throw new ErrorNegocio("Producto #$prodId inexistente.");
                }
                [$nombre, $factor] = VentaService::presentacion($prodId, (int) ($i['presentacion_id'] ?? 0), $i['factor'] ?? null, $i['presentacion_nombre'] ?? null);
                $base = round($cant * $factor, 3);
                $costoPres = round(parse_decimal($i['costo_unitario'] ?? 0), 4);
                $costoBase = $factor > 0 ? round($costoPres / $factor, 4) : $costoPres;
                $ocItem = null;
                if ($oc && !empty($i['orden_item_id'])) {
                    $ocItem = DB::fila('SELECT * FROM orden_compra_items WHERE id = ? AND orden_id = ?', [(int) $i['orden_item_id'], $oc['id']]);
                }
                DB::insertar('recepcion_items', [
                    'recepcion_id' => $id, 'orden_item_id' => $ocItem['id'] ?? null, 'producto_id' => $prodId,
                    'presentacion_nombre' => $nombre, 'cantidad' => $cant, 'factor' => $factor, 'cantidad_base' => $base,
                    'costo_unitario_base' => $costoBase, 'lote' => $i['lote'] ?? null, 'vencimiento' => parse_fecha($i['vencimiento'] ?? ''),
                ]);
                // Costo promedio ponderado (con el stock total en depósitos antes del ingreso)
                $stockAntes = StockService::totalDepositos($prodId);
                $promAnterior = (float) $p['costo_promedio'] > 0 ? (float) $p['costo_promedio'] : (float) $p['costo'];
                $nuevoPromedio = $stockAntes > 0 ? ($stockAntes * $promAnterior + $base * $costoBase) / ($stockAntes + $base) : $costoBase;

                StockService::mover($prodId, $deposito, $base, 'compra', 'recepcion', $id, [
                    'costo' => $costoBase, 'lote' => $i['lote'] ?? null, 'vencimiento' => parse_fecha($i['vencimiento'] ?? ''),
                    'motivo' => 'Recepción N° ' . DB::valor('SELECT numero FROM recepciones WHERE id = ?', [$id]),
                ]);
                if ($actualizarCostos && $costoBase > 0) {
                    DB::actualizar('productos', ['costo_promedio' => round($nuevoPromedio, 4)], 'id = :id', ['id' => $prodId]);
                    PrecioService::actualizarCosto($prodId, $costoBase, 'Recepción de compra', $recalcular);
                    if ($recalcular && Ajustes::get('costo_metodo') === 'promedio') {
                        PrecioService::recalcularProducto($prodId, 'Recepción de compra (costo promedio)');
                    }
                }
                if ($ocItem) {
                    DB::query('UPDATE orden_compra_items SET cantidad_recibida_base = cantidad_recibida_base + ? WHERE id = ?', [$base, $ocItem['id']]);
                }
                $total += $base * $costoBase;
                $n++;
            }
            if ($n === 0) {
                throw new ErrorNegocio('Indicá al menos una cantidad recibida.');
            }
            DB::actualizar('recepciones', ['total' => round($total, 2)], 'id = :id', ['id' => $id]);
            if ($oc) {
                self::actualizarEstadoOrden((int) $oc['id']);
            }
            Auditoria::registrar('compras', 'recepcion', 'recepcion', $id, null, ['total' => round($total, 2), 'items' => $n], 'Recepción de mercadería');
            return $id;
        });
    }

    public static function actualizarEstadoOrden(int $ordenId): void
    {
        $f = DB::fila('SELECT SUM(cantidad_base) AS pedido, SUM(LEAST(cantidad_recibida_base, cantidad_base)) AS recibido, SUM(cantidad_recibida_base) AS bruto FROM orden_compra_items WHERE orden_id = ?', [$ordenId]);
        $estadoActual = DB::valor('SELECT estado FROM ordenes_compra WHERE id = ?', [$ordenId]);
        if ($estadoActual === 'anulada') {
            return;
        }
        $estado = (float) $f['bruto'] <= 0.0005 ? 'enviada' : ((float) $f['recibido'] + 0.0005 >= (float) $f['pedido'] ? 'recibida' : 'recibida_parcial');
        DB::actualizar('ordenes_compra', ['estado' => $estado], 'id = :id', ['id' => $ordenId]);
    }

    public static function anularRecepcion(int $id, string $motivo): void
    {
        DB::transaccion(function () use ($id, $motivo) {
            $r = DB::fila('SELECT * FROM recepciones WHERE id = ? FOR UPDATE', [$id]);
            if (!$r || $r['estado'] !== 'aplicada') {
                throw new ErrorNegocio('La recepción no se puede anular.');
            }
            StockService::revertirDocumento('recepcion', $id, 'Anulación recepción N° ' . $r['numero']);
            foreach (DB::filas('SELECT * FROM recepcion_items WHERE recepcion_id = ? AND orden_item_id IS NOT NULL', [$id]) as $it) {
                DB::query('UPDATE orden_compra_items SET cantidad_recibida_base = GREATEST(0, cantidad_recibida_base - ?) WHERE id = ?', [$it['cantidad_base'], $it['orden_item_id']]);
            }
            DB::actualizar('recepciones', ['estado' => 'anulada', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $id]);
            if ($r['orden_id']) {
                self::actualizarEstadoOrden((int) $r['orden_id']);
            }
            Auditoria::registrar('compras', 'anular', 'recepcion', $id, ['estado' => 'aplicada'], ['estado' => 'anulada', 'motivo' => $motivo], 'Anulación recepción N° ' . $r['numero']);
        });
    }

    /** Registra la factura (o NC/ND) del proveedor e impacta su cuenta corriente. */
    public static function registrarFactura(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            $prov = DB::fila('SELECT * FROM proveedores WHERE id = ?', [(int) ($d['proveedor_id'] ?? 0)]);
            if (!$prov) {
                throw new ErrorNegocio('Elegí el proveedor.');
            }
            $tipo = (string) ($d['tipo'] ?? 'FA');
            if (!in_array($tipo, ['FA', 'FB', 'FC', 'NCA', 'NCB', 'NCC', 'NDA', 'NDB', 'NDC'], true)) {
                throw new ErrorNegocio('Tipo de comprobante inválido.');
            }
            $numero = (int) ($d['numero'] ?? 0);
            $pv = (int) ($d['punto_venta'] ?? 0);
            if ($numero <= 0 || $pv <= 0) {
                throw new ErrorNegocio('Indicá punto de venta y número del comprobante del proveedor.');
            }
            if (DB::valor("SELECT id FROM facturas_proveedor WHERE proveedor_id = ? AND tipo = ? AND punto_venta = ? AND numero = ? AND estado <> 'anulada'", [$prov['id'], $tipo, $pv, $numero])) {
                throw new ErrorNegocio('Ese comprobante del proveedor ya fue cargado.');
            }
            $campos = ['neto_gravado', 'no_gravado', 'iva_21', 'iva_105', 'iva_27', 'percepcion_iva', 'percepcion_iibb', 'impuestos_internos', 'otros'];
            $importes = [];
            foreach ($campos as $c) {
                $importes[$c] = round(parse_decimal($d[$c] ?? 0), 2);
            }
            $total = round(array_sum($importes), 2);
            $totalInformado = isset($d['total']) && $d['total'] !== '' ? round(parse_decimal($d['total']), 2) : $total;
            if (abs($totalInformado - $total) > 0.05) {
                throw new ErrorNegocio('El total (' . money($totalInformado) . ') no coincide con la suma de los importes (' . money($total) . ').');
            }
            if ($total <= 0) {
                throw new ErrorNegocio('El total debe ser mayor a cero.');
            }
            $fecha = parse_fecha($d['fecha'] ?? '') ?? hoy();
            $venc = parse_fecha($d['vencimiento'] ?? '') ?? date('Y-m-d', strtotime("$fecha +{$prov['plazo_pago_dias']} days"));
            $id = DB::insertar('facturas_proveedor', $importes + [
                'proveedor_id' => $prov['id'], 'tipo' => $tipo, 'punto_venta' => $pv, 'numero' => $numero, 'fecha' => $fecha,
                'vencimiento' => $venc, 'total' => $total, 'orden_id' => !empty($d['orden_id']) ? (int) $d['orden_id'] : null,
                'estado' => 'registrada', 'observaciones' => $d['observaciones'] ?? null, 'usuario_id' => Auth::id(), 'creado_en' => ahora(),
            ]);
            if (!empty($d['recepcion_id'])) {
                DB::query('UPDATE recepciones SET factura_proveedor_id = ? WHERE id = ? AND proveedor_id = ?', [$id, (int) $d['recepcion_id'], $prov['id']]);
            }
            $signo = str_starts_with($tipo, 'NC') ? -1 : 1;
            $desc = $tipo . ' ' . sprintf('%05d-%08d', $pv, $numero);
            CuentaCorriente::proveedores()->registrar((int) $prov['id'], 'factura', $signo * $total, $desc, 'factura_proveedor', $id, $venc, $fecha . ' ' . date('H:i:s'));
            Auditoria::registrar('compras', 'factura', 'factura_proveedor', $id, null, ['total' => $total, 'tipo' => $tipo], "Factura proveedor $desc");
            return $id;
        });
    }

    public static function anularFactura(int $id, string $motivo): void
    {
        DB::transaccion(function () use ($id, $motivo) {
            $f = DB::fila('SELECT * FROM facturas_proveedor WHERE id = ? FOR UPDATE', [$id]);
            if (!$f || $f['estado'] === 'anulada') {
                throw new ErrorNegocio('La factura no se puede anular.');
            }
            CuentaCorriente::proveedores()->anularDocumento('factura_proveedor', $id, 'Anulación ' . $f['tipo'] . ' ' . $f['numero']);
            DB::actualizar('facturas_proveedor', ['estado' => 'anulada', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $id]);
            DB::query('UPDATE recepciones SET factura_proveedor_id = NULL WHERE factura_proveedor_id = ?', [$id]);
            Auditoria::registrar('compras', 'anular', 'factura_proveedor', $id, ['estado' => $f['estado']], ['estado' => 'anulada', 'motivo' => $motivo]);
        });
    }

    /**
     * Pago a proveedor. $d: proveedor_id, detalle [[forma (efectivo|transferencia|cheque_propio|cheque_tercero), importe, cuenta_bancaria_id, cheque_id, referencia]],
     * imputaciones [cc_id => importe], observaciones, caja_id
     */
    public static function registrarPago(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            $prov = DB::fila('SELECT * FROM proveedores WHERE id = ?', [(int) ($d['proveedor_id'] ?? 0)]);
            if (!$prov) {
                throw new ErrorNegocio('Elegí el proveedor.');
            }
            $detalle = [];
            foreach ($d['detalle'] ?? [] as $x) {
                $imp = round(parse_decimal($x['importe'] ?? 0), 2);
                if ($x['forma'] === 'cheque_tercero' && !empty($x['cheque_id'])) {
                    $imp = (float) DB::valor('SELECT importe FROM cheques WHERE id = ?', [(int) $x['cheque_id']]);
                }
                if ($imp > 0) {
                    $x['importe'] = $imp;
                    $detalle[] = $x;
                }
            }
            if (!$detalle) {
                throw new ErrorNegocio('Ingresá al menos una forma de pago con importe.');
            }
            $total = round(array_sum(array_column($detalle, 'importe')), 2);
            $numero = Numeracion::siguiente('PP');
            $id = DB::insertar('pagos_proveedor', [
                'numero' => $numero, 'fecha' => ahora(), 'proveedor_id' => $prov['id'], 'total' => $total,
                'observaciones' => $d['observaciones'] ?? null, 'estado' => 'emitido', 'usuario_id' => Auth::id(),
            ]);
            $concepto = 'Pago N° ' . $numero . ' a ' . $prov['razon_social'];
            foreach ($detalle as $x) {
                $fila = ['pago_id' => $id, 'forma_pago' => $x['forma'], 'importe' => $x['importe'], 'referencia' => $x['referencia'] ?? null];
                switch ($x['forma']) {
                    case 'efectivo':
                        $s = CajaService::sesionParaOperar(!empty($d['caja_id']) ? (int) $d['caja_id'] : null);
                        $fila['caja_sesion_id'] = $s['id'];
                        CajaService::movimiento((int) $s['id'], 'egreso', $concepto, $x['importe'], 'pago_proveedor', $id);
                        break;
                    case 'transferencia':
                    case 'cheque_propio':
                        $cta = (int) ($x['cuenta_bancaria_id'] ?? 0) ?: CajaService::cuentaPorForma('transferencia');
                        if (!$cta) {
                            throw new ErrorNegocio('Elegí la cuenta bancaria.');
                        }
                        $fila['cuenta_bancaria_id'] = $cta;
                        CajaService::movimientoBanco($cta, 'egreso', $concepto, $x['importe'], $x['referencia'] ?? null, 'pago_proveedor', $id);
                        break;
                    case 'cheque_tercero':
                        $ch = DB::fila('SELECT * FROM cheques WHERE id = ? FOR UPDATE', [(int) ($x['cheque_id'] ?? 0)]);
                        if (!$ch || $ch['estado'] !== 'cartera') {
                            throw new ErrorNegocio('El cheque elegido no está en cartera.');
                        }
                        $fila['cheque_id'] = $ch['id'];
                        DB::actualizar('cheques', ['estado' => 'endosado', 'proveedor_id' => $prov['id'], 'pago_proveedor_id' => $id, 'fecha_estado' => ahora()], 'id = :id', ['id' => $ch['id']]);
                        break;
                    default:
                        throw new ErrorNegocio('Forma de pago inválida.');
                }
                DB::insertar('pagos_proveedor_detalle', $fila);
            }
            $cc = CuentaCorriente::proveedores();
            $ccId = $cc->registrar((int) $prov['id'], 'pago', -$total, "Pago N° $numero", 'pago_proveedor', $id, null, null, false);
            if (!empty($d['imputaciones'])) {
                $cc->imputar($ccId, $d['imputaciones']);
            }
            $cc->aplicarPendientes((int) $prov['id']);
            Auditoria::registrar('compras', 'pago', 'pago_proveedor', $id, null, ['total' => $total], $concepto);
            return $id;
        });
    }

    public static function anularPago(int $id, string $motivo): void
    {
        DB::transaccion(function () use ($id, $motivo) {
            $p = DB::fila('SELECT * FROM pagos_proveedor WHERE id = ? FOR UPDATE', [$id]);
            if (!$p || $p['estado'] !== 'emitido') {
                throw new ErrorNegocio('El pago no se puede anular.');
            }
            $concepto = 'Anulación pago N° ' . $p['numero'];
            foreach (DB::filas('SELECT * FROM pagos_proveedor_detalle WHERE pago_id = ?', [$id]) as $x) {
                if ($x['forma_pago'] === 'efectivo') {
                    $mov = DB::valor("SELECT id FROM caja_movimientos WHERE origen_tipo = 'pago_proveedor' AND origen_id = ? AND importe = ? LIMIT 1", [$id, $x['importe']]);
                    if ($mov) {
                        CajaService::revertirMovimiento((int) $mov, $concepto);
                    }
                } elseif (in_array($x['forma_pago'], ['transferencia', 'cheque_propio'], true)) {
                    $mov = DB::valor("SELECT id FROM banco_movimientos WHERE origen_tipo = 'pago_proveedor' AND origen_id = ? AND importe = ? LIMIT 1", [$id, $x['importe']]);
                    if ($mov) {
                        CajaService::revertirMovimientoBanco((int) $mov, $concepto);
                    }
                } elseif ($x['cheque_id']) {
                    DB::actualizar('cheques', ['estado' => 'cartera', 'proveedor_id' => null, 'pago_proveedor_id' => null, 'fecha_estado' => ahora()], 'id = :id', ['id' => $x['cheque_id']]);
                }
            }
            CuentaCorriente::proveedores()->anularDocumento('pago_proveedor', $id, $concepto);
            DB::actualizar('pagos_proveedor', ['estado' => 'anulado', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $id]);
            Auditoria::registrar('compras', 'anular', 'pago_proveedor', $id, ['estado' => 'emitido'], ['estado' => 'anulado', 'motivo' => $motivo], $concepto);
        });
    }

    /**
     * Devolución a proveedor: egreso de stock y, opcionalmente, nota de crédito del proveedor.
     * $d: proveedor_id, deposito_id, motivo, items [[producto_id, cantidad (unidad base), costo_unitario]], nc [punto_venta, numero, fecha]
     */
    public static function devolucion(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            if (empty($d['proveedor_id'])) {
                throw new ErrorNegocio('Elegí el proveedor.');
            }
            $deposito = (int) ($d['deposito_id'] ?? 0) ?: (int) Ajustes::get('deposito_central', 1);
            $numero = Numeracion::siguiente('DP');
            $id = DB::insertar('devoluciones_proveedor', [
                'numero' => $numero, 'fecha' => ahora(), 'proveedor_id' => (int) $d['proveedor_id'], 'deposito_id' => $deposito,
                'motivo' => $d['motivo'] ?? null, 'estado' => 'aplicada', 'usuario_id' => Auth::id(),
            ]);
            $total = 0.0;
            foreach ($d['items'] ?? [] as $i) {
                $cant = round(parse_decimal($i['cantidad'] ?? 0), 3);
                if ($cant <= 0 || empty($i['producto_id'])) {
                    continue;
                }
                $p = DB::fila('SELECT * FROM productos WHERE id = ?', [(int) $i['producto_id']]);
                [, $factor] = VentaService::presentacion((int) $i['producto_id'], (int) ($i['presentacion_id'] ?? 0), $i['factor'] ?? null);
                $base = round($cant * $factor, 3);
                $costo = isset($i['costo_unitario']) && $i['costo_unitario'] !== '' ? round(parse_decimal($i['costo_unitario']) / $factor, 4) : (float) $p['costo'];
                DB::insertar('devoluciones_proveedor_items', ['devolucion_id' => $id, 'producto_id' => $p['id'], 'cantidad_base' => $base, 'costo_unitario_base' => $costo]);
                StockService::mover((int) $p['id'], $deposito, -$base, 'devolucion_proveedor', 'devolucion_proveedor', $id, ['costo' => $costo, 'motivo' => 'Devolución a proveedor N° ' . $numero]);
                $total += $base * $costo;
            }
            if ($total <= 0) {
                throw new ErrorNegocio('Indicá los productos y cantidades a devolver.');
            }
            DB::actualizar('devoluciones_proveedor', ['total' => round($total, 2)], 'id = :id', ['id' => $id]);
            if (!empty($d['nc']['numero'])) {
                $prov = DB::fila('SELECT * FROM proveedores WHERE id = ?', [(int) $d['proveedor_id']]);
                $letra = $prov['condicion_iva'] === 'RI' && Ajustes::get('empresa_condicion_iva') === 'RI' ? 'A' : ($prov['condicion_iva'] === 'RI' ? 'B' : 'C');
                $neto = round($total, 2);
                $iva = $letra === 'A' ? round($neto * 0.21, 2) : 0;
                $ncId = self::registrarFactura([
                    'proveedor_id' => $d['proveedor_id'], 'tipo' => 'NC' . $letra, 'punto_venta' => (int) ($d['nc']['punto_venta'] ?? 1),
                    'numero' => (int) $d['nc']['numero'], 'fecha' => $d['nc']['fecha'] ?? hoy(), 'neto_gravado' => $neto, 'iva_21' => $iva,
                    'observaciones' => 'Devolución N° ' . $numero,
                ]);
                DB::actualizar('devoluciones_proveedor', ['nota_credito_id' => $ncId], 'id = :id', ['id' => $id]);
            }
            Auditoria::registrar('compras', 'devolucion', 'devolucion_proveedor', $id, null, ['total' => round($total, 2)], 'Devolución a proveedor N° ' . $numero);
            return $id;
        });
    }

    public static function anularDevolucion(int $id, string $motivo): void
    {
        DB::transaccion(function () use ($id, $motivo) {
            $dv = DB::fila('SELECT * FROM devoluciones_proveedor WHERE id = ? FOR UPDATE', [$id]);
            if (!$dv || $dv['estado'] !== 'aplicada') {
                throw new ErrorNegocio('La devolución no se puede anular.');
            }
            StockService::revertirDocumento('devolucion_proveedor', $id, 'Anulación devolución N° ' . $dv['numero']);
            if ($dv['nota_credito_id']) {
                self::anularFactura((int) $dv['nota_credito_id'], 'Anulación devolución N° ' . $dv['numero']);
            }
            DB::actualizar('devoluciones_proveedor', ['estado' => 'anulada', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $id]);
            Auditoria::registrar('compras', 'anular', 'devolucion_proveedor', $id, ['estado' => 'aplicada'], ['estado' => 'anulada', 'motivo' => $motivo]);
        });
    }
}
