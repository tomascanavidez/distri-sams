<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Recibos de cobranza con pagos mixtos e imputación a comprobantes.
 * Cada forma de pago genera su movimiento: efectivo → caja (o pendiente de rendición si cobra
 * el chofer), transferencia / Mercado Pago / tarjeta → cuenta bancaria o billetera,
 * cheque → cartera de cheques de terceros.
 */
final class CobranzaService
{
    /**
     * $datos: cliente_id, pagos[], imputaciones[cc_id => importe], origen, hoja_ruta_id,
     *         comprobante_id, uuid, observaciones, fecha, caja_id
     */
    public static function crearRecibo(array $datos): int
    {
        $clienteId = (int) ($datos['cliente_id'] ?? 0);
        $pagos = self::normalizarPagos($datos['pagos'] ?? []);
        if (!$pagos) {
            throw new ErrorNegocio('Ingresá al menos un pago con importe mayor a cero.');
        }
        $uuid = $datos['uuid'] ?? null;
        if ($uuid && ($existente = DB::valor('SELECT id FROM recibos WHERE uuid = ?', [$uuid]))) {
            return (int) $existente; // ya se registró (reintento de sincronización)
        }

        return DB::transaccion(function () use ($datos, $clienteId, $pagos, $uuid) {
            $cliente = DB::fila('SELECT id, nombre_comercio, preventista_id FROM clientes WHERE id = ?', [$clienteId]);
            if (!$cliente) {
                throw new ErrorNegocio('El cliente no existe.');
            }
            $origen = $datos['origen'] ?? 'cobranza';
            $total = round(array_sum(array_column($pagos, 'importe')), 2);
            $pv = Numeracion::puntoVentaDefecto();
            $numero = Numeracion::siguiente('RC', $pv);
            $fecha = $datos['fecha'] ?? ahora();
            $id = DB::insertar('recibos', [
                'uuid' => $uuid, 'punto_venta_id' => $pv, 'numero' => $numero, 'fecha' => $fecha,
                'cliente_id' => $clienteId, 'total' => $total, 'origen' => $origen,
                'hoja_ruta_id' => $datos['hoja_ruta_id'] ?? null, 'comprobante_id' => $datos['comprobante_id'] ?? null,
                'preventista_id' => $cliente['preventista_id'], 'usuario_id' => Auth::id(), 'estado' => 'emitido',
                'observaciones' => $datos['observaciones'] ?? null, 'creado_en' => ahora(),
            ]);
            $nro = 'RC ' . sprintf('%04d-%08d', Numeracion::numeroPuntoVenta($pv), $numero);
            $concepto = "Cobranza $nro - " . $cliente['nombre_comercio'];
            $sesion = null;

            foreach ($pagos as $p) {
                $fila = [
                    'recibo_id' => $id, 'forma_pago' => $p['forma'], 'importe' => $p['importe'],
                    'referencia' => $p['referencia'] ?? null, 'pendiente_rendicion' => 0,
                ];
                switch ($p['forma']) {
                    case 'efectivo':
                        if ($origen === 'reparto') {
                            $fila['pendiente_rendicion'] = 1; // el chofer lo rinde al volver
                        } else {
                            $sesion ??= CajaService::sesionParaOperar(isset($datos['caja_id']) ? (int) $datos['caja_id'] : null);
                            $fila['caja_movimiento_id'] = CajaService::movimiento((int) $sesion['id'], 'ingreso', $concepto, $p['importe'], 'recibo', $id);
                        }
                        break;
                    case 'transferencia':
                    case 'mercadopago':
                    case 'tarjeta':
                        $cuenta = (int) ($p['cuenta_bancaria_id'] ?? 0) ?: CajaService::cuentaPorForma($p['forma']);
                        if (!$cuenta) {
                            throw new ErrorNegocio('No hay cuentas bancarias/billeteras configuradas para registrar el pago.');
                        }
                        $fila['cuenta_bancaria_id'] = $cuenta;
                        $fila['banco_movimiento_id'] = CajaService::movimientoBanco($cuenta, 'ingreso', $concepto, $p['importe'], $p['referencia'] ?? null, 'recibo', $id, $fecha);
                        if ($origen === 'reparto') {
                            $fila['pendiente_rendicion'] = 1; // se verifica en la rendición
                        }
                        break;
                    case 'cheque':
                        $ch = $p['cheque'] ?? [];
                        if (empty($ch['numero']) || empty($ch['banco']) || empty($ch['fecha_pago'])) {
                            throw new ErrorNegocio('Para cobrar con cheque indicá número, banco y fecha de pago.');
                        }
                        $fila['cheque_id'] = DB::insertar('cheques', [
                            'numero' => mb_substr((string) $ch['numero'], 0, 20), 'banco' => mb_substr((string) $ch['banco'], 0, 80),
                            'librador' => $ch['librador'] ?? $cliente['nombre_comercio'], 'cuit_librador' => $ch['cuit'] ?? null,
                            'fecha_emision' => parse_fecha($ch['fecha_emision'] ?? '') ?? hoy(),
                            'fecha_pago' => parse_fecha((string) $ch['fecha_pago']) ?? hoy(),
                            'importe' => $p['importe'], 'estado' => 'cartera', 'cliente_id' => $clienteId,
                            'recibo_id' => $id, 'fecha_estado' => ahora(), 'creado_en' => ahora(),
                        ]);
                        if ($origen === 'reparto') {
                            $fila['pendiente_rendicion'] = 1;
                        }
                        break;
                    case 'retencion':
                        break;
                    default:
                        throw new ErrorNegocio('Forma de pago inválida: ' . $p['forma']);
                }
                DB::insertar('recibo_pagos', $fila);
            }

            $cc = CuentaCorriente::clientes();
            $ccId = $cc->registrar($clienteId, 'recibo', -$total, "Recibo $nro", 'recibo', $id, null, $fecha, false);
            if (!empty($datos['imputaciones'])) {
                $cc->imputar($ccId, $datos['imputaciones']);
            }
            $cc->aplicarPendientes($clienteId);

            Auditoria::registrar('cobranzas', 'crear', 'recibo', $id, null, ['total' => $total, 'cliente_id' => $clienteId, 'pagos' => array_column($pagos, 'importe', 'forma')], "Recibo $nro");
            return $id;
        });
    }

