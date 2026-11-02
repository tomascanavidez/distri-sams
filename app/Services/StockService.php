<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Único punto que modifica el stock. Cada cambio queda en el kardex (stock_movimientos)
 * con el saldo resultante. Maneja reservas y lotes (salidas por FEFO: primero vence, primero sale).
 */
final class StockService
{
    public const TIPOS = [
        'compra' => 'Ingreso por compra',
        'venta' => 'Venta',
        'devolucion_cliente' => 'Devolución de cliente',
        'devolucion_proveedor' => 'Devolución a proveedor',
        'ajuste' => 'Ajuste',
        'transferencia_salida' => 'Transferencia (salida)',
        'transferencia_entrada' => 'Transferencia (entrada)',
        'inventario' => 'Inventario físico',
        'anulacion' => 'Anulación',
        'saldo_inicial' => 'Saldo inicial',
    ];

    public const MOTIVOS_AJUSTE = [
        'rotura' => 'Rotura',
        'vencimiento' => 'Vencimiento',
        'faltante' => 'Faltante',
        'sobrante' => 'Sobrante',
        'consumo_interno' => 'Consumo interno',
        'inventario' => 'Inventario físico',
        'rendicion' => 'Diferencia de rendición',
        'otro' => 'Otro',
    ];

    /**
     * Mueve stock (cantidad con signo, en unidad base).
     * Opciones: costo, lote, vencimiento, motivo, fecha, controlar_reservas (bool), permitir_negativo (bool)
     * Devuelve los lotes afectados: [[lote, vencimiento, cantidad], ...] (vacío si no maneja lotes).
     */
    public static function mover(int $productoId, int $depositoId, float $cantidad, string $tipo, ?string $docTipo, ?int $docId, array $opc = []): array
    {
        $cantidad = round($cantidad, 3);
        if (abs($cantidad) < 0.0005) {
            return [];
        }
        return DB::transaccion(function () use ($productoId, $depositoId, $cantidad, $tipo, $docTipo, $docId, $opc) {
            $prod = DB::fila('SELECT id, descripcion, costo, costo_promedio, maneja_lotes FROM productos WHERE id = ?', [$productoId]);
            if (!$prod) {
                throw new ErrorNegocio("El producto #$productoId no existe.");
            }
            $fila = self::bloquear($productoId, $depositoId);
            $nuevo = round((float) $fila['cantidad'] + $cantidad, 3);

            if ($cantidad < 0) {
                $permitirNeg = $opc['permitir_negativo'] ?? (Ajustes::get('stock_permitir_negativo') === '1');
                $disponible = (float) $fila['cantidad'] - (!empty($opc['controlar_reservas']) ? (float) $fila['reservado'] : 0);
                if (!$permitirNeg && $disponible + $cantidad < -0.0005) {
                    $dep = DB::valor('SELECT nombre FROM depositos WHERE id = ?', [$depositoId]);
                    throw new ErrorNegocio(sprintf(
                        'Stock insuficiente de "%s" en %s: disponible %s, se necesitan %s.',
                        $prod['descripcion'],
                        $dep,
                        cant(max(0, $disponible)),
                        cant(-$cantidad)
                    ), ['producto_id' => $productoId, 'disponible' => $disponible]);
                }
            }

            DB::actualizar('stock', ['cantidad' => $nuevo], 'producto_id = :p AND deposito_id = :d', ['p' => $productoId, 'd' => $depositoId]);

            $costo = $opc['costo'] ?? PrecioService::costoBase($prod);
            $base = [
                'fecha' => $opc['fecha'] ?? ahora(),
                'producto_id' => $productoId,
                'deposito_id' => $depositoId,
                'tipo' => $tipo,
                'costo_unitario' => round((float) $costo, 4),
                'documento_tipo' => $docTipo,
                'documento_id' => $docId,
                'motivo' => isset($opc['motivo']) ? mb_substr((string) $opc['motivo'], 0, 150) : null,
                'usuario_id' => Auth::id(),
            ];

            if ((int) $prod['maneja_lotes'] !== 1) {
                DB::insertar('stock_movimientos', $base + ['lote_id' => null, 'cantidad' => $cantidad, 'saldo' => $nuevo]);
                return [];
            }

            // Con lotes: un renglón de kardex por lote afectado
            $afectados = [];
            $saldo = (float) $fila['cantidad'];
            if ($cantidad > 0) {
                $lote = trim((string) ($opc['lote'] ?? '')) ?: 'S/L';
                $loteId = self::loteId($productoId, $depositoId, $lote, $opc['vencimiento'] ?? null);
                DB::query('UPDATE stock_lotes SET cantidad = cantidad + ? WHERE id = ?', [$cantidad, $loteId]);
                $saldo += $cantidad;
                DB::insertar('stock_movimientos', $base + ['lote_id' => $loteId, 'cantidad' => $cantidad, 'saldo' => $saldo]);
                $afectados[] = [$lote, $opc['vencimiento'] ?? DB::valor('SELECT vencimiento FROM stock_lotes WHERE id = ?', [$loteId]), $cantidad];
                return $afectados;
            }

            $restante = -$cantidad;
            if (!empty($opc['lote'])) {
                $lotes = DB::filas('SELECT * FROM stock_lotes WHERE producto_id = ? AND deposito_id = ? AND lote = ? FOR UPDATE', [$productoId, $depositoId, $opc['lote']]);
            } else {
                $lotes = DB::filas(
                    'SELECT * FROM stock_lotes WHERE producto_id = ? AND deposito_id = ? AND cantidad > 0
                     ORDER BY vencimiento IS NULL, vencimiento, id FOR UPDATE',
                    [$productoId, $depositoId]
                );
            }
            foreach ($lotes as $l) {
                if ($restante <= 0.0005) {
                    break;
                }
                $toma = min($restante, (float) $l['cantidad']);
                if ($toma <= 0) {
                    continue;
                }
                DB::query('UPDATE stock_lotes SET cantidad = cantidad - ? WHERE id = ?', [$toma, $l['id']]);
                $saldo -= $toma;
                $restante = round($restante - $toma, 3);
                DB::insertar('stock_movimientos', $base + ['lote_id' => $l['id'], 'cantidad' => -$toma, 'saldo' => round($saldo, 3)]);
                $afectados[] = [$l['lote'], $l['vencimiento'], -$toma];
            }
            if ($restante > 0.0005) {
                // Sin lotes suficientes (stock negativo permitido o lotes desfasados): se descuenta del lote "S/L"
                $loteId = self::loteId($productoId, $depositoId, 'S/L', null);
                DB::query('UPDATE stock_lotes SET cantidad = cantidad - ? WHERE id = ?', [$restante, $loteId]);
                $saldo -= $restante;
                DB::insertar('stock_movimientos', $base + ['lote_id' => $loteId, 'cantidad' => -$restante, 'saldo' => round($saldo, 3)]);
                $afectados[] = ['S/L', null, -$restante];
            }
            return $afectados;
        });
    }

