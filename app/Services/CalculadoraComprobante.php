<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cálculo de importes de un comprobante (sin acceso a base de datos: fácil de probar).
 *
 * Los precios unitarios son FINALES (IVA e internos incluidos) por presentación.
 * Por cada línea: total = precio × cantidad × (1 − desc%) × (1 − desc general%)
 *                 internos = internos por unidad base × unidades × mismos descuentos
 *                 neto = (total − internos) / (1 + IVA%) ; IVA = total − internos − neto
 */
final class CalculadoraComprobante
{
    /**
     * @param array $items [['precio_unitario','cantidad','factor','descuento_porc','iva_alicuota','impuesto_interno'], ...]
     * @return array ['items' => [...con neto, iva, internos, total], 'subtotal', 'descuento_importe', 'total', 'neto_21', 'neto_105', 'neto_exento', 'iva_21', 'iva_105', 'impuestos_internos']
     */
    public static function calcular(array $items, float $descuentoGeneralPorc = 0.0, string $letra = 'B'): array
    {
        $r = [
            'items' => [], 'subtotal' => 0.0, 'descuento_importe' => 0.0, 'total' => 0.0,
            'neto_21' => 0.0, 'neto_105' => 0.0, 'neto_exento' => 0.0, 'iva_21' => 0.0, 'iva_105' => 0.0,
            'impuestos_internos' => 0.0,
        ];
        $g = max(0.0, min(100.0, $descuentoGeneralPorc)) / 100;

        foreach ($items as $it) {
            $precio = (float) ($it['precio_unitario'] ?? 0);
            $cantidad = (float) ($it['cantidad'] ?? 0);
            $factor = (float) ($it['factor'] ?? 1) ?: 1.0;
            $d = max(0.0, min(100.0, (float) ($it['descuento_porc'] ?? 0))) / 100;
            $iva = (float) ($it['iva_alicuota'] ?? 21);
            $internosUnit = (float) ($it['impuesto_interno'] ?? 0);

            $bruto = round($precio * $cantidad * (1 - $d), 2);
            $total = round($bruto * (1 - $g), 2);
            $internos = round($internosUnit * $cantidad * $factor * (1 - $d) * (1 - $g), 2);
            if ($internos > $total) {
                $internos = $total;
            }
            $base = $total - $internos;
            $neto = round($base / (1 + $iva / 100), 2);
            $ivaImporte = round($base - $neto, 2);

            $it['bruto'] = $bruto;
            $it['total'] = $total;
            $it['internos'] = $internos;
            $it['neto'] = $neto;
            $it['iva'] = $ivaImporte;
            $r['items'][] = $it;

            $r['subtotal'] += $bruto;
            $r['total'] += $total;
            $r['impuestos_internos'] += $internos;
            if ($iva >= 20) {
                $r['neto_21'] += $neto;
                $r['iva_21'] += $ivaImporte;
            } elseif ($iva > 0) {
                $r['neto_105'] += $neto;
                $r['iva_105'] += $ivaImporte;
            } else {
                $r['neto_exento'] += $neto;
            }
        }

        foreach (['subtotal', 'total', 'neto_21', 'neto_105', 'neto_exento', 'iva_21', 'iva_105', 'impuestos_internos'] as $k) {
            $r[$k] = round($r[$k], 2);
        }
        $r['descuento_importe'] = round($r['subtotal'] - $r['total'], 2);

        // Factura C (emisor monotributista): no se discrimina IVA.
        if ($letra === 'C') {
            $r['neto_exento'] = round($r['total'] - $r['impuestos_internos'], 2);
            $r['neto_21'] = $r['neto_105'] = $r['iva_21'] = $r['iva_105'] = 0.0;
        }
        return $r;
    }

    /** Letra del comprobante según la condición de IVA del emisor y del cliente. */
    public static function letra(string $condicionEmpresa, string $condicionCliente): string
    {
        if ($condicionEmpresa !== 'RI') {
            return 'C';
        }
        return $condicionCliente === 'RI' ? 'A' : 'B';
    }

    /** Tipo de comprobante: clase = factura | nc | nd | remito | presupuesto */
    public static function tipo(string $clase, string $letra): string
    {
        return match ($clase) {
            'factura' => 'F' . $letra,
            'nc' => 'NC' . $letra,
            'nd' => 'ND' . $letra,
            'remito' => 'RM',
            'presupuesto' => 'PR',
            default => throw new \InvalidArgumentException("Clase de comprobante inválida: $clase"),
        };
    }
}
