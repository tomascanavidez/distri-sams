<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Logística de reparto:
 * 1. Se arma la hoja de ruta con pedidos preparados (estado "armado").
 * 2. Al salir, la mercadería se transfiere del depósito al depósito del camión y se liberan las reservas.
 * 3. El chofer registra cada parada: entregado, parcial, rechazado o cliente cerrado, con cobro.
 *    Las entregas generan el comprobante (factura o remito) descontando del camión.
 * 4. En la rendición se concilia mercadería devuelta y dinero contra lo esperado; lo no entregado
 *    vuelve al depósito y los pedidos pueden reprogramarse.
 */
final class RepartoService
{
    public const RESULTADOS = [
        'entregado' => 'Entregado',
        'parcial' => 'Entrega parcial',
        'rechazado' => 'Rechazado',
        'cerrado' => 'Cliente cerrado',
    ];

    public const MOTIVOS_RECHAZO = [
        'sin_dinero' => 'Sin dinero',
        'no_pidio' => 'Dice que no hizo el pedido',
        'mercaderia_danada' => 'Mercadería dañada',
        'precio' => 'Diferencia de precio',
        'faltante' => 'Faltante en el camión',
        'otro' => 'Otro',
    ];

    /** Depósito asociado al vehículo (se crea si no existe). */
    public static function depositoVehiculo(int $vehiculoId): int
    {
        $id = DB::valor('SELECT id FROM depositos WHERE vehiculo_id = ?', [$vehiculoId]);
        if ($id) {
            return (int) $id;
        }
        $patente = DB::valor('SELECT patente FROM vehiculos WHERE id = ?', [$vehiculoId]);
        return DB::insertar('depositos', ['nombre' => 'Camión ' . $patente, 'tipo' => 'vehiculo', 'vehiculo_id' => $vehiculoId, 'activo' => 1]);
    }