    /** Bloquea (FOR UPDATE) la fila de stock, creándola si no existe. */
    private static function bloquear(int $productoId, int $depositoId): array
    {
        DB::query('INSERT IGNORE INTO stock (producto_id, deposito_id, cantidad, reservado) VALUES (?, ?, 0, 0)', [$productoId, $depositoId]);
        return DB::fila('SELECT * FROM stock WHERE producto_id = ? AND deposito_id = ? FOR UPDATE', [$productoId, $depositoId]);
    }

    private static function loteId(int $productoId, int $depositoId, string $lote, ?string $vencimiento): int
    {
        $id = DB::valor('SELECT id FROM stock_lotes WHERE producto_id = ? AND deposito_id = ? AND lote = ?', [$productoId, $depositoId, $lote]);
        if ($id) {
            if ($vencimiento) {
                DB::query('UPDATE stock_lotes SET vencimiento = ? WHERE id = ? AND vencimiento IS NULL', [$vencimiento, $id]);
            }
            return (int) $id;
        }
        return DB::insertar('stock_lotes', [
            'producto_id' => $productoId, 'deposito_id' => $depositoId, 'lote' => mb_substr($lote, 0, 40),
            'vencimiento' => $vencimiento ?: null, 'cantidad' => 0,
        ]);
    }

