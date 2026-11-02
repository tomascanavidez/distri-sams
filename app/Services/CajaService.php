<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Cajas (efectivo) y cuentas bancarias / billeteras.
 */
final class CajaService
{
    public static function sesionAbierta(int $cajaId): ?array
    {
        return DB::fila("SELECT * FROM caja_sesiones WHERE caja_id = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1", [$cajaId]);
    }

    /** Caja asignada al usuario (o la única activa). */
    public static function cajaDelUsuario(?int $usuarioId = null): ?int
    {
        $usuarioId ??= Auth::id();
        $caja = $usuarioId ? DB::valor('SELECT caja_id FROM usuarios WHERE id = ?', [$usuarioId]) : null;
        if ($caja) {
            return (int) $caja;
        }
        $ids = DB::columna('SELECT id FROM cajas WHERE activo = 1 ORDER BY id');
        return $ids ? (int) $ids[0] : null;
    }

    /** Sesión abierta de la caja indicada o de la caja del usuario; error si no hay. */
    public static function sesionParaOperar(?int $cajaId = null): array
    {
        $cajaId ??= self::cajaDelUsuario();
        if (!$cajaId) {
            throw new ErrorNegocio('No hay ninguna caja configurada.');
        }
        $s = self::sesionAbierta($cajaId);
        if (!$s) {
            $nombre = DB::valor('SELECT nombre FROM cajas WHERE id = ?', [$cajaId]);
            throw new ErrorNegocio("La caja \"$nombre\" está cerrada. Abrila desde Finanzas › Caja para registrar movimientos en efectivo.");
        }
        return $s;
    }

    public static function abrir(int $cajaId, float $saldoInicial, ?string $observaciones = null): int
    {
        return DB::transaccion(function () use ($cajaId, $saldoInicial, $observaciones) {
            DB::fila('SELECT id FROM cajas WHERE id = ? FOR UPDATE', [$cajaId]);
            if (self::sesionAbierta($cajaId)) {
                throw new ErrorNegocio('Esta caja ya está abierta.');
            }
            $id = DB::insertar('caja_sesiones', [
                'caja_id' => $cajaId, 'usuario_apertura_id' => Auth::id(), 'fecha_apertura' => ahora(),
                'saldo_inicial' => round($saldoInicial, 2), 'estado' => 'abierta', 'observaciones' => $observaciones,
            ]);
            Auditoria::registrar('caja', 'abrir', 'caja_sesion', $id, null, ['saldo_inicial' => $saldoInicial], 'Apertura de caja');
            return $id;
        });
    }

    public static function movimiento(int $sesionId, string $tipo, string $concepto, float $importe, ?string $origenTipo = null, ?int $origenId = null): int
    {
        if (!in_array($tipo, ['ingreso', 'egreso'], true) || $importe <= 0) {
            throw new ErrorNegocio('Movimiento de caja inválido.');
        }
        $s = DB::fila('SELECT estado FROM caja_sesiones WHERE id = ?', [$sesionId]);
        if (!$s || $s['estado'] !== 'abierta') {
            throw new ErrorNegocio('La caja está cerrada.');
        }
        return DB::insertar('caja_movimientos', [
            'caja_sesion_id' => $sesionId, 'fecha' => ahora(), 'tipo' => $tipo,
            'concepto' => mb_substr($concepto, 0, 150), 'importe' => round($importe, 2),
            'origen_tipo' => $origenTipo, 'origen_id' => $origenId, 'usuario_id' => Auth::id(),
        ]);
    }

    public static function saldoTeorico(int $sesionId): float
    {
        $s = DB::fila('SELECT saldo_inicial FROM caja_sesiones WHERE id = ?', [$sesionId]);
        $mov = (float) DB::valor(
            "SELECT COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN importe ELSE -importe END), 0) FROM caja_movimientos WHERE caja_sesion_id = ?",
            [$sesionId]
        );
        return round((float) ($s['saldo_inicial'] ?? 0) + $mov, 2);
    }

