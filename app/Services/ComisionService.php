<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Comisiones de preventistas: % sobre ventas netas (sin IVA, descontando notas de crédito)
 * o sobre cobranzas, según la configuración de cada preventista.
 */
final class ComisionService
{
    /** Documentos no liquidados del período y totales. */
    public static function calcular(int $preventistaId, string $desde, string $hasta): array
    {
        $prev = DB::fila('SELECT * FROM preventistas WHERE id = ?', [$preventistaId]);
        if (!$prev) {
            throw new ErrorNegocio('Preventista inexistente.');
        }
        $rango = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
        if ($prev['comision_base'] === 'cobranzas') {
            $docs = DB::filas(
                "SELECT r.id, r.fecha, CONCAT('RC ', LPAD(r.numero, 8, '0')) AS numero, c.nombre_comercio, r.total AS base
                 FROM recibos r JOIN clientes c ON c.id = r.cliente_id
                 WHERE r.preventista_id = ? AND r.estado = 'emitido' AND r.liquidacion_id IS NULL AND r.fecha BETWEEN ? AND ?
                 ORDER BY r.fecha",
                array_merge([$preventistaId], $rango)
            );
        } else {
            $docs = DB::filas(
                "SELECT cp.id, cp.fecha, CONCAT(cp.tipo, ' ', LPAD(cp.numero, 8, '0')) AS numero, c.nombre_comercio,
                        (CASE WHEN cp.tipo LIKE 'NC%' THEN -1 ELSE 1 END) * (cp.total - cp.iva_21 - cp.iva_105 - cp.impuestos_internos) AS base
                 FROM comprobantes cp JOIN clientes c ON c.id = cp.cliente_id
                 WHERE cp.preventista_id = ? AND cp.estado = 'emitido' AND cp.liquidacion_id IS NULL
                   AND cp.tipo IN ('FA','FB','FC','RM','NCA','NCB','NCC') AND cp.fecha BETWEEN ? AND ?
                 ORDER BY cp.fecha",
                array_merge([$preventistaId], $rango)
            );
        }
        $base = round(array_sum(array_map(fn ($d) => (float) $d['base'], $docs)), 2);
        return [
            'preventista' => $prev, 'documentos' => $docs, 'base' => $base,
            'porcentaje' => (float) $prev['comision_porc'], 'comision' => round($base * (float) $prev['comision_porc'] / 100, 2),
        ];
    }

    public static function liquidar(int $preventistaId, string $desde, string $hasta, ?string $observaciones = null): int
    {
        return DB::transaccion(function () use ($preventistaId, $desde, $hasta, $observaciones) {
            $c = self::calcular($preventistaId, $desde, $hasta);
            if (!$c['documentos']) {
                throw new ErrorNegocio('No hay ventas ni cobranzas pendientes de liquidar en el período.');
            }
            $id = DB::insertar('comisiones_liquidaciones', [
                'preventista_id' => $preventistaId, 'desde' => $desde, 'hasta' => $hasta,
                'base_calculo' => $c['preventista']['comision_base'], 'importe_base' => $c['base'],
                'porcentaje' => $c['porcentaje'], 'importe' => $c['comision'], 'estado' => 'pendiente',
                'observaciones' => $observaciones, 'usuario_id' => Auth::id(), 'creado_en' => ahora(),
            ]);
            $tabla = $c['preventista']['comision_base'] === 'cobranzas' ? 'recibos' : 'comprobantes';
            $ids = array_map(fn ($d) => (int) $d['id'], $c['documentos']);
            DB::query("UPDATE $tabla SET liquidacion_id = ? WHERE id IN (" . DB::marcadores($ids) . ')', array_merge([$id], $ids));
            Auditoria::registrar('preventistas', 'liquidar', 'liquidacion', $id, null, ['importe' => $c['comision'], 'base' => $c['base']], 'Liquidación de comisiones');
            return $id;
        });
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        DB::transaccion(function () use ($id, $estado) {
            $l = DB::fila('SELECT * FROM comisiones_liquidaciones WHERE id = ? FOR UPDATE', [$id]);
            if (!$l || $l['estado'] !== 'pendiente' || !in_array($estado, ['pagada', 'anulada'], true)) {
                throw new ErrorNegocio('Cambio de estado no permitido.');
            }
            if ($estado === 'anulada') {
                DB::query('UPDATE comprobantes SET liquidacion_id = NULL WHERE liquidacion_id = ?', [$id]);
                DB::query('UPDATE recibos SET liquidacion_id = NULL WHERE liquidacion_id = ?', [$id]);
            }
            DB::actualizar('comisiones_liquidaciones', ['estado' => $estado], 'id = :id', ['id' => $id]);
            Auditoria::registrar('preventistas', $estado === 'pagada' ? 'pagar' : 'anular', 'liquidacion', $id, ['estado' => 'pendiente'], ['estado' => $estado]);
        });
    }
}