    /** Reserva stock para un pedido confirmado. */
    public static function reservar(int $productoId, int $depositoId, float $cantidad, bool $controlar = true): void
    {
        if ($cantidad <= 0) {
            return;
        }
        DB::transaccion(function () use ($productoId, $depositoId, $cantidad, $controlar) {
            $fila = self::bloquear($productoId, $depositoId);
            $disponible = (float) $fila['cantidad'] - (float) $fila['reservado'];
            if ($controlar && Ajustes::get('stock_permitir_negativo') !== '1' && $disponible + 0.0005 < $cantidad) {
                $desc = DB::valor('SELECT descripcion FROM productos WHERE id = ?', [$productoId]);
                throw new ErrorNegocio(sprintf('Stock disponible insuficiente de "%s": hay %s, se piden %s.', $desc, cant(max(0, $disponible)), cant($cantidad)), ['producto_id' => $productoId, 'disponible' => $disponible]);
            }
            DB::query('UPDATE stock SET reservado = reservado + ? WHERE producto_id = ? AND deposito_id = ?', [round($cantidad, 3), $productoId, $depositoId]);
        });
    }

    public static function liberar(int $productoId, int $depositoId, float $cantidad): void
    {
        if ($cantidad <= 0) {
            return;
        }
        DB::query(
            'UPDATE stock SET reservado = GREATEST(0, reservado - ?) WHERE producto_id = ? AND deposito_id = ?',
            [round($cantidad, 3), $productoId, $depositoId]
        );
    }

    public static function disponible(int $productoId, int $depositoId): float
    {
        $f = DB::fila('SELECT cantidad - reservado AS d FROM stock WHERE producto_id = ? AND deposito_id = ?', [$productoId, $depositoId]);
        return $f ? (float) $f['d'] : 0.0;
    }

    public static function fisico(int $productoId, int $depositoId): float
    {
        return (float) (DB::valor('SELECT cantidad FROM stock WHERE producto_id = ? AND deposito_id = ?', [$productoId, $depositoId]) ?? 0);
    }

    /**
     * Transfiere entre depósitos conservando lotes y vencimientos. $items = [[producto_id, cantidad], ...]
     * Devuelve el id de la transferencia.
     */
    public static function transferir(int $origen, int $destino, array $items, ?string $observaciones = null, ?int $hojaRutaId = null, bool $permitirNegativo = false): int
    {
        if ($origen === $destino) {
            throw new ErrorNegocio('El depósito de origen y destino deben ser distintos.');
        }
        return DB::transaccion(function () use ($origen, $destino, $items, $observaciones, $hojaRutaId, $permitirNegativo) {
            $id = DB::insertar('transferencias', [
                'fecha' => ahora(), 'deposito_origen_id' => $origen, 'deposito_destino_id' => $destino,
                'observaciones' => $observaciones, 'estado' => 'aplicada', 'hoja_ruta_id' => $hojaRutaId, 'usuario_id' => Auth::id(),
            ]);
            $agrupado = [];
            foreach ($items as [$prod, $cant]) {
                $agrupado[(int) $prod] = ($agrupado[(int) $prod] ?? 0) + (float) $cant;
            }
            foreach ($agrupado as $prod => $cant) {
                if ($cant <= 0) {
                    continue;
                }
                DB::insertar('transferencias_items', ['transferencia_id' => $id, 'producto_id' => $prod, 'cantidad' => $cant]);
                $opc = $permitirNegativo ? ['permitir_negativo' => true] : [];
                $lotes = self::mover($prod, $origen, -$cant, 'transferencia_salida', 'transferencia', $id, $opc);
                if ($lotes) {
                    foreach ($lotes as [$lote, $venc, $c]) {
                        self::mover($prod, $destino, -$c, 'transferencia_entrada', 'transferencia', $id, ['lote' => $lote, 'vencimiento' => $venc]);
                    }
                } else {
                    self::mover($prod, $destino, $cant, 'transferencia_entrada', 'transferencia', $id);
                }
            }
            return $id;
        });
    }