    /** $d: fecha, vehiculo_id, chofer_id, zona_id, observaciones, pedidos [ids en orden] */
    public static function crear(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            $pedidos = array_values(array_unique(array_map('intval', $d['pedidos'] ?? [])));
            if (!$pedidos) {
                throw new ErrorNegocio('Elegí al menos un pedido para la hoja de ruta.');
            }
            if (empty($d['vehiculo_id']) || empty($d['chofer_id'])) {
                throw new ErrorNegocio('Elegí el vehículo y el chofer.');
            }
            $origen = (int) ($d['deposito_origen_id'] ?? 0) ?: (int) Ajustes::get('deposito_central', 1);
            $id = DB::insertar('hojas_ruta', [
                'numero' => Numeracion::siguiente('HR'), 'fecha' => parse_fecha($d['fecha'] ?? '') ?? hoy(),
                'vehiculo_id' => (int) $d['vehiculo_id'], 'chofer_id' => (int) $d['chofer_id'],
                'zona_id' => !empty($d['zona_id']) ? (int) $d['zona_id'] : null, 'deposito_origen_id' => $origen,
                'estado' => 'armado', 'observaciones' => $d['observaciones'] ?? null,
                'usuario_id' => \App\Core\Auth::id(), 'creado_en' => ahora(),
            ]);
            self::depositoVehiculo((int) $d['vehiculo_id']);
            self::agregarPedidos($id, $pedidos);
            Auditoria::registrar('reparto', 'crear', 'hoja_ruta', $id, null, ['pedidos' => count($pedidos)], 'Hoja de ruta creada');
            return $id;
        });
    }

    public static function agregarPedidos(int $hrId, array $pedidoIds): void
    {
        $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ? FOR UPDATE', [$hrId]);
        if ($hr['estado'] !== 'armado') {
            throw new ErrorNegocio('Sólo se pueden agregar pedidos a una hoja de ruta en armado.');
        }
        $orden = (int) DB::valor('SELECT COALESCE(MAX(orden), 0) FROM hoja_ruta_paradas WHERE hoja_ruta_id = ?', [$hrId]);
        foreach ($pedidoIds as $pid) {
            $p = DB::fila('SELECT id, numero, estado, cliente_id, hoja_ruta_id, deposito_id FROM pedidos WHERE id = ? FOR UPDATE', [$pid]);
            if (!$p || !in_array($p['estado'], ['confirmado', 'en_preparacion', 'preparado'], true)) {
                throw new ErrorNegocio('El pedido N° ' . ($p['numero'] ?? $pid) . ' no está listo para reparto (debe estar confirmado o preparado).');
            }
            if ($p['hoja_ruta_id']) {
                throw new ErrorNegocio('El pedido N° ' . $p['numero'] . ' ya está en otra hoja de ruta.');
            }
            if ((int) $p['deposito_id'] !== (int) $hr['deposito_origen_id']) {
                throw new ErrorNegocio('El pedido N° ' . $p['numero'] . ' sale de otro depósito.');
            }
            DB::insertar('hoja_ruta_paradas', [
                'hoja_ruta_id' => $hrId, 'pedido_id' => $pid, 'cliente_id' => $p['cliente_id'], 'orden' => ++$orden, 'estado' => 'pendiente',
            ]);
            DB::query('UPDATE pedidos SET hoja_ruta_id = ?, actualizado_en = ? WHERE id = ?', [$hrId, ahora(), $pid]);
        }
    }

    public static function quitarPedido(int $hrId, int $pedidoId): void
    {
        DB::transaccion(function () use ($hrId, $pedidoId) {
            $hr = DB::fila('SELECT estado FROM hojas_ruta WHERE id = ? FOR UPDATE', [$hrId]);
            if (!$hr || $hr['estado'] !== 'armado') {
                throw new ErrorNegocio('Sólo se pueden quitar pedidos de una hoja de ruta en armado.');
            }
            DB::query('DELETE FROM hoja_ruta_paradas WHERE hoja_ruta_id = ? AND pedido_id = ?', [$hrId, $pedidoId]);
            DB::query('UPDATE pedidos SET hoja_ruta_id = NULL, actualizado_en = ? WHERE id = ? AND hoja_ruta_id = ?', [ahora(), $pedidoId, $hrId]);
        });
    }

    /** Reordena las paradas: [parada_id => orden] */
    public static function ordenar(int $hrId, array $ordenes): void
    {
        foreach ($ordenes as $paradaId => $orden) {
            DB::query('UPDATE hoja_ruta_paradas SET orden = ? WHERE id = ? AND hoja_ruta_id = ?', [(int) $orden, (int) $paradaId, $hrId]);
        }
    }

    /** Carga consolidada del camión: [producto_id => [descripcion, codigo, ubicacion, cantidad_base]] */
    public static function cargaConsolidada(int $hrId): array
    {
        return DB::filas(
            "SELECT p.id AS producto_id, p.codigo, p.descripcion, p.ubicacion, p.unidad_base, r.nombre AS rubro,
                    SUM(pi.cantidad_base + pi.bonificacion_base) AS cantidad
             FROM hoja_ruta_paradas hp
             JOIN pedido_items pi ON pi.pedido_id = hp.pedido_id
             JOIN productos p ON p.id = pi.producto_id
             LEFT JOIN rubros r ON r.id = p.rubro_id
             WHERE hp.hoja_ruta_id = ?
             GROUP BY p.id, p.codigo, p.descripcion, p.ubicacion, p.unidad_base, r.nombre
             ORDER BY p.ubicacion IS NULL, p.ubicacion, r.nombre, p.descripcion",
            [$hrId]
        );
    }

    /** Salida a reparto: transfiere la carga al camión y libera las reservas. */
    public static function iniciar(int $hrId): void
    {
        DB::transaccion(function () use ($hrId) {
            $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ? FOR UPDATE', [$hrId]);
            if (!$hr || $hr['estado'] !== 'armado') {
                throw new ErrorNegocio('La hoja de ruta no está en armado.');
            }
            $paradas = DB::filas('SELECT pedido_id FROM hoja_ruta_paradas WHERE hoja_ruta_id = ?', [$hrId]);
            if (!$paradas) {
                throw new ErrorNegocio('La hoja de ruta no tiene pedidos.');
            }
            foreach ($paradas as $pa) {
                PedidoService::liberarReservas((int) $pa['pedido_id']);
            }
            $items = [];
            foreach (self::cargaConsolidada($hrId) as $c) {
                $items[] = [(int) $c['producto_id'], (float) $c['cantidad']];
            }
            $camion = self::depositoVehiculo((int) $hr['vehiculo_id']);
            StockService::transferir((int) $hr['deposito_origen_id'], $camion, $items, 'Carga hoja de ruta N° ' . $hr['numero'], $hrId);
            DB::query("UPDATE pedidos SET estado = 'en_reparto', actualizado_en = ? WHERE hoja_ruta_id = ?", [ahora(), $hrId]);
            DB::actualizar('hojas_ruta', ['estado' => 'en_reparto', 'salida_en' => ahora()], 'id = :id', ['id' => $hrId]);
            Auditoria::registrar('reparto', 'salida', 'hoja_ruta', $hrId, ['estado' => 'armado'], ['estado' => 'en_reparto'], 'Salida a reparto HR N° ' . $hr['numero']);
        });
    }

    /**
     * Registra el resultado de una parada.
     * $d: resultado, items [pedido_item_id => cantidad_entregada_base] (para parcial), motivo, observaciones,
     *     cobros [[forma, importe, referencia, cheque{}]], evento_uuid
     * Devuelve ['comprobante_id' => ?, 'recibo_id' => ?, 'repetido' => bool]
     */
    public static function registrarEntrega(int $paradaId, array $d): array
    {
        return DB::transaccion(function () use ($paradaId, $d) {
            $uuid = $d['evento_uuid'] ?? null;
            $pa = DB::fila('SELECT * FROM hoja_ruta_paradas WHERE id = ? FOR UPDATE', [$paradaId]);
            if (!$pa) {
                throw new ErrorNegocio('La parada no existe.');
            }
            if ($pa['estado'] !== 'pendiente') {
                if ($uuid && $pa['evento_uuid'] === $uuid) {
                    return ['comprobante_id' => $pa['comprobante_id'], 'recibo_id' => $pa['recibo_id'], 'repetido' => true];
                }
                throw new ErrorNegocio('Esta entrega ya fue registrada (' . (self::RESULTADOS[$pa['estado']] ?? $pa['estado']) . ').');
            }
            $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ?', [$pa['hoja_ruta_id']]);
            if ($hr['estado'] !== 'en_reparto') {
                throw new ErrorNegocio('La hoja de ruta no está en reparto.');
            }
            $resultado = (string) ($d['resultado'] ?? '');
            if (!isset(self::RESULTADOS[$resultado])) {
                throw new ErrorNegocio('Resultado de entrega inválido.');
            }
            $pedido = DB::fila('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$pa['pedido_id']]);
            $camion = self::depositoVehiculo((int) $hr['vehiculo_id']);
            $compId = null;
            $reciboId = null;
            $cobros = CobranzaService::normalizarPagos($d['cobros'] ?? []);

            if (in_array($resultado, ['entregado', 'parcial'], true)) {
                $items = [];
                $hayFaltante = false;
                foreach (DB::filas('SELECT * FROM pedido_items WHERE pedido_id = ?', [$pedido['id']]) as $it) {
                    $pedidoBase = (float) $it['cantidad_base'];
                    $entregado = $resultado === 'entregado'
                        ? $pedidoBase
                        : min($pedidoBase, max(0, round(parse_decimal($d['items'][$it['id']] ?? 0), 3)));
                    if ($entregado < $pedidoBase - 0.0005) {
                        $hayFaltante = true;
                    }
                    // Bonificación proporcional a lo entregado
                    $bonif = $pedidoBase > 0 ? round((float) $it['bonificacion_base'] * $entregado / $pedidoBase, 0) : 0;
                    DB::query('UPDATE pedido_items SET entregado_base = ? WHERE id = ?', [$entregado, $it['id']]);
                    if ($entregado <= 0) {
                        continue;
                    }
                    // Si la cantidad no es múltiplo de la presentación, se factura en unidades base
                    $factor = (float) $it['factor'];
                    $enPresentacion = $entregado / $factor;
                    if (abs($enPresentacion - round($enPresentacion)) > 0.0005) {
                        $precioBase = round((float) $it['precio_unitario'] / $factor, 2);
                        $items[] = ['producto_id' => $it['producto_id'], 'factor' => 1, 'presentacion_nombre' => DB::valor('SELECT unidad_base FROM productos WHERE id = ?', [$it['producto_id']]),
                            'cantidad' => $entregado, 'precio_unitario' => $precioBase, 'descuento_porc' => $it['descuento_porc'], 'bonificacion_base' => $bonif, 'pedido_item_id' => $it['id']];
                    } else {
                        $items[] = ['producto_id' => $it['producto_id'], 'presentacion_id' => $it['presentacion_id'], 'factor' => $factor,
                            'presentacion_nombre' => $it['presentacion_nombre'], 'cantidad' => round($enPresentacion, 3),
                            'precio_unitario' => $it['precio_unitario'], 'descuento_porc' => $it['descuento_porc'], 'bonificacion_base' => $bonif, 'pedido_item_id' => $it['id']];
                    }
                }
                if (!$items) {
                    throw new ErrorNegocio('Indicá al menos una cantidad entregada (o marcá la parada como rechazada).');
                }
                if ($resultado === 'entregado') {
                    $hayFaltante = false;
                }
                $compId = VentaService::emitir([
                    'clase' => Ajustes::get('comprobante_reparto', 'factura') === 'remito' ? 'remito' : 'factura',
                    'cliente_id' => $pedido['cliente_id'], 'items' => $items, 'descuento_porc' => $pedido['descuento_porc'],
                    'deposito_id' => $camion, 'pedido_id' => $pedido['id'], 'hoja_ruta_id' => $hr['id'],
                    'lista_precio_id' => $pedido['lista_precio_id'], 'preventista_id' => $pedido['preventista_id'],
                    'origen' => 'reparto', 'pagos' => [], 'validar_credito' => false, 'controlar_reservas' => false,
                    'observaciones' => 'Pedido N° ' . $pedido['numero'] . ' - HR N° ' . $hr['numero'],
                    'fecha' => $d['fecha'] ?? null,
                ]);
                $estadoPedido = $hayFaltante || $resultado === 'parcial' ? 'entregado_parcial' : 'entregado';
                DB::actualizar('pedidos', ['estado' => $estadoPedido, 'comprobante_id' => $compId, 'actualizado_en' => ahora()], 'id = :id', ['id' => $pedido['id']]);
            }

            if ($cobros && $resultado !== 'cerrado') {
                $imputar = [];
                if ($compId) {
                    $deb = DB::valor("SELECT id FROM cc_clientes WHERE documento_tipo = 'comprobante' AND documento_id = ? AND importe > 0", [$compId]);
                    if ($deb) {
                        $imputar[(int) $deb] = array_sum(array_column($cobros, 'importe'));
                    }
                }
                $reciboId = CobranzaService::crearRecibo([
                    'cliente_id' => $pedido['cliente_id'], 'pagos' => $cobros, 'imputaciones' => $imputar,
                    'origen' => 'reparto', 'hoja_ruta_id' => $hr['id'], 'comprobante_id' => $compId,
                    'uuid' => $uuid ? self::uuidDerivado($uuid) : null,
                    'observaciones' => 'Cobro en reparto HR N° ' . $hr['numero'],
                ]);
            }

            $estadoParada = $resultado;
            $motivo = $d['motivo'] ?? null;
            if ($motivo && isset(self::MOTIVOS_RECHAZO[$motivo])) {
                $motivo = self::MOTIVOS_RECHAZO[$motivo];
            }
            DB::actualizar('hoja_ruta_paradas', [
                'estado' => $estadoParada, 'motivo' => $motivo ? mb_substr((string) $motivo, 0, 150) : null,
                'observaciones' => isset($d['observaciones']) ? mb_substr((string) $d['observaciones'], 0, 255) : null,
                'evento_uuid' => $uuid, 'registrado_en' => ahora(), 'comprobante_id' => $compId, 'recibo_id' => $reciboId,
            ], 'id = :id', ['id' => $paradaId]);
            Auditoria::registrar('reparto', 'entrega', 'parada', $paradaId, ['estado' => 'pendiente'], ['estado' => $estadoParada, 'motivo' => $motivo], 'Entrega HR N° ' . $hr['numero'] . ' - pedido N° ' . $pedido['numero']);
            return ['comprobante_id' => $compId, 'recibo_id' => $reciboId, 'repetido' => false];
        });
    }

    /** UUID determinístico para el recibo de un evento (evita duplicados al reintentar). */
    private static function uuidDerivado(string $uuid): string
    {
        $h = md5('recibo:' . $uuid);
        return sprintf('%s-%s-4%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
    }

    /** Datos para la pantalla de rendición: mercadería y dinero esperado. */
    public static function datosRendicion(int $hrId): array
    {
        $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ?', [$hrId]);
        $camion = self::depositoVehiculo((int) $hr['vehiculo_id']);
        $cargado = DB::pares(
            "SELECT ti.producto_id, SUM(ti.cantidad) FROM transferencias t JOIN transferencias_items ti ON ti.transferencia_id = t.id
             WHERE t.hoja_ruta_id = ? AND t.deposito_destino_id = ? AND t.estado = 'aplicada' GROUP BY ti.producto_id",
            [$hrId, $camion]
        );
        $entregado = DB::pares(
            "SELECT ci.producto_id, SUM(ci.cantidad_base + ci.bonificacion_base) FROM comprobantes c JOIN comprobante_items ci ON ci.comprobante_id = c.id
             WHERE c.hoja_ruta_id = ? AND c.estado = 'emitido' AND c.tipo NOT LIKE 'NC%' AND c.deposito_id = ? GROUP BY ci.producto_id",
            [$hrId, $camion]
        );
        $productos = [];
        foreach ($cargado as $pid => $cant) {
            $p = DB::fila('SELECT id, codigo, descripcion, unidad_base, costo, costo_promedio FROM productos WHERE id = ?', [$pid]);
            $enCamion = StockService::fisico((int) $pid, $camion);
            $productos[] = $p + [
                'cargado' => (float) $cant,
                'entregado' => (float) ($entregado[$pid] ?? 0),
                'devolucion_esperada' => max(0, $enCamion),
            ];
        }
        $pagos = DB::filas(
            "SELECT rp.forma_pago, SUM(rp.importe) AS importe FROM recibos r JOIN recibo_pagos rp ON rp.recibo_id = r.id
             WHERE r.hoja_ruta_id = ? AND r.estado = 'emitido' AND rp.pendiente_rendicion = 1 GROUP BY rp.forma_pago",
            [$hrId]
        );
        $esperado = [];
        foreach ($pagos as $p) {
            $esperado[$p['forma_pago']] = (float) $p['importe'];
        }
        $paradas = DB::filas(
            'SELECT hp.*, c.nombre_comercio, p.numero AS pedido_numero, p.total AS pedido_total
             FROM hoja_ruta_paradas hp JOIN clientes c ON c.id = hp.cliente_id JOIN pedidos p ON p.id = hp.pedido_id
             WHERE hp.hoja_ruta_id = ? ORDER BY hp.orden',
            [$hrId]
        );
        return ['hr' => $hr, 'camion' => $camion, 'productos' => $productos, 'esperado' => $esperado, 'paradas' => $paradas];
    }

    /**
     * Rendición del reparto.
     * $d: devuelto [producto_id => cantidad real], rendido [forma => importe], caja_id,
     *     reprogramar [parada_id => 1], observaciones
     */
    public static function rendir(int $hrId, array $d): int
    {
        return DB::transaccion(function () use ($hrId, $d) {
            $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ? FOR UPDATE', [$hrId]);
            if (!$hr || $hr['estado'] !== 'en_reparto') {
                throw new ErrorNegocio('Sólo se puede rendir una hoja de ruta en reparto.');
            }
            $datos = self::datosRendicion($hrId);
            $camion = $datos['camion'];
            $origen = (int) $hr['deposito_origen_id'];
            $nro = 'HR N° ' . $hr['numero'];

            // --- Dinero ---
            $esperadoEfectivo = (float) ($datos['esperado']['efectivo'] ?? 0);
            $rendidoEfectivo = round(parse_decimal($d['rendido']['efectivo'] ?? $esperadoEfectivo), 2);
            $sesionId = null;
            if ($rendidoEfectivo > 0) {
                $sesion = CajaService::sesionParaOperar(!empty($d['caja_id']) ? (int) $d['caja_id'] : null);
                $sesionId = (int) $sesion['id'];
                CajaService::movimiento($sesionId, 'ingreso', "Rendición $nro", $rendidoEfectivo, 'rendicion', $hrId);
            }
            $otrosEsperado = 0.0;
            $otrosConfirmado = 0.0;

            $rendId = DB::insertar('rendiciones', [
                'hoja_ruta_id' => $hrId, 'fecha' => ahora(), 'efectivo_esperado' => $esperadoEfectivo,
                'efectivo_rendido' => $rendidoEfectivo, 'diferencia_efectivo' => round($rendidoEfectivo - $esperadoEfectivo, 2),
                'caja_sesion_id' => $sesionId, 'observaciones' => $d['observaciones'] ?? null, 'usuario_id' => \App\Core\Auth::id(),
            ]);
            $formas = array_unique(array_merge(array_keys($datos['esperado']), ['efectivo']));
            foreach ($formas as $forma) {
                $esp = (float) ($datos['esperado'][$forma] ?? 0);
                $ren = $forma === 'efectivo' ? $rendidoEfectivo : round(parse_decimal($d['rendido'][$forma] ?? $esp), 2);
                if ($forma !== 'efectivo') {
                    $otrosEsperado += $esp;
                    $otrosConfirmado += $ren;
                }
                if ($esp > 0 || $ren > 0) {
                    DB::insertar('rendicion_pagos', ['rendicion_id' => $rendId, 'forma_pago' => $forma, 'esperado' => $esp, 'rendido' => $ren, 'diferencia' => round($ren - $esp, 2)]);
                }
            }
            DB::query(
                'UPDATE recibo_pagos rp JOIN recibos r ON r.id = rp.recibo_id SET rp.pendiente_rendicion = 0 WHERE r.hoja_ruta_id = ?',
                [$hrId]
            );

            // --- Mercadería ---
            $ajuste = [];
            $devolver = [];
            $difValor = 0.0;
            foreach ($datos['productos'] as $p) {
                $esperada = (float) $p['devolucion_esperada'];
                $real = isset($d['devuelto'][$p['id']]) ? max(0, round(parse_decimal($d['devuelto'][$p['id']]), 3)) : $esperada;
                $dif = round($real - $esperada, 3);
                DB::insertar('rendicion_items', [
                    'rendicion_id' => $rendId, 'producto_id' => $p['id'], 'cargado' => $p['cargado'], 'entregado' => $p['entregado'],
                    'devolucion_esperada' => $esperada, 'devolucion_real' => $real, 'diferencia' => $dif,
                ]);
                if (abs($dif) >= 0.0005) {
                    $ajuste[] = [(int) $p['id'], $dif];
                    $difValor += $dif * PrecioService::costoBase($p);
                }
                if ($real > 0) {
                    $devolver[] = [(int) $p['id'], $real];
                }
            }
            $ajusteId = null;
            if ($ajuste) {
                $ajusteId = StockService::ajustar($camion, 'rendicion', $ajuste, "Rendición $nro");
            }
            if ($devolver) {
                StockService::transferir($camion, $origen, $devolver, "Descarga rendición $nro", $hrId, true);
            }
            DB::actualizar('rendiciones', [
                'otros_medios_esperado' => round($otrosEsperado, 2), 'otros_medios_confirmado' => round($otrosConfirmado, 2),
                'diferencia_mercaderia' => round($difValor, 2), 'ajuste_id' => $ajusteId,
            ], 'id = :id', ['id' => $rendId]);

            // --- Pedidos no entregados: reprogramar o cerrar como rechazados ---
            foreach ($datos['paradas'] as $pa) {
                if (!in_array($pa['estado'], ['pendiente', 'rechazado', 'cerrado'], true)) {
                    continue;
                }
                if (!empty($d['reprogramar'][$pa['id']])) {
                    self::reprogramarPedido((int) $pa['pedido_id'], $origen);
                    DB::query('UPDATE hoja_ruta_paradas SET reprogramado = 1 WHERE id = ?', [$pa['id']]);
                } else {
                    $motivo = $pa['motivo'] ?: ($pa['estado'] === 'pendiente' ? 'No visitado' : RepartoService::RESULTADOS[$pa['estado']]);
                    DB::query("UPDATE pedidos SET estado = 'rechazado', motivo_rechazo = ?, actualizado_en = ? WHERE id = ?", [$motivo, ahora(), $pa['pedido_id']]);
                    if ($pa['estado'] === 'pendiente') {
                        DB::query("UPDATE hoja_ruta_paradas SET estado = 'rechazado', motivo = 'No visitado' WHERE id = ?", [$pa['id']]);
                    }
                }
            }
            DB::actualizar('hojas_ruta', ['estado' => 'rendida', 'rendida_en' => ahora()], 'id = :id', ['id' => $hrId]);
            Auditoria::registrar('reparto', 'rendicion', 'hoja_ruta', $hrId, ['efectivo_esperado' => $esperadoEfectivo], ['efectivo_rendido' => $rendidoEfectivo, 'diferencia_mercaderia' => round($difValor, 2)], "Rendición $nro");
            return $rendId;
        });
    }

    /** Devuelve el pedido a "preparado" para incluirlo en otra hoja de ruta, reservando stock otra vez. */
    public static function reprogramarPedido(int $pedidoId, int $deposito): void
    {
        DB::query('UPDATE pedido_items SET entregado_base = NULL, reservado_base = 0 WHERE pedido_id = ?', [$pedidoId]);
        DB::query("UPDATE pedidos SET estado = 'preparado', hoja_ruta_id = NULL, deposito_id = ?, actualizado_en = ? WHERE id = ?", [$deposito, ahora(), $pedidoId]);
        PedidoService::reservar($pedidoId);
        $nuevaFecha = date('Y-m-d', strtotime('+1 day', \App\Core\Reloj::ahora()));
        DB::query('UPDATE pedidos SET fecha_entrega = ? WHERE id = ?', [$nuevaFecha, $pedidoId]);
        Auditoria::registrar('pedidos', 'reprogramar', 'pedido', $pedidoId, null, ['fecha_entrega' => $nuevaFecha], 'Entrega reprogramada');
    }

    /** Anula una hoja de ruta sin entregas registradas (devuelve la carga si ya había salido). */
    public static function anular(int $hrId, string $motivo): void
    {
        DB::transaccion(function () use ($hrId, $motivo) {
            $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ? FOR UPDATE', [$hrId]);
            if (!$hr || !in_array($hr['estado'], ['armado', 'en_reparto'], true)) {
                throw new ErrorNegocio('Sólo se pueden anular hojas de ruta en armado o en reparto.');
            }
            $registradas = (int) DB::valor("SELECT COUNT(*) FROM hoja_ruta_paradas WHERE hoja_ruta_id = ? AND estado <> 'pendiente'", [$hrId]);
            if ($registradas) {
                throw new ErrorNegocio('Ya hay entregas registradas: hacé la rendición en lugar de anular.');
            }
            if ($hr['estado'] === 'en_reparto') {
                foreach (DB::columna("SELECT id FROM transferencias WHERE hoja_ruta_id = ? AND estado = 'aplicada'", [$hrId]) as $tid) {
                    StockService::revertirDocumento('transferencia', (int) $tid, 'Anulación HR N° ' . $hr['numero']);
                    DB::query("UPDATE transferencias SET estado = 'anulada', motivo_anulacion = ? WHERE id = ?", [$motivo, $tid]);
                }
                foreach (DB::columna('SELECT pedido_id FROM hoja_ruta_paradas WHERE hoja_ruta_id = ?', [$hrId]) as $pid) {
                    DB::query("UPDATE pedidos SET estado = 'preparado', hoja_ruta_id = NULL, actualizado_en = ? WHERE id = ?", [ahora(), $pid]);
                    PedidoService::reservar((int) $pid);
                }
            } else {
                DB::query('UPDATE pedidos SET hoja_ruta_id = NULL, actualizado_en = ? WHERE hoja_ruta_id = ?', [ahora(), $hrId]);
            }
            DB::actualizar('hojas_ruta', ['estado' => 'anulada', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $hrId]);
            Auditoria::registrar('reparto', 'anular', 'hoja_ruta', $hrId, ['estado' => $hr['estado']], ['estado' => 'anulada', 'motivo' => $motivo], 'Anulación HR N° ' . $hr['numero']);
        });
    }
}
