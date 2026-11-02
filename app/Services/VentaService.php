<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Services\Facturacion\FacturacionFactory;

/**
 * Emisión y anulación de comprobantes de venta: facturas A/B/C, remitos, notas de crédito
 * y débito, presupuestos. Mueve stock, cuenta corriente y genera el recibo de los pagos.
 */
final class VentaService
{
    /** Clases que descuentan stock y generan deuda. */
    private const CLASES_VENTA = ['factura', 'remito'];

    public static function emitir(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            $cliente = DB::fila('SELECT * FROM clientes WHERE id = ?', [(int) ($d['cliente_id'] ?? 0)]);
            if (!$cliente) {
                throw new ErrorNegocio('Elegí un cliente.');
            }
            $clase = $d['clase'] ?? 'factura';
            $letra = CalculadoraComprobante::letra((string) Ajustes::get('empresa_condicion_iva', 'RI'), $cliente['condicion_iva']);
            $tipo = CalculadoraComprobante::tipo($clase, $letra);
            if (in_array($clase, ['remito', 'presupuesto'], true)) {
                $letra = $clase === 'remito' ? 'R' : 'X';
            }
            $pv = (int) ($d['punto_venta_id'] ?? 0) ?: Numeracion::puntoVentaDefecto();
            $deposito = (int) ($d['deposito_id'] ?? 0) ?: (int) Ajustes::get('deposito_central', 1);
            $listaId = (int) ($d['lista_precio_id'] ?? 0) ?: (int) ($cliente['lista_precio_id'] ?? 0) ?: null;
            $fecha = $d['fecha'] ?? ahora();

            $items = self::prepararItems($d['items'] ?? [], $listaId, $clase);
            if (!$items) {
                throw new ErrorNegocio('El comprobante no tiene productos.');
            }
            $calc = CalculadoraComprobante::calcular($items, (float) ($d['descuento_porc'] ?? 0), $letra === 'C' ? 'C' : $letra);
            if ($calc['total'] <= 0 && $clase !== 'presupuesto') {
                throw new ErrorNegocio('El total del comprobante debe ser mayor a cero.');
            }

            $pagos = in_array($clase, self::CLASES_VENTA, true) ? CobranzaService::normalizarPagos($d['pagos'] ?? []) : [];
            $pagado = round(array_sum(array_column($pagos, 'importe')), 2);
            if ($pagado - $calc['total'] > 0.009) {
                throw new ErrorNegocio('Los pagos (' . money($pagado) . ') superan el total (' . money($calc['total']) . ').');
            }
            $aCuenta = round($calc['total'] - $pagado, 2);
            $condicion = $aCuenta > 0.009 ? 'cta_cte' : 'contado';

            if (in_array($clase, ['factura', 'remito', 'nd'], true) && $aCuenta > 0.009) {
                if ((int) $cliente['id'] === (int) Ajustes::get('cliente_consumidor_final', 1)) {
                    throw new ErrorNegocio('A "Consumidor Final" sólo se le puede vender de contado: registrá el pago completo.');
                }
                if (($d['validar_credito'] ?? true) && empty($d['forzar_credito'])) {
                    $problemas = ClienteService::problemasCredito((int) $cliente['id'], $aCuenta);
                    if ($problemas && Ajustes::get('credito_modo') === 'bloquear') {
                        throw new ErrorNegocio('No se puede vender a cuenta corriente: ' . implode(' ', $problemas), ['credito' => $problemas]);
                    }
                }
            }
            $vencimiento = $condicion === 'cta_cte'
                ? date('Y-m-d', strtotime(substr($fecha, 0, 10) . ' +' . (int) $cliente['dias_credito'] . ' days'))
                : substr($fecha, 0, 10);

            $proveedor = FacturacionFactory::para($pv);
            $numero = in_array($tipo, ['RM', 'PR'], true) ? Numeracion::siguiente($tipo, $pv) : $proveedor->proximoNumero($tipo, $pv);
            $mueveStock = $d['mueve_stock'] ?? in_array($clase, ['factura', 'remito', 'nc'], true);
            $costoTotal = 0.0;
            foreach ($calc['items'] as $it) {
                $costoTotal += (float) $it['costo_unitario'] * ((float) $it['cantidad'] * (float) $it['factor'] + (float) ($it['bonificacion_base'] ?? 0));
            }

            $comp = [
                'tipo' => $tipo, 'letra' => $letra, 'punto_venta_id' => $pv, 'numero' => $numero, 'fecha' => $fecha,
                'vencimiento' => $vencimiento, 'cliente_id' => $cliente['id'],
                'cliente_nombre' => $cliente['nombre_comercio'] . ($cliente['titular'] ? ' (' . $cliente['titular'] . ')' : ''),
                'cliente_documento' => $cliente['documento'] ? $cliente['tipo_documento'] . ' ' . $cliente['documento'] : null,
                'cliente_condicion_iva' => $cliente['condicion_iva'],
                'cliente_direccion' => trim(($cliente['direccion'] ?? '') . ($cliente['localidad'] ? ', ' . $cliente['localidad'] : '')) ?: null,
                'pedido_id' => $d['pedido_id'] ?? null, 'comprobante_asociado_id' => $d['comprobante_asociado_id'] ?? null,
                'hoja_ruta_id' => $d['hoja_ruta_id'] ?? null, 'usuario_id' => Auth::id(),
                'preventista_id' => $d['preventista_id'] ?? $cliente['preventista_id'],
                'deposito_id' => $deposito, 'lista_precio_id' => $listaId, 'condicion_venta' => $condicion,
                'origen' => $d['origen'] ?? 'oficina',
                'neto_21' => $calc['neto_21'], 'neto_105' => $calc['neto_105'], 'neto_exento' => $calc['neto_exento'],
                'iva_21' => $calc['iva_21'], 'iva_105' => $calc['iva_105'], 'impuestos_internos' => $calc['impuestos_internos'],
                'subtotal' => $calc['subtotal'], 'descuento_porc' => (float) ($d['descuento_porc'] ?? 0),
                'descuento_importe' => $calc['descuento_importe'], 'total' => $calc['total'],
                'costo_total' => round($costoTotal, 2), 'mueve_stock' => $mueveStock ? 1 : 0,
                'afecta_cc' => $clase === 'presupuesto' ? 0 : 1, 'estado' => 'emitido',
                'proveedor_facturacion' => $proveedor->codigo(),
                'observaciones' => isset($d['observaciones']) ? mb_substr((string) $d['observaciones'], 0, 255) : null,
                'creado_en' => ahora(),
            ];
            $id = DB::insertar('comprobantes', $comp);
            $nro = numero_comprobante($tipo, Numeracion::numeroPuntoVenta($pv), $numero);

            $filasItems = [];
            foreach ($calc['items'] as $it) {
                $fila = [
                    'comprobante_id' => $id, 'producto_id' => $it['producto_id'], 'pedido_item_id' => $it['pedido_item_id'] ?? null,
                    'descripcion' => $it['descripcion'], 'presentacion_nombre' => $it['presentacion_nombre'],
                    'cantidad' => $it['cantidad'], 'factor' => $it['factor'], 'cantidad_base' => round((float) $it['cantidad'] * (float) $it['factor'], 3),
                    'bonificacion_base' => $it['bonificacion_base'] ?? 0, 'precio_unitario' => $it['precio_unitario'],
                    'descuento_porc' => $it['descuento_porc'] ?? 0, 'iva_alicuota' => $it['iva_alicuota'],
                    'neto' => $it['neto'], 'iva' => $it['iva'], 'internos' => $it['internos'], 'total' => $it['total'],
                    'costo_unitario' => $it['costo_unitario'], 'reingresa_stock' => !empty($it['reingresa_stock']) ? 1 : 0,
                ];
                DB::insertar('comprobante_items', $fila);
                $filasItems[] = $fila;

                if ($mueveStock && $it['producto_id']) {
                    $cantBase = $fila['cantidad_base'] + (float) $fila['bonificacion_base'];
                    if ($clase === 'nc') {
                        if ($fila['reingresa_stock']) {
                            StockService::mover((int) $it['producto_id'], $deposito, $cantBase, 'devolucion_cliente', 'comprobante', $id, ['costo' => $it['costo_unitario'], 'motivo' => $nro]);
                        }
                    } else {
                        StockService::mover((int) $it['producto_id'], $deposito, -$cantBase, 'venta', 'comprobante', $id, [
                            'costo' => $it['costo_unitario'], 'motivo' => $nro,
                            'controlar_reservas' => $d['controlar_reservas'] ?? true,
                        ]);
                    }
                }
            }

            // Autorización (ARCA en el futuro; la interna no hace nada)
            if ($proveedor->requiereAutorizacion($tipo)) {
                $aut = $proveedor->autorizar($comp + ['id' => $id], $filasItems);
                DB::actualizar('comprobantes', ['cae' => $aut['cae'], 'cae_vencimiento' => $aut['cae_vencimiento']], 'id = :id', ['id' => $id]);
            }

            // Cuenta corriente
            $cc = CuentaCorriente::clientes();
            if ($clase === 'nc') {
                $ccId = $cc->registrar((int) $cliente['id'], 'comprobante', -$calc['total'], $nro, 'comprobante', $id, null, $fecha, false);
                if (!empty($d['comprobante_asociado_id'])) {
                    $deb = DB::valor("SELECT id FROM cc_clientes WHERE documento_tipo = 'comprobante' AND documento_id = ? AND importe > 0 AND pendiente > 0", [(int) $d['comprobante_asociado_id']]);
                    if ($deb) {
                        $cc->imputar($ccId, [(int) $deb => $calc['total']]);
                    }
                }
                $cc->aplicarPendientes((int) $cliente['id']);
            } elseif ($clase !== 'presupuesto') {
                $ccId = $cc->registrar((int) $cliente['id'], 'comprobante', $calc['total'], $nro, 'comprobante', $id, $vencimiento, $fecha, false);
                if ($pagos) {
                    CobranzaService::crearRecibo([
                        'cliente_id' => $cliente['id'], 'pagos' => $pagos, 'imputaciones' => [$ccId => $pagado],
                        'origen' => ($d['origen'] ?? '') === 'reparto' ? 'reparto' : 'mostrador',
                        'hoja_ruta_id' => $d['hoja_ruta_id'] ?? null, 'comprobante_id' => $id,
                        'uuid' => $d['recibo_uuid'] ?? null, 'fecha' => $fecha, 'caja_id' => $d['caja_id'] ?? null,
                        'observaciones' => "Pago de $nro",
                    ]);
                }
                $cc->aplicarPendientes((int) $cliente['id']);
            }

            if (in_array($clase, self::CLASES_VENTA, true)) {
                DB::query('UPDATE clientes SET ultima_compra = ? WHERE id = ?', [$fecha, $cliente['id']]);
            }
            Auditoria::registrar('ventas', 'emitir', 'comprobante', $id, null, ['tipo' => $tipo, 'total' => $calc['total'], 'cliente_id' => $cliente['id']], "Emisión $nro");
            return $id;
        });
    }

    /**
     * Completa cada ítem con datos del producto, presentación, precio de lista y costo.
     */
    public static function prepararItems(array $items, ?int $listaId, string $clase = 'factura'): array
    {
        $out = [];
        foreach ($items as $i) {
            $cantidad = round(parse_decimal($i['cantidad'] ?? 0), 3);
            if ($cantidad <= 0) {
                continue;
            }
            $prodId = (int) ($i['producto_id'] ?? 0);
            if (!$prodId) {
                // Concepto libre (notas de débito/crédito sin mercadería)
                $desc = trim((string) ($i['descripcion'] ?? ''));
                if ($desc === '') {
                    throw new ErrorNegocio('Falta la descripción de un concepto.');
                }
                $out[] = [
                    'producto_id' => null, 'descripcion' => mb_substr($desc, 0, 200), 'presentacion_nombre' => 'Unidad',
                    'cantidad' => $cantidad, 'factor' => 1, 'precio_unitario' => round(parse_decimal($i['precio_unitario'] ?? 0), 2),
                    'descuento_porc' => 0, 'iva_alicuota' => (float) ($i['iva_alicuota'] ?? 21), 'impuesto_interno' => 0,
                    'bonificacion_base' => 0, 'costo_unitario' => 0, 'reingresa_stock' => 0,
                ];
                continue;
            }
            $p = DB::fila('SELECT * FROM productos WHERE id = ?', [$prodId]);
            if (!$p) {
                throw new ErrorNegocio("Producto #$prodId inexistente.");
            }
            if (!(int) $p['activo'] && $clase !== 'nc') {
                throw new ErrorNegocio("El producto \"{$p['descripcion']}\" está inactivo.");
            }
            [$presNombre, $factor] = self::presentacion($prodId, (int) ($i['presentacion_id'] ?? 0), $i['factor'] ?? null, $i['presentacion_nombre'] ?? null);
            $precio = isset($i['precio_unitario']) && $i['precio_unitario'] !== '' && $i['precio_unitario'] !== null
                ? round(parse_decimal($i['precio_unitario']), 2)
                : round(PrecioService::precioLista($prodId, $listaId) * $factor, 2);
            $out[] = [
                'producto_id' => $prodId,
                'pedido_item_id' => $i['pedido_item_id'] ?? null,
                'descripcion' => $p['descripcion'],
                'presentacion_nombre' => $presNombre,
                'cantidad' => $cantidad,
                'factor' => $factor,
                'precio_unitario' => $precio,
                'descuento_porc' => min(100, max(0, parse_decimal($i['descuento_porc'] ?? 0))),
                'iva_alicuota' => (float) $p['iva_alicuota'],
                'impuesto_interno' => (float) $p['impuesto_interno'],
                'bonificacion_base' => max(0, round(parse_decimal($i['bonificacion_base'] ?? 0), 3)),
                'costo_unitario' => isset($i['costo_unitario']) ? (float) $i['costo_unitario'] : PrecioService::costoBase($p),
                'reingresa_stock' => !isset($i['reingresa_stock']) || !empty($i['reingresa_stock']) ? 1 : 0,
            ];
        }
        return $out;
    }

    /** [nombre, factor] de la presentación elegida (o la unidad base). */
    public static function presentacion(int $productoId, int $presentacionId, mixed $factor = null, ?string $nombre = null): array
    {
        if ($presentacionId) {
            $pr = DB::fila('SELECT nombre, factor FROM producto_presentaciones WHERE id = ? AND producto_id = ?', [$presentacionId, $productoId]);
            if ($pr) {
                return [$pr['nombre'], (float) $pr['factor']];
            }
        }
        if ($factor !== null && (float) $factor > 0) {
            return [$nombre ?: 'Unidad', (float) $factor];
        }
        $base = DB::valor('SELECT unidad_base FROM productos WHERE id = ?', [$productoId]);
        return [$base ?: 'Unidad', 1.0];
    }

    /**
     * Anula un comprobante: revierte stock, cuenta corriente y (opcionalmente) el recibo de pago.
     */
    public static function anular(int $id, string $motivo, bool $anularRecibos = true, ?int $depositoReingreso = null): void
    {
        DB::transaccion(function () use ($id, $motivo, $anularRecibos, $depositoReingreso) {
            $c = DB::fila('SELECT * FROM comprobantes WHERE id = ? FOR UPDATE', [$id]);
            if (!$c) {
                throw new ErrorNegocio('El comprobante no existe.');
            }
            if ($c['estado'] === 'anulado') {
                throw new ErrorNegocio('El comprobante ya está anulado.');
            }
            $nro = numero_comprobante($c['tipo'], Numeracion::numeroPuntoVenta((int) $c['punto_venta_id']), (int) $c['numero']);
            $ncActivas = DB::valor("SELECT COUNT(*) FROM comprobantes WHERE comprobante_asociado_id = ? AND estado = 'emitido'", [$id]);
            if ($ncActivas) {
                throw new ErrorNegocio('Tiene notas de crédito/débito asociadas vigentes: anulalas primero.');
            }
            if ($anularRecibos) {
                foreach (DB::columna("SELECT id FROM recibos WHERE comprobante_id = ? AND estado = 'emitido'", [$id]) as $rid) {
                    CobranzaService::anular((int) $rid, "Anulación de $nro: $motivo");
                }
            }
            if ((int) $c['mueve_stock']) {
                // Si salió de un camión, se reingresa al depósito central
                $dep = $depositoReingreso;
                if (!$dep && $c['deposito_id'] && DB::valor('SELECT tipo FROM depositos WHERE id = ?', [$c['deposito_id']]) === 'vehiculo') {
                    $dep = (int) Ajustes::get('deposito_central', 1);
                }
                StockService::revertirDocumento('comprobante', $id, "Anulación $nro", $dep);
            }
            if ((int) $c['afecta_cc']) {
                CuentaCorriente::clientes()->anularDocumento('comprobante', $id, "Anulación $nro");
            }
            DB::actualizar('comprobantes', [
                'estado' => 'anulado', 'motivo_anulacion' => $motivo, 'anulado_por' => Auth::id(), 'anulado_en' => ahora(),
            ], 'id = :id', ['id' => $id]);
            if ($c['pedido_id']) {
                DB::query(
                    "UPDATE pedidos SET estado = 'anulado', motivo_anulacion = ?, actualizado_en = ? WHERE id = ? AND comprobante_id = ?",
                    ["Venta $nro anulada: $motivo", ahora(), $c['pedido_id'], $id]
                );
            }
            Auditoria::registrar('ventas', 'anular', 'comprobante', $id, ['estado' => 'emitido', 'total' => $c['total']], ['estado' => 'anulado', 'motivo' => $motivo], "Anulación $nro");
        });
    }

    public static function numero(array $c): string
    {
        return numero_comprobante($c['tipo'], Numeracion::numeroPuntoVenta((int) $c['punto_venta_id']), (int) $c['numero']);
    }

    /** Cantidad ya devuelta (por NC vigentes) de cada producto de una factura. */
    public static function devuelto(int $facturaId): array
    {
        return DB::pares(
            "SELECT ci.producto_id, SUM(ci.cantidad_base) FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id
             WHERE c.comprobante_asociado_id = ? AND c.estado = 'emitido' AND c.tipo LIKE 'NC%' GROUP BY ci.producto_id",
            [$facturaId]
        );
    }
}
