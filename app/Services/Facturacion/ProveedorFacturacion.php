<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

/**
 * Contrato de un proveedor de facturación.
 *
 * La implementación actual es "interna" (numeración propia, sin CAE). Para integrar la
 * facturación electrónica de ARCA (ex AFIP) se debe crear una clase que implemente esta
 * interfaz consumiendo los web services WSAA (autenticación) y WSFEv1 (FECAESolicitar)
 * y registrarla en FacturacionFactory. Ver docs/FACTURACION_ELECTRONICA.md.
 */
interface ProveedorFacturacion
{
    /** Código guardado en comprobantes.proveedor_facturacion (ej. "interno", "arca"). */
    public function codigo(): string;

    /** Indica si el comprobante necesita autorización externa (CAE) para este tipo. */
    public function requiereAutorizacion(string $tipo): bool;

    /**
     * Asigna el número del comprobante. Un proveedor externo lo obtiene del último
     * autorizado (FECompUltimoAutorizado + 1); el interno usa la tabla `numeracion`.
     */
    public function proximoNumero(string $tipo, int $puntoVentaId): int;

    /**
     * Autoriza el comprobante ya guardado. Debe devolver:
     * ['cae' => ?string, 'cae_vencimiento' => ?string (AAAA-MM-DD), 'observaciones' => ?string]
     * Si la autorización falla debe lanzar ErrorNegocio para que se revierta la transacción.
     *
     * @param array $comprobante Fila de `comprobantes` (con importes discriminados)
     * @param array $items       Filas de `comprobante_items`
     */
    public function autorizar(array $comprobante, array $items): array;
}
