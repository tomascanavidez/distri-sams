<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/**
 * Fragmentos SQL compartidos por el panel y los reportes.
 */
final class Consultas
{
    /** Tipos de comprobante que cuentan como venta (las NC restan). */
    public const TIPOS_VENTA = "('FA','FB','FC','RM','NDA','NDB','NDC','NCA','NCB','NCC')";

    /** Signo de un comprobante: −1 para notas de crédito. */
    public static function signo(string $alias = 'c'): string
    {
        return "(CASE WHEN $alias.tipo LIKE 'NC%' THEN -1 ELSE 1 END)";
    }

    /** Total de ventas (con IVA) entre dos fechas (AAAA-MM-DD). */
    public static function ventasEntre(string $desde, string $hasta): float
    {
        return (float) DB::valor(
            'SELECT COALESCE(SUM(' . self::signo() . ' * c.total), 0) FROM comprobantes c
             WHERE c.estado = \'emitido\' AND c.tipo IN ' . self::TIPOS_VENTA . ' AND c.fecha BETWEEN ? AND ?',
            [$desde . ' 00:00:00', $hasta . ' 23:59:59']
        );
    }

    /** Stock físico total por producto en depósitos (subconsulta). */
    public static function stockTotal(string $alias = 'p'): string
    {
        return "(SELECT COALESCE(SUM(s.cantidad), 0) FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = $alias.id AND d.tipo = 'deposito')";
    }

    public static function stockDisponible(string $alias = 'p'): string
    {
        return "(SELECT COALESCE(SUM(s.cantidad - s.reservado), 0) FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = $alias.id AND d.tipo = 'deposito')";
    }

    /** Saldo de cuenta corriente del cliente (subconsulta). */
    public static function saldoCliente(string $alias = 'c'): string
    {
        return "(SELECT COALESCE(SUM(cc.importe), 0) FROM cc_clientes cc WHERE cc.cliente_id = $alias.id)";
    }

    public static function vencidoCliente(string $alias = 'c'): string
    {
        return "(SELECT COALESCE(SUM(cc.pendiente), 0) FROM cc_clientes cc WHERE cc.cliente_id = $alias.id AND cc.importe > 0 AND cc.pendiente > 0 AND cc.vencimiento < CURDATE())";
    }
}