    public static function cerrar(int $sesionId, float $contado, ?array $arqueo, ?string $observaciones): array
    {
        return DB::transaccion(function () use ($sesionId, $contado, $arqueo, $observaciones) {
            $s = DB::fila('SELECT * FROM caja_sesiones WHERE id = ? FOR UPDATE', [$sesionId]);
            if (!$s || $s['estado'] !== 'abierta') {
                throw new ErrorNegocio('La sesión de caja no está abierta.');
            }
            $teorico = self::saldoTeorico($sesionId);
            $dif = round($contado - $teorico, 2);
            DB::actualizar('caja_sesiones', [
                'usuario_cierre_id' => Auth::id(), 'fecha_cierre' => ahora(), 'saldo_teorico' => $teorico,
                'saldo_contado' => round($contado, 2), 'diferencia' => $dif,
                'arqueo' => $arqueo ? json_encode($arqueo) : null, 'estado' => 'cerrada',
                'observaciones' => trim(($s['observaciones'] ? $s['observaciones'] . ' | ' : '') . (string) $observaciones) ?: null,
            ], 'id = :id', ['id' => $sesionId]);
            Auditoria::registrar('caja', 'cerrar', 'caja_sesion', $sesionId, ['saldo_teorico' => $teorico], ['saldo_contado' => $contado, 'diferencia' => $dif], 'Cierre de caja');
            return ['teorico' => $teorico, 'contado' => $contado, 'diferencia' => $dif];
        });
    }

    /** Revierte un movimiento de caja (anulaciones) en la sesión abierta de esa misma caja. */
    public static function revertirMovimiento(int $movimientoId, string $concepto): void
    {
        $m = DB::fila('SELECT m.*, s.caja_id FROM caja_movimientos m JOIN caja_sesiones s ON s.id = m.caja_sesion_id WHERE m.id = ?', [$movimientoId]);
        if (!$m) {
            return;
        }
        $sesion = self::sesionParaOperar((int) $m['caja_id']);
        self::movimiento((int) $sesion['id'], $m['tipo'] === 'ingreso' ? 'egreso' : 'ingreso', $concepto, (float) $m['importe'], 'anulacion', (int) $m['id']);
    }

    // ---------------- Bancos y billeteras ----------------

    public static function movimientoBanco(int $cuentaId, string $tipo, string $concepto, float $importe, ?string $referencia = null, ?string $origenTipo = null, ?int $origenId = null, ?string $fecha = null): int
    {
        if (!in_array($tipo, ['ingreso', 'egreso'], true) || $importe <= 0) {
            throw new ErrorNegocio('Movimiento bancario inválido.');
        }
        if (!DB::valor('SELECT id FROM cuentas_bancarias WHERE id = ?', [$cuentaId])) {
            throw new ErrorNegocio('La cuenta bancaria no existe.');
        }
        return DB::insertar('banco_movimientos', [
            'cuenta_id' => $cuentaId, 'fecha' => $fecha ?? ahora(), 'tipo' => $tipo,
            'concepto' => mb_substr($concepto, 0, 150), 'importe' => round($importe, 2),
            'referencia' => $referencia ? mb_substr($referencia, 0, 60) : null,
            'origen_tipo' => $origenTipo, 'origen_id' => $origenId, 'usuario_id' => Auth::id(),
        ]);
    }

    public static function revertirMovimientoBanco(int $movimientoId, string $concepto): void
    {
        $m = DB::fila('SELECT * FROM banco_movimientos WHERE id = ?', [$movimientoId]);
        if ($m) {
            self::movimientoBanco((int) $m['cuenta_id'], $m['tipo'] === 'ingreso' ? 'egreso' : 'ingreso', $concepto, (float) $m['importe'], $m['referencia'], 'anulacion', (int) $m['id']);
        }
    }

    public static function saldoBanco(int $cuentaId): float
    {
        $ini = (float) DB::valor('SELECT saldo_inicial FROM cuentas_bancarias WHERE id = ?', [$cuentaId]);
        $mov = (float) DB::valor(
            "SELECT COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN importe ELSE -importe END), 0) FROM banco_movimientos WHERE cuenta_id = ?",
            [$cuentaId]
        );
        return round($ini + $mov, 2);
    }

    /** Cuenta por defecto para una forma de pago electrónica. */
    public static function cuentaPorForma(string $forma): ?int
    {
        $tipo = match ($forma) {
            'mercadopago' => 'billetera',
            'tarjeta' => 'tarjeta',
            default => 'banco',
        };
        $id = DB::valor('SELECT id FROM cuentas_bancarias WHERE activo = 1 AND tipo = ? ORDER BY id LIMIT 1', [$tipo]);
        $id ??= DB::valor('SELECT id FROM cuentas_bancarias WHERE activo = 1 ORDER BY id LIMIT 1');
        return $id ? (int) $id : null;
    }
}