    /** Limpia y valida la lista de pagos. */
    public static function normalizarPagos(array $pagos): array
    {
        $out = [];
        foreach ($pagos as $p) {
            $importe = round(parse_decimal($p['importe'] ?? 0), 2);
            $forma = (string) ($p['forma'] ?? $p['forma_pago'] ?? '');
            if ($importe <= 0 || $forma === '' || $forma === 'cuenta_corriente') {
                continue;
            }
            if (!array_key_exists($forma, formas_pago())) {
                throw new ErrorNegocio("Forma de pago inválida: $forma");
            }
            $p['forma'] = $forma;
            $p['importe'] = $importe;
            $out[] = $p;
        }
        return $out;
    }

    public static function anular(int $reciboId, string $motivo): void
    {
        DB::transaccion(function () use ($reciboId, $motivo) {
            $r = DB::fila('SELECT * FROM recibos WHERE id = ? FOR UPDATE', [$reciboId]);
            if (!$r) {
                throw new ErrorNegocio('El recibo no existe.');
            }
            if ($r['estado'] === 'anulado') {
                throw new ErrorNegocio('El recibo ya está anulado.');
            }
            $nro = 'RC ' . sprintf('%04d-%08d', Numeracion::numeroPuntoVenta((int) $r['punto_venta_id']), $r['numero']);
            foreach (DB::filas('SELECT * FROM recibo_pagos WHERE recibo_id = ?', [$reciboId]) as $p) {
                if ($p['forma_pago'] === 'efectivo' && !$p['caja_movimiento_id'] && !(int) $p['pendiente_rendicion'] && $r['origen'] === 'reparto') {
                    // Efectivo de reparto ya rendido: está en la caja de la oficina, se devuelve desde ahí.
                    $sesion = CajaService::sesionParaOperar();
                    CajaService::movimiento((int) $sesion['id'], 'egreso', "Anulación $nro", (float) $p['importe'], 'anulacion', $reciboId);
                }
                if ($p['caja_movimiento_id']) {
                    CajaService::revertirMovimiento((int) $p['caja_movimiento_id'], "Anulación $nro");
                }
                if ($p['banco_movimiento_id']) {
                    CajaService::revertirMovimientoBanco((int) $p['banco_movimiento_id'], "Anulación $nro");
                }
                if ($p['cheque_id']) {
                    $ch = DB::fila('SELECT estado, numero FROM cheques WHERE id = ?', [$p['cheque_id']]);
                    if ($ch && $ch['estado'] !== 'cartera') {
                        throw new ErrorNegocio("No se puede anular: el cheque N° {$ch['numero']} ya fue {$ch['estado']}.");
                    }
                    DB::actualizar('cheques', ['estado' => 'anulado', 'fecha_estado' => ahora()], 'id = :id', ['id' => $p['cheque_id']]);
                }
            }
            DB::query('UPDATE recibo_pagos SET pendiente_rendicion = 0 WHERE recibo_id = ?', [$reciboId]);
            CuentaCorriente::clientes()->anularDocumento('recibo', $reciboId, "Anulación $nro");
            DB::actualizar('recibos', ['estado' => 'anulado', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $reciboId]);
            Auditoria::registrar('cobranzas', 'anular', 'recibo', $reciboId, ['estado' => 'emitido'], ['estado' => 'anulado', 'motivo' => $motivo], "Anulación $nro");
        });
    }

    public static function numero(array $recibo): string
    {
        return 'RC ' . sprintf('%04d-%08d', Numeracion::numeroPuntoVenta((int) $recibo['punto_venta_id']), $recibo['numero']);
    }
}
