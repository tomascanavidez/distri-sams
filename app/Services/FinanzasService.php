<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Cheques de terceros y gastos operativos.
 */
final class FinanzasService
{
    public const ESTADOS_CHEQUE = [
        'cartera' => 'En cartera',
        'depositado' => 'Depositado',
        'endosado' => 'Endosado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    public static function depositarCheque(int $chequeId, int $cuentaId, ?string $fecha = null): void
    {
        DB::transaccion(function () use ($chequeId, $cuentaId, $fecha) {
            $ch = DB::fila('SELECT * FROM cheques WHERE id = ? FOR UPDATE', [$chequeId]);
            if (!$ch || $ch['estado'] !== 'cartera') {
                throw new ErrorNegocio('Sólo se pueden depositar cheques en cartera.');
            }
            CajaService::movimientoBanco($cuentaId, 'ingreso', "Depósito cheque N° {$ch['numero']} ({$ch['banco']})", (float) $ch['importe'], $ch['numero'], 'cheque', $chequeId, $fecha);
            DB::actualizar('cheques', ['estado' => 'depositado', 'cuenta_deposito_id' => $cuentaId, 'fecha_estado' => ahora()], 'id = :id', ['id' => $chequeId]);
            Auditoria::registrar('finanzas', 'depositar', 'cheque', $chequeId, ['estado' => 'cartera'], ['estado' => 'depositado', 'cuenta_id' => $cuentaId]);
        });
    }

    /**
     * Cheque rechazado: si estaba depositado se debita del banco; si era de un cliente se le carga
     * el importe (más gastos opcionales) en su cuenta corriente.
     */
    public static function rechazarCheque(int $chequeId, float $gastos, string $motivo): void
    {
        DB::transaccion(function () use ($chequeId, $gastos, $motivo) {
            $ch = DB::fila('SELECT * FROM cheques WHERE id = ? FOR UPDATE', [$chequeId]);
            if (!$ch || !in_array($ch['estado'], ['cartera', 'depositado', 'endosado'], true)) {
                throw new ErrorNegocio('El cheque no se puede marcar como rechazado.');
            }
            if ($ch['estado'] === 'depositado' && $ch['cuenta_deposito_id']) {
                CajaService::movimientoBanco((int) $ch['cuenta_deposito_id'], 'egreso', "Cheque rechazado N° {$ch['numero']}", (float) $ch['importe'], $ch['numero'], 'cheque', $chequeId);
            }
            if ($ch['estado'] === 'endosado' && $ch['proveedor_id']) {
                // El proveedor nos lo devuelve: vuelve a ser deuda con él
                CuentaCorriente::proveedores()->registrar((int) $ch['proveedor_id'], 'cheque_rechazado', (float) $ch['importe'], "Cheque rechazado N° {$ch['numero']}", 'cheque', $chequeId);
            }
            if ($ch['cliente_id']) {
                CuentaCorriente::clientes()->registrar((int) $ch['cliente_id'], 'cheque_rechazado', (float) $ch['importe'] + max(0, $gastos), "Cheque rechazado N° {$ch['numero']} ({$ch['banco']})" . ($gastos > 0 ? ' + gastos' : ''), 'cheque', $chequeId);
            }
            DB::actualizar('cheques', ['estado' => 'rechazado', 'fecha_estado' => ahora(), 'observaciones' => mb_substr($motivo, 0, 255)], 'id = :id', ['id' => $chequeId]);
            Auditoria::registrar('finanzas', 'rechazar', 'cheque', $chequeId, ['estado' => $ch['estado']], ['estado' => 'rechazado', 'motivo' => $motivo, 'gastos' => $gastos]);
        });
    }

    /** $d: fecha, categoria_id, descripcion, importe, forma_pago (efectivo|banco), cuenta_bancaria_id, comprobante, vehiculo_id, caja_id */
    public static function registrarGasto(array $d): int
    {
        return DB::transaccion(function () use ($d) {
            $importe = round(parse_decimal($d['importe'] ?? 0), 2);
            if ($importe <= 0) {
                throw new ErrorNegocio('El importe del gasto debe ser mayor a cero.');
            }
            if (empty($d['categoria_id']) || trim((string) ($d['descripcion'] ?? '')) === '') {
                throw new ErrorNegocio('Indicá la categoría y la descripción del gasto.');
            }
            $forma = ($d['forma_pago'] ?? 'efectivo') === 'banco' ? 'banco' : 'efectivo';
            $fila = [
                'fecha' => parse_fecha($d['fecha'] ?? '') ?? hoy(), 'categoria_id' => (int) $d['categoria_id'],
                'descripcion' => mb_substr(trim((string) $d['descripcion']), 0, 200), 'importe' => $importe, 'forma_pago' => $forma,
                'comprobante' => $d['comprobante'] ?? null, 'vehiculo_id' => !empty($d['vehiculo_id']) ? (int) $d['vehiculo_id'] : null,
                'estado' => 'registrado', 'usuario_id' => Auth::id(), 'creado_en' => ahora(),
            ];
            if ($forma === 'efectivo') {
                $s = CajaService::sesionParaOperar(!empty($d['caja_id']) ? (int) $d['caja_id'] : null);
                $fila['caja_sesion_id'] = $s['id'];
            } else {
                $fila['cuenta_bancaria_id'] = (int) ($d['cuenta_bancaria_id'] ?? 0) ?: CajaService::cuentaPorForma('transferencia');
                if (!$fila['cuenta_bancaria_id']) {
                    throw new ErrorNegocio('Elegí la cuenta bancaria.');
                }
            }
            $id = DB::insertar('gastos', $fila);
            $cat = DB::valor('SELECT nombre FROM gastos_categorias WHERE id = ?', [$fila['categoria_id']]);
            $concepto = "Gasto: $cat - " . $fila['descripcion'];
            if ($forma === 'efectivo') {
                CajaService::movimiento((int) $fila['caja_sesion_id'], 'egreso', $concepto, $importe, 'gasto', $id);
            } else {
                CajaService::movimientoBanco((int) $fila['cuenta_bancaria_id'], 'egreso', $concepto, $importe, $fila['comprobante'], 'gasto', $id, $fila['fecha'] . ' ' . date('H:i:s'));
            }
            Auditoria::registrar('finanzas', 'gasto', 'gasto', $id, null, ['importe' => $importe, 'categoria' => $cat], $concepto);
            return $id;
        });
    }

    public static function anularGasto(int $id, string $motivo): void
    {
        DB::transaccion(function () use ($id, $motivo) {
            $g = DB::fila('SELECT * FROM gastos WHERE id = ? FOR UPDATE', [$id]);
            if (!$g || $g['estado'] !== 'registrado') {
                throw new ErrorNegocio('El gasto no se puede anular.');
            }
            if ($g['forma_pago'] === 'efectivo') {
                $mov = DB::valor("SELECT id FROM caja_movimientos WHERE origen_tipo = 'gasto' AND origen_id = ?", [$id]);
                if ($mov) {
                    CajaService::revertirMovimiento((int) $mov, 'Anulación gasto: ' . $g['descripcion']);
                }
            } else {
                $mov = DB::valor("SELECT id FROM banco_movimientos WHERE origen_tipo = 'gasto' AND origen_id = ?", [$id]);
                if ($mov) {
                    CajaService::revertirMovimientoBanco((int) $mov, 'Anulación gasto: ' . $g['descripcion']);
                }
            }
            DB::actualizar('gastos', ['estado' => 'anulado', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => $id]);
            Auditoria::registrar('finanzas', 'anular', 'gasto', $id, ['estado' => 'registrado', 'importe' => $g['importe']], ['estado' => 'anulado', 'motivo' => $motivo]);
        });
    }
}
