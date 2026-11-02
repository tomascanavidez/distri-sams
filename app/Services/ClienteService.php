<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\DB;

/**
 * Reglas de crédito de clientes y bloqueo automático por deuda.
 */
final class ClienteService
{
    /**
     * Problemas de crédito al sumar $montoNuevo a la cuenta corriente del cliente.
     * Devuelve una lista de mensajes (vacía si está todo bien).
     */
    public static function problemasCredito(int $clienteId, float $montoNuevo = 0.0): array
    {
        if (Ajustes::get('credito_modo') === 'ignorar') {
            $c = DB::fila('SELECT estado FROM clientes WHERE id = ?', [$clienteId]);
            return ($c && $c['estado'] === 'bloqueado') ? ['El cliente está bloqueado.'] : [];
        }
        $c = DB::fila('SELECT * FROM clientes WHERE id = ?', [$clienteId]);
        if (!$c) {
            return ['Cliente inexistente.'];
        }
        $problemas = [];
        if ($c['estado'] === 'bloqueado') {
            $problemas[] = 'El cliente está bloqueado.';
        }
        $cc = CuentaCorriente::clientes();
        $vencida = $cc->deudaVencida($clienteId, (int) Ajustes::get('credito_tolerancia_dias', 0));
        if ($vencida > 0.009) {
            $problemas[] = 'Tiene deuda vencida por ' . money($vencida) . '.';
        }
        $limite = (float) $c['limite_credito'];
        if ($limite > 0) {
            $saldo = $cc->saldo($clienteId);
            if ($saldo + $montoNuevo > $limite + 0.009) {
                $problemas[] = 'Supera el límite de crédito (' . money($limite) . '): saldo actual ' . money($saldo) . ' + ' . money($montoNuevo) . '.';
            }
        }
        return $problemas;
    }

    /** Resumen de estado de cuenta para mostrar en pantallas (venta, pedido, app). */
    public static function resumen(int $clienteId): array
    {
        $cc = CuentaCorriente::clientes();
        $c = DB::fila('SELECT limite_credito, estado FROM clientes WHERE id = ?', [$clienteId]);
        $saldo = $cc->saldo($clienteId);
        return [
            'saldo' => $saldo,
            'vencido' => $cc->deudaVencida($clienteId),
            'limite' => (float) ($c['limite_credito'] ?? 0),
            'disponible' => (float) ($c['limite_credito'] ?? 0) > 0 ? round((float) $c['limite_credito'] - $saldo, 2) : null,
            'estado' => $c['estado'] ?? 'activo',
        ];
    }

    /**
     * Bloquea automáticamente a los clientes con deuda vencida hace más de N días
     * (parámetro bloqueo_automatico_dias; 0 = desactivado). Devuelve cuántos se bloquearon.
     */
    public static function bloqueoAutomatico(): int
    {
        $dias = (int) Ajustes::get('bloqueo_automatico_dias', 0);
        if ($dias <= 0) {
            return 0;
        }
        $limite = date('Y-m-d', strtotime("-$dias days"));
        $ids = DB::columna(
            "SELECT DISTINCT cc.cliente_id FROM cc_clientes cc JOIN clientes c ON c.id = cc.cliente_id
             WHERE c.estado = 'activo' AND cc.importe > 0 AND cc.pendiente > 0 AND cc.vencimiento < ?",
            [$limite]
        );
        foreach ($ids as $id) {
            DB::query("UPDATE clientes SET estado = 'bloqueado', actualizado_en = ? WHERE id = ?", [ahora(), $id]);
            Auditoria::registrar('clientes', 'bloquear', 'cliente', (int) $id, ['estado' => 'activo'], ['estado' => 'bloqueado'], "Bloqueo automático por deuda vencida de más de $dias días");
        }
        return count($ids);
    }

    /** Próximo código de cliente correlativo (C0001...). */
    public static function proximoCodigo(): string
    {
        $max = (int) DB::valor("SELECT MAX(CAST(SUBSTRING(codigo, 2) AS UNSIGNED)) FROM clientes WHERE codigo REGEXP '^C[0-9]+$'");
        return 'C' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
