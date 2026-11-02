<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Devuelve el proveedor de facturación configurado para un punto de venta.
 * Para sumar ARCA: agregar 'arca' => ProveedorFacturacionArca::class en PROVEEDORES.
 */
final class FacturacionFactory
{
    private const PROVEEDORES = [
        'interno' => FacturacionInterna::class,
    ];

    public static function para(int $puntoVentaId): ProveedorFacturacion
    {
        $codigo = (string) (DB::valor('SELECT proveedor_facturacion FROM puntos_venta WHERE id = ?', [$puntoVentaId]) ?: 'interno');
        $clase = self::PROVEEDORES[$codigo] ?? null;
        if ($clase === null) {
            throw new ErrorNegocio("El proveedor de facturación \"$codigo\" no está disponible en esta instalación.");
        }
        return new $clase();
    }

    /** Proveedores disponibles (para la configuración de puntos de venta). */
    public static function disponibles(): array
    {
        return ['interno' => 'Interna / manual (sin CAE)'];
    }
}
