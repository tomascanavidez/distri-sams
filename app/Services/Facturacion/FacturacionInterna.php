<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Services\Numeracion;

/**
 * Facturación interna/manual: numeración propia por punto de venta, sin CAE.
 * Sirve para remitos, presupuestos y para trabajar mientras no se integre ARCA
 * (o para cargar comprobantes emitidos con otro sistema/talonario).
 */
final class FacturacionInterna implements ProveedorFacturacion
{
    public function codigo(): string
    {
        return 'interno';
    }

    public function requiereAutorizacion(string $tipo): bool
    {
        return false;
    }

    public function proximoNumero(string $tipo, int $puntoVentaId): int
    {
        return Numeracion::siguiente($tipo, $puntoVentaId);
    }

    public function autorizar(array $comprobante, array $items): array
    {
        return ['cae' => null, 'cae_vencimiento' => null, 'observaciones' => null];
    }
}
