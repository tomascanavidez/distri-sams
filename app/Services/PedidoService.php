<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Pedidos de preventa.
 * Estados: borrador → confirmado → en_preparacion → preparado → en_reparto →
 *          entregado / entregado_parcial / rechazado ; anulado.
 * Al confirmar se reserva stock (disponible = físico − reservado).
 */
final class PedidoService
{
    public const ESTADOS = [
        'borrador' => 'Borrador',
        'confirmado' => 'Confirmado',
        'en_preparacion' => 'En preparación',
        'preparado' => 'Preparado',
        'en_reparto' => 'En reparto',
        'entregado' => 'Entregado',
        'entregado_parcial' => 'Entregado parcial',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    /** Estados en que el pedido tiene stock reservado. */
    public const CON_RESERVA = ['confirmado', 'en_preparacion', 'preparado'];

    /**
     * Crea o actualiza un pedido. $d: cliente_id, items[], descuento_porc, observaciones, fecha_entrega,
     * deposito_id, preventista_id, uuid, origen, confirmar (bool), aplicar_promociones (bool)
     * Devuelve el id.
     */
    public static function guardar(array $d, ?int $id = null): int
    {
        return DB::transaccion(function () use ($d, $id) {
            $cliente = DB::fila('SELECT * FROM clientes WHERE id = ?', [(int) ($d['cliente_id'] ?? 0)]);
            if (!$cliente) {
                throw new ErrorNegocio('Elegí un cliente.');
            }
            $actual = null;
            if ($id) {
                $actual = DB::fila('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$id]);
                if (!$actual) {
                    throw new ErrorNegocio('El pedido no existe.');
                }
                if (!in_array($actual['estado'], ['borrador', 'confirmado', 'en_preparacion'], true)) {
                    throw new ErrorNegocio('Sólo se pueden modificar pedidos en borrador, confirmados o en preparación.');
                }
                if (in_array($actual['estado'], self::CON_RESERVA, true)) {
                    self::liberarReservas($id);
                }
            }
            $listaId = (int) ($cliente['lista_precio_id'] ?? 0) ?: null;
            $deposito = (int) ($d['deposito_id'] ?? 0) ?: (int) ($actual['deposito_id'] ?? 0) ?: (int) Ajustes::get('deposito_central', 1);
            $items = VentaService::prepararItems($d['items'] ?? [], $listaId, 'pedido');
            if (!$items) {
                throw new ErrorNegocio('Agregá al menos un producto al pedido.');
            }
            if (!empty($d['aplicar_promociones'])) {
                foreach ($items as &$it) {
                    if ((float) $it['bonificacion_base'] <= 0) {
                        $it['bonificacion_base'] = PrecioService::bonificacion((int) $it['producto_id'], $it['cantidad'] * $it['factor']);
                    }
                }
                unset($it);
            }
            $calc = CalculadoraComprobante::calcular($items, (float) ($d['descuento_porc'] ?? 0));
            $preventista = $d['preventista_id'] ?? ($actual['preventista_id'] ?? null) ?? Auth::usuario()['preventista_id'] ?? $cliente['preventista_id'];

            $datos = [
                'fecha_entrega' => parse_fecha($d['fecha_entrega'] ?? null) ?? ($actual['fecha_entrega'] ?? self::proximaEntrega($cliente)),
                'cliente_id' => $cliente['id'],
                'preventista_id' => $preventista ?: null,
                'lista_precio_id' => $listaId,
                'deposito_id' => $deposito,
                'subtotal' => $calc['subtotal'],
                'descuento_porc' => (float) ($d['descuento_porc'] ?? 0),
                'descuento_importe' => $calc['descuento_importe'],
                'total' => $calc['total'],
                'observaciones' => isset($d['observaciones']) ? mb_substr((string) $d['observaciones'], 0, 255) : null,
                'actualizado_en' => ahora(),
            ];
            if ($actual) {
                DB::actualizar('pedidos', $datos, 'id = :id', ['id' => $id]);
                DB::query('DELETE FROM pedido_items WHERE pedido_id = ?', [$id]);
                Auditoria::registrar('pedidos', 'editar', 'pedido', $id, ['total' => $actual['total']], ['total' => $calc['total']], 'Modificación del pedido N° ' . $actual['numero']);
            } else {
                $datos += [
                    'uuid' => $d['uuid'] ?? null,
                    'numero' => Numeracion::siguiente('PED'),
                    'fecha' => $d['fecha'] ?? ahora(),
                    'usuario_id' => Auth::id(),
                    'estado' => 'borrador',
                    'origen' => $d['origen'] ?? 'oficina',
                    'revision_requerida' => !empty($d['revision_detalle']) ? 1 : 0,
                    'revision_detalle' => $d['revision_detalle'] ?? null,
                    'creado_en' => ahora(),
                ];
                $id = DB::insertar('pedidos', $datos);
            }
            foreach ($calc['items'] as $it) {
                DB::insertar('pedido_items', [
                    'pedido_id' => $id, 'producto_id' => $it['producto_id'], 'presentacion_id' => self::presentacionId((int) $it['producto_id'], $it['presentacion_nombre'], (float) $it['factor']),
                    'presentacion_nombre' => $it['presentacion_nombre'], 'cantidad' => $it['cantidad'], 'factor' => $it['factor'],
                    'cantidad_base' => round($it['cantidad'] * $it['factor'], 3), 'bonificacion_base' => $it['bonificacion_base'],
                    'precio_unitario' => $it['precio_unitario'], 'descuento_porc' => $it['descuento_porc'],
                    'iva_alicuota' => $it['iva_alicuota'], 'subtotal' => $it['total'], 'reservado_base' => 0,
                ]);
            }
            if ($actual && in_array($actual['estado'], self::CON_RESERVA, true)) {
                self::reservar($id);
            } elseif (!empty($d['confirmar'])) {
                self::confirmar($id, !empty($d['forzar_credito']));
            }
            return $id;
        });
    }

    private static function presentacionId(int $productoId, string $nombre, float $factor): ?int
    {
        $id = DB::valor('SELECT id FROM producto_presentaciones WHERE producto_id = ? AND nombre = ? AND factor = ? LIMIT 1', [$productoId, $nombre, $factor]);
        return $id ? (int) $id : null;
    }

    /** Próximo día de entrega del cliente (según sus días o los de su zona). */
    public static function proximaEntrega(array $cliente): string
    {
        $dias = $cliente['dias_entrega'] ?: DB::valor('SELECT dias_entrega FROM zonas WHERE id = ?', [$cliente['zona_id'] ?? 0]);
        $lista = array_filter(array_map('intval', explode(',', (string) $dias)));
        for ($i = 1; $i <= 7; $i++) {
            $t = strtotime("+$i day", \App\Core\Reloj::ahora());
            if (!$lista || in_array((int) date('N', $t), $lista, true)) {
                if (!$lista && (int) date('N', $t) === 7) {
                    continue; // sin días definidos: no se entrega domingo
                }
                return date('Y-m-d', $t);
            }
        }
        return date('Y-m-d', strtotime('+1 day', \App\Core\Reloj::ahora()));
    }

    /** Confirma: valida cliente y crédito, y reserva stock. */
    public static function confirmar(int $id, bool $forzarCredito = false): void
    {
        DB::transaccion(function () use ($id, $forzarCredito) {
            $p = DB::fila('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$id]);
            if (!$p || $p['estado'] !== 'borrador') {
                throw new ErrorNegocio('Sólo se pueden confirmar pedidos en borrador.');
            }
            $cli = DB::fila('SELECT estado, nombre_comercio, condicion_pago FROM clientes WHERE id = ?', [$p['cliente_id']]);
            if ($cli['estado'] === 'bloqueado') {
                throw new ErrorNegocio("El cliente {$cli['nombre_comercio']} está bloqueado. Regularizá su cuenta antes de confirmar.");
            }
            if ($cli['estado'] === 'inactivo') {
                throw new ErrorNegocio("El cliente {$cli['nombre_comercio']} está inactivo.");
            }
            if (!$forzarCredito && $cli['condicion_pago'] === 'cta_cte' && Ajustes::get('credito_modo') === 'bloquear') {
                $problemas = ClienteService::problemasCredito((int) $p['cliente_id'], (float) $p['total']);
                if ($problemas) {
                    throw new ErrorNegocio('No se puede confirmar: ' . implode(' ', $problemas), ['credito' => $problemas]);
                }
            }
            self::reservar($id);
            DB::actualizar('pedidos', ['estado' => 'confirmado', 'revision_requerida' => 0, 'actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
            Auditoria::registrar('pedidos', 'confirmar', 'pedido', $id, ['estado' => 'borrador'], ['estado' => 'confirmado'], 'Pedido N° ' . $p['numero'] . ' confirmado');
        });
    }

    /** Reserva todas las líneas (stock disponible en el depósito del pedido). */
    public static function reservar(int $id): void
    {
        $p = DB::fila('SELECT deposito_id FROM pedidos WHERE id = ?', [$id]);
        $errores = [];
        foreach (DB::filas('SELECT * FROM pedido_items WHERE pedido_id = ?', [$id]) as $it) {
            $cant = round((float) $it['cantidad_base'] + (float) $it['bonificacion_base'] - (float) $it['reservado_base'], 3);
            if ($cant <= 0) {
                continue;
            }
            try {
                StockService::reservar((int) $it['producto_id'], (int) $p['deposito_id'], $cant);
                DB::query('UPDATE pedido_items SET reservado_base = reservado_base + ? WHERE id = ?', [$cant, $it['id']]);
            } catch (ErrorNegocio $e) {
                $errores[] = $e->getMessage();
            }
        }
        if ($errores) {
            throw new ErrorNegocio(implode("\n", $errores), ['stock' => $errores]);
        }
    }

    public static function liberarReservas(int $id): void
    {
        $p = DB::fila('SELECT deposito_id FROM pedidos WHERE id = ?', [$id]);
        foreach (DB::filas('SELECT * FROM pedido_items WHERE pedido_id = ? AND reservado_base > 0', [$id]) as $it) {
            StockService::liberar((int) $it['producto_id'], (int) $p['deposito_id'], (float) $it['reservado_base']);
        }
        DB::query('UPDATE pedido_items SET reservado_base = 0 WHERE pedido_id = ?', [$id]);
    }

    /** Cambios de estado de preparación: en_preparacion | preparado | confirmado (volver atrás). */
    public static function cambiarEstado(int $id, string $nuevo): void
    {
        $permitidos = [
            'confirmado' => ['en_preparacion'],
            'en_preparacion' => ['preparado', 'confirmado'],
            'preparado' => ['en_preparacion'],
        ];
        DB::transaccion(function () use ($id, $nuevo, $permitidos) {
            $p = DB::fila('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$id]);
            if (!$p || !in_array($nuevo, $permitidos[$p['estado']] ?? [], true)) {
                throw new ErrorNegocio('Cambio de estado no permitido para el pedido N° ' . ($p['numero'] ?? $id) . '.');
            }
            DB::actualizar('pedidos', ['estado' => $nuevo, 'actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
            Auditoria::registrar('pedidos', 'estado', 'pedido', $id, ['estado' => $p['estado']], ['estado' => $nuevo]);
        });
    }

    public static function anular(int $id, string $motivo): void
    {
        DB::transaccion(function () use ($id, $motivo) {
            $p = DB::fila('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$id]);
            if (!$p) {
                throw new ErrorNegocio('El pedido no existe.');
            }
            if (!in_array($p['estado'], ['borrador', 'confirmado', 'en_preparacion', 'preparado'], true)) {
                throw new ErrorNegocio('No se puede anular un pedido ' . (self::ESTADOS[$p['estado']] ?? $p['estado']) . '.');
            }
            if ($p['hoja_ruta_id']) {
                throw new ErrorNegocio('El pedido está asignado a una hoja de ruta: quitalo de la hoja primero.');
            }
            self::liberarReservas($id);
            DB::actualizar('pedidos', ['estado' => 'anulado', 'motivo_anulacion' => $motivo, 'actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
            Auditoria::registrar('pedidos', 'anular', 'pedido', $id, ['estado' => $p['estado']], ['estado' => 'anulado', 'motivo' => $motivo], 'Pedido N° ' . $p['numero'] . ' anulado');
        });
    }

    /**
     * Convierte el pedido en venta (retira en mostrador o se factura desde la oficina).
     * $opc: clase (factura|remito), pagos[], forzar_credito
     */
    public static function facturar(int $id, array $opc = []): int
    {
        return DB::transaccion(function () use ($id, $opc) {
            $p = DB::fila('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$id]);
            if (!$p || !in_array($p['estado'], ['borrador', 'confirmado', 'en_preparacion', 'preparado'], true)) {
                throw new ErrorNegocio('El pedido no se puede facturar en su estado actual.');
            }
            if ($p['hoja_ruta_id']) {
                throw new ErrorNegocio('El pedido está en una hoja de ruta: se factura al registrar la entrega.');
            }
            self::liberarReservas($id);
            $items = [];
            foreach (DB::filas('SELECT * FROM pedido_items WHERE pedido_id = ?', [$id]) as $it) {
                $items[] = [
                    'producto_id' => $it['producto_id'], 'presentacion_id' => $it['presentacion_id'], 'factor' => $it['factor'],
                    'presentacion_nombre' => $it['presentacion_nombre'], 'cantidad' => $it['cantidad'],
                    'precio_unitario' => $it['precio_unitario'], 'descuento_porc' => $it['descuento_porc'],
                    'bonificacion_base' => $it['bonificacion_base'], 'pedido_item_id' => $it['id'],
                ];
            }
            $compId = VentaService::emitir([
                'clase' => $opc['clase'] ?? 'factura', 'cliente_id' => $p['cliente_id'], 'items' => $items,
                'descuento_porc' => $p['descuento_porc'], 'deposito_id' => $p['deposito_id'], 'pedido_id' => $id,
                'lista_precio_id' => $p['lista_precio_id'], 'preventista_id' => $p['preventista_id'],
                'origen' => 'pedido', 'pagos' => $opc['pagos'] ?? [], 'forzar_credito' => $opc['forzar_credito'] ?? false,
                'observaciones' => $p['observaciones'],
            ]);
            DB::query('UPDATE pedido_items SET entregado_base = cantidad_base WHERE pedido_id = ?', [$id]);
            DB::actualizar('pedidos', ['estado' => 'entregado', 'comprobante_id' => $compId, 'actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
            return $compId;
        });
    }

    /** Ítems del último pedido (no anulado) del cliente, para "repetir último pedido". */
    public static function ultimoPedido(int $clienteId): array
    {
        $pid = DB::valor("SELECT id FROM pedidos WHERE cliente_id = ? AND estado <> 'anulado' ORDER BY fecha DESC, id DESC LIMIT 1", [$clienteId]);
        if (!$pid) {
            return [];
        }
        return DB::filas(
            'SELECT pi.producto_id, pi.presentacion_id, pi.presentacion_nombre, pi.factor, pi.cantidad, p.descripcion, p.codigo
             FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id WHERE pi.pedido_id = ? AND p.activo = 1',
            [$pid]
        );
    }
}
