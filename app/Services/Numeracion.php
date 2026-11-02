<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\DB;

/**
 * Numeración correlativa por punto de venta y tipo, sin huecos ni duplicados
 * (bloqueo de fila con SELECT ... FOR UPDATE dentro de la transacción).
 */
final class Numeracion
{
    public static function siguiente(string $tipo, ?int $puntoVentaId = null): int
    {
        $pv = $puntoVentaId ?: self::puntoVentaDefecto();
        return DB::transaccion(function () use ($tipo, $pv) {
            DB::query('INSERT IGNORE INTO numeracion (punto_venta_id, tipo, ultimo_numero) VALUES (?, ?, 0)', [$pv, $tipo]);
            $ultimo = (int) DB::valor('SELECT ultimo_numero FROM numeracion WHERE punto_venta_id = ? AND tipo = ? FOR UPDATE', [$pv, $tipo]);
            $nuevo = $ultimo + 1;
            DB::query('UPDATE numeracion SET ultimo_numero = ? WHERE punto_venta_id = ? AND tipo = ?', [$nuevo, $pv, $tipo]);
            return $nuevo;
        });
    }

    public static function puntoVentaDefecto(): int
    {
        $id = (int) Ajustes::get('punto_venta_defecto', 1);
        $existe = DB::valor('SELECT id FROM puntos_venta WHERE id = ? AND activo = 1', [$id]);
        return $existe ? $id : (int) DB::valor('SELECT id FROM puntos_venta WHERE activo = 1 ORDER BY numero LIMIT 1');
    }

    public static function numeroPuntoVenta(int $id): int
    {
        return (int) DB::valor('SELECT numero FROM puntos_venta WHERE id = ?', [$id]);
    }
}