    /**
     * Revierte todos los movimientos de un documento (anulaciones). Si se indica
     * $depositoDestino, los reingresos van a ese depósito en lugar del original.
     */
    public static function revertirDocumento(string $docTipo, int $docId, string $motivo, ?int $depositoDestino = null): void
    {
        DB::transaccion(function () use ($docTipo, $docId, $motivo, $depositoDestino) {
            $movs = DB::filas(
                'SELECT m.*, l.lote, l.vencimiento FROM stock_movimientos m LEFT JOIN stock_lotes l ON l.id = m.lote_id
                 WHERE m.documento_tipo = ? AND m.documento_id = ? AND m.tipo <> ? ORDER BY m.id',
                [$docTipo, $docId, 'anulacion']
            );
            foreach ($movs as $m) {
                $cant = -(float) $m['cantidad'];
                $dep = ($cant > 0 && $depositoDestino) ? $depositoDestino : (int) $m['deposito_id'];
                self::mover((int) $m['producto_id'], $dep, $cant, 'anulacion', $docTipo, $docId, [
                    'lote' => $cant > 0 ? $m['lote'] : ($m['lote'] ?: null),
                    'vencimiento' => $m['vencimiento'],
                    'costo' => (float) $m['costo_unitario'],
                    'motivo' => $motivo,
                    'permitir_negativo' => true,
                ]);
            }
        });
    }

    /**
     * Ajuste de stock con motivo. $items = [[producto_id, cantidad_con_signo, lote?, vencimiento?], ...]
     */
    public static function ajustar(int $depositoId, string $motivo, array $items, ?string $observaciones = null): int
    {
        if (!isset(self::MOTIVOS_AJUSTE[$motivo])) {
            throw new ErrorNegocio('Motivo de ajuste inválido.');
        }
        $items = array_filter($items, fn ($i) => abs((float) $i[1]) >= 0.0005);
        if (!$items) {
            throw new ErrorNegocio('El ajuste no tiene cantidades.');
        }
        return DB::transaccion(function () use ($depositoId, $motivo, $items, $observaciones) {
            $id = DB::insertar('ajustes_stock', [
                'fecha' => ahora(), 'deposito_id' => $depositoId, 'motivo' => $motivo,
                'observaciones' => $observaciones, 'estado' => 'aplicado', 'usuario_id' => Auth::id(),
            ]);
            foreach ($items as $i) {
                [$prod, $cant] = $i;
                $lote = $i[2] ?? null;
                $venc = $i[3] ?? null;
                $p = DB::fila('SELECT costo, costo_promedio FROM productos WHERE id = ?', [$prod]);
                $costo = PrecioService::costoBase($p ?: ['costo' => 0]);
                DB::insertar('ajustes_stock_items', [
                    'ajuste_id' => $id, 'producto_id' => $prod, 'cantidad' => $cant,
                    'lote' => $lote, 'vencimiento' => $venc, 'costo_unitario' => $costo,
                ]);
                self::mover((int) $prod, $depositoId, (float) $cant, 'ajuste', 'ajuste', $id, [
                    'motivo' => self::MOTIVOS_AJUSTE[$motivo] . ($observaciones ? ': ' . $observaciones : ''),
                    'lote' => $lote, 'vencimiento' => $venc, 'permitir_negativo' => $motivo === 'rendicion',
                ]);
            }
            \App\Core\Auditoria::registrar('stock', 'ajuste', 'ajuste', $id, null, ['motivo' => $motivo, 'items' => count($items)], 'Ajuste de stock: ' . self::MOTIVOS_AJUSTE[$motivo]);
            return $id;
        });
    }

    /** Stock físico total por producto en depósitos (no vehículos). */
    public static function totalDepositos(int $productoId): float
    {
        return (float) DB::valor(
            "SELECT COALESCE(SUM(s.cantidad), 0) FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = ? AND d.tipo = 'deposito'",
            [$productoId]
        );
    }
}
